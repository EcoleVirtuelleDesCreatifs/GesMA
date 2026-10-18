/*
 * GesMA — stockage local protégé (IndexedDB + chiffrement AES-GCM).
 * La clé de chiffrement est générée sur l'appareil et marquée "non extractible" :
 * elle ne peut pas être lue ni copiée, seulement utilisée par l'application.
 */
(function (global) {
    'use strict';

    const DB_NAME = 'gesma';
    const DB_VERSION = 1;
    let dbPromise = null;
    let keyPromise = null;

    function open() {
        if (dbPromise) return dbPromise;
        dbPromise = new Promise((resolve, reject) => {
            if (!('indexedDB' in global)) return reject(new Error('IndexedDB indisponible'));
            const req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = () => {
                const db = req.result;
                if (!db.objectStoreNames.contains('kv')) db.createObjectStore('kv');
                if (!db.objectStoreNames.contains('queue')) {
                    const q = db.createObjectStore('queue', { keyPath: 'uuid' });
                    q.createIndex('user_id', 'user_id');
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
        return dbPromise;
    }

    function tx(store, mode, fn) {
        return open().then((db) => new Promise((resolve, reject) => {
            const t = db.transaction(store, mode);
            const s = t.objectStore(store);
            let result;
            Promise.resolve(fn(s)).then((r) => { result = r; });
            t.oncomplete = () => resolve(result);
            t.onerror = () => reject(t.error);
            t.onabort = () => reject(t.error);
        }));
    }

    function reqP(req) {
        return new Promise((resolve, reject) => { req.onsuccess = () => resolve(req.result); req.onerror = () => reject(req.error); });
    }

    const hasCrypto = !!(global.crypto && global.crypto.subtle);

    function cryptoKey() {
        if (!hasCrypto) return Promise.resolve(null);
        if (keyPromise) return keyPromise;
        keyPromise = tx('kv', 'readonly', (s) => reqP(s.get('__key'))).then((key) => {
            if (key) return key;
            return crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt'])
                .then((k) => tx('kv', 'readwrite', (s) => s.put(k, '__key')).then(() => k));
        });
        return keyPromise;
    }

    async function encrypt(value) {
        const json = JSON.stringify(value);
        const key = await cryptoKey();
        if (!key) return { plain: json };
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const data = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, new TextEncoder().encode(json));
        return { iv, data };
    }

    async function decrypt(box) {
        if (!box) return null;
        if (box.plain !== undefined) return JSON.parse(box.plain);
        const key = await cryptoKey();
        if (!key) return null;
        const buf = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: box.iv }, key, box.data);
        return JSON.parse(new TextDecoder().decode(buf));
    }

    function uuid() {
        if (global.crypto && crypto.randomUUID) return crypto.randomUUID();
        const b = (global.crypto && crypto.getRandomValues) ? crypto.getRandomValues(new Uint8Array(16)) : Uint8Array.from({ length: 16 }, () => Math.floor(Math.random() * 256));
        b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
        const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
        return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
    }

    const Store = {
        uuid,
        encrypted: hasCrypto,

        async set(key, value) {
            const box = await encrypt(value);
            return tx('kv', 'readwrite', (s) => s.put(box, key));
        },
        async get(key) {
            const box = await tx('kv', 'readonly', (s) => reqP(s.get(key)));
            try { return await decrypt(box); } catch (e) { return null; }
        },
        async remove(key) {
            return tx('kv', 'readwrite', (s) => s.delete(key));
        },

        /** Ajoute une opération à la file de synchronisation */
        async enqueue(userId, type, payload, label) {
            const op = {
                uuid: uuid(),
                user_id: userId,
                type,
                status: 'pending',
                created_at: new Date().toISOString(),
                message: null,
                box: await encrypt({ payload, label }),
            };
            await tx('queue', 'readwrite', (s) => s.put(op));
            return op.uuid;
        },
        /** Opérations de l'utilisateur, déchiffrées */
        async queue(userId) {
            const all = await tx('queue', 'readonly', (s) => reqP(s.index('user_id').getAll(userId)));
            const out = [];
            for (const op of all) {
                let content = null;
                try { content = await decrypt(op.box); } catch (e) { /* clé perdue */ }
                out.push({ uuid: op.uuid, type: op.type, status: op.status, created_at: op.created_at, synced_at: op.synced_at, message: op.message, payload: content && content.payload, label: content ? content.label : '(illisible)' });
            }
            return out.sort((a, b) => a.created_at.localeCompare(b.created_at));
        },
        async updateOp(uuidValue, changes) {
            return tx('queue', 'readwrite', async (s) => {
                const op = await reqP(s.get(uuidValue));
                if (op) s.put(Object.assign(op, changes));
            });
        },
        async removeOp(uuidValue) {
            return tx('queue', 'readwrite', (s) => s.delete(uuidValue));
        },
        /** Garde l'historique des 50 dernières opérations synchronisées */
        async pruneSynced(userId, keep = 50) {
            const ops = (await this.queue(userId)).filter((o) => o.status === 'synced' || o.status === 'conflict');
            const extra = ops.slice(0, Math.max(0, ops.length - keep));
            for (const o of extra) await this.removeOp(o.uuid);
        },
        async counts(userId) {
            const all = await tx('queue', 'readonly', (s) => reqP(s.index('user_id').getAll(userId)));
            return {
                pending: all.filter((o) => o.status === 'pending').length,
                failed: all.filter((o) => o.status === 'failed').length,
            };
        },
        /** Efface les données consultables (catalogue, clients...) sans perdre la file d'attente */
        async clearSnapshots() {
            const keys = await tx('kv', 'readonly', (s) => reqP(s.getAllKeys()));
            return tx('kv', 'readwrite', (s) => keys.filter((k) => k !== '__key').forEach((k) => s.delete(k)));
        },
    };

    global.GesMAStore = Store;
})(window);
