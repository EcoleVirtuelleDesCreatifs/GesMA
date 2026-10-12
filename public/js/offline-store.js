/*
 * GesMA — stockage local et synchronisation hors connexion.
 *
 * - Les données de référence (produits, stocks, clients, livraisons…) et les opérations
 *   en attente sont conservées dans IndexedDB, chiffrées en AES-GCM avec une clé
 *   non exportable propre à l'appareil.
 * - Chaque opération reçoit un identifiant unique (UUID) : le serveur ne l'applique
 *   qu'une seule fois, même si elle est envoyée plusieurs fois.
 * - Les données locales sont effacées à la déconnexion.
 */
(function (global) {
    'use strict';

    var DB_NAME = 'gesma-offline';
    var DB_VERSION = 1;
    var BOOTSTRAP_MAX_AGE = 5 * 60 * 1000;
    var BATCH_SIZE = 50;
    var dbPromise = null;
    var keyPromise = null;
    var syncing = null;

    function openDb() {
        if (dbPromise) return dbPromise;
        dbPromise = new Promise(function (resolve, reject) {
            if (!global.indexedDB) return reject(new Error('IndexedDB indisponible'));
            var req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = function () {
                var db = req.result;
                if (!db.objectStoreNames.contains('meta')) db.createObjectStore('meta');
                if (!db.objectStoreNames.contains('data')) db.createObjectStore('data');
                if (!db.objectStoreNames.contains('outbox')) db.createObjectStore('outbox', { keyPath: 'uuid' });
            };
            req.onsuccess = function () { resolve(req.result); };
            req.onerror = function () { reject(req.error); };
        });
        return dbPromise;
    }

    function tx(store, mode, fn) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var t = db.transaction(store, mode);
                var result;
                Promise.resolve(fn(t.objectStore(store), function (r) { result = r; })).catch(reject);
                t.oncomplete = function () { resolve(result); };
                t.onerror = function () { reject(t.error); };
                t.onabort = function () { reject(t.error); };
            });
        });
    }

    function get(store, key) {
        return tx(store, 'readonly', function (s, done) {
            var r = s.get(key);
            r.onsuccess = function () { done(r.result); };
        });
    }

    function put(store, value, key) {
        return tx(store, 'readwrite', function (s) { key === undefined ? s.put(value) : s.put(value, key); });
    }

    function del(store, key) {
        return tx(store, 'readwrite', function (s) { s.delete(key); });
    }

    function all(store) {
        return tx(store, 'readonly', function (s, done) {
            var r = s.getAll();
            r.onsuccess = function () { done(r.result || []); };
        });
    }

    // ---------- Chiffrement ----------
    var subtle = global.crypto && global.crypto.subtle;

    function cryptoKey() {
        if (!subtle) return Promise.resolve(null);
        if (keyPromise) return keyPromise;
        keyPromise = get('meta', 'key').then(function (key) {
            if (key) return key;
            return subtle.generateKey({ name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']).then(function (k) {
                return put('meta', k, 'key').then(function () { return k; });
            });
        }).catch(function () { return null; });
        return keyPromise;
    }

    function encrypt(value) {
        return cryptoKey().then(function (key) {
            var plain = new TextEncoder().encode(JSON.stringify(value));
            if (!key) return { plain: JSON.stringify(value) };
            var iv = crypto.getRandomValues(new Uint8Array(12));
            return subtle.encrypt({ name: 'AES-GCM', iv: iv }, key, plain).then(function (buf) {
                return { iv: iv, data: buf };
            });
        });
    }

    function decrypt(box) {
        if (!box) return Promise.resolve(null);
        if (box.plain !== undefined) return Promise.resolve(JSON.parse(box.plain));
        return cryptoKey().then(function (key) {
            if (!key) return null;
            return subtle.decrypt({ name: 'AES-GCM', iv: box.iv }, key, box.data).then(function (buf) {
                return JSON.parse(new TextDecoder().decode(buf));
            });
        }).catch(function () { return null; });
    }

    // ---------- Utilitaires ----------
    function uuid() {
        if (global.crypto && crypto.randomUUID) return crypto.randomUUID();
        var b = crypto.getRandomValues(new Uint8Array(16));
        b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
        var h = Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
        return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
    }

    function deviceId() {
        try {
            var id = localStorage.getItem('gesma.device');
            if (!id) { id = uuid(); localStorage.setItem('gesma.device', id); }
            return id;
        } catch (e) { return null; }
    }

    function emit() {
        return state().then(function (s) {
            global.dispatchEvent(new CustomEvent('gesma:sync-state', { detail: s }));
            return s;
        });
    }

    // ---------- API publique ----------
    var Store = {
        uuid: uuid,

        isOnline: function () { return navigator.onLine !== false; },

        saveBootstrap: function (data) {
            return encrypt(data).then(function (box) {
                return put('data', box, 'bootstrap');
            }).then(function () {
                return put('meta', { at: Date.now(), userId: data.user && data.user.id }, 'bootstrapInfo');
            });
        },

        bootstrap: function () {
            return get('data', 'bootstrap').then(decrypt).catch(function () { return null; });
        },

        /** Enregistre une opération à envoyer au serveur. */
        queue: function (type, payload, summary) {
            var op = { uuid: uuid(), type: type, created_at: new Date().toISOString() };
            return Store.bootstrap().then(function (b) {
                return encrypt(payload).then(function (box) {
                    return put('outbox', {
                        uuid: op.uuid, type: type, created_at: op.created_at, status: 'pending',
                        message: null, attempts: 0, summary: summary || null,
                        user_id: b && b.user ? b.user.id : null, box: box
                    });
                });
            }).then(function () {
                emit();
                if (Store.isOnline()) Store.sync();
                return op;
            });
        },

        outbox: function () {
            return all('outbox').then(function (ops) {
                return Promise.all(ops.map(function (op) {
                    return decrypt(op.box).then(function (payload) {
                        var copy = Object.assign({}, op, { payload: payload });
                        delete copy.box;
                        return copy;
                    });
                }));
            }).then(function (ops) {
                return ops.sort(function (a, b) { return a.created_at < b.created_at ? -1 : 1; });
            });
        },

        removeOperation: function (id) { return del('outbox', id).then(emit); },

        retryOperation: function (id) {
            return get('outbox', id).then(function (op) {
                if (!op) return;
                op.status = 'pending'; op.message = null;
                return put('outbox', op);
            }).then(emit).then(function () { if (Store.isOnline()) Store.sync(); });
        },

        state: function () { return state(); },

        /** Télécharge les données de référence puis envoie les opérations en attente. */
        sync: function (options) {
            options = options || {};
            if (syncing) return syncing;
            if (!Store.isOnline()) return emit();
            // L'erreur est déjà enregistrée par doSync (affichée dans l'indicateur).
            syncing = doSync(options).catch(function () {}).then(function () {
                syncing = null;
                return emit();
            });
            emit();
            return syncing;
        },

        refreshIfStale: function () {
            return get('meta', 'bootstrapInfo').then(function (info) {
                if (!info || Date.now() - info.at > BOOTSTRAP_MAX_AGE) return Store.sync();
                return Store.outbox().then(function (ops) {
                    if (ops.some(function (o) { return o.status === 'pending'; })) return Store.sync();
                    return emit();
                });
            });
        },

        /** Efface toutes les données locales (déconnexion). */
        wipe: function () {
            keyPromise = null;
            var close = dbPromise ? dbPromise.then(function (db) { db.close(); }).catch(function () {}) : Promise.resolve();
            dbPromise = null;
            return close.then(function () {
                return new Promise(function (resolve) {
                    var req = indexedDB.deleteDatabase(DB_NAME);
                    req.onsuccess = req.onerror = req.onblocked = function () { resolve(); };
                });
            }).then(function () {
                try { localStorage.removeItem('gesma.lastSync'); } catch (e) {}
            });
        }
    };

    function state() {
        return Promise.all([all('outbox'), get('meta', 'lastSync'), get('meta', 'lastError'), get('meta', 'bootstrapInfo')])
            .then(function (r) {
                var ops = r[0] || [];
                var count = function (s) { return ops.filter(function (o) { return o.status === s; }).length; };
                return {
                    online: Store.isOnline(),
                    syncing: !!syncing,
                    pending: count('pending'),
                    failed: count('failed'),
                    conflict: count('conflict'),
                    lastSync: r[1] ? r[1].at : null,
                    lastError: r[2] && (!r[1] || r[2].at > r[1].at) ? r[2].message : null,
                    bootstrapAt: r[3] ? r[3].at : null,
                    authRequired: !!(r[2] && r[2].auth && (!r[1] || r[2].at > r[1].at))
                };
            })
            .catch(function () { return { online: Store.isOnline(), pending: 0, failed: 0, conflict: 0, lastSync: null, unsupported: true }; });
    }

    function fetchJson(url, init) {
        init = init || {};
        init.credentials = 'same-origin';
        init.headers = Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, init.headers || {});
        return fetch(url, init).then(function (res) {
            if (res.status === 401 || res.status === 419) {
                var err = new Error('Session expirée : reconnectez-vous pour synchroniser les opérations en attente.');
                err.auth = true;
                throw err;
            }
            if (!res.ok) throw new Error('Erreur serveur (' + res.status + ')');
            return res.json();
        });
    }

    function doSync() {
        return fetchJson('/sync/bootstrap').then(function (data) {
            return Store.saveBootstrap(data).then(function () { return pushPending(data); });
        }).then(function (applied) {
            var done = put('meta', { at: Date.now() }, 'lastSync');
            // Les stocks ont changé : on rafraîchit les données locales.
            if (applied) {
                return done.then(function () { return fetchJson('/sync/bootstrap'); }).then(Store.saveBootstrap);
            }
            return done;
        }).catch(function (e) {
            return put('meta', { at: Date.now(), message: e.message, auth: !!e.auth }, 'lastError').then(function () { throw e; });
        });
    }

    function pushPending(bootstrap) {
        return Store.outbox().then(function (ops) {
            var userId = bootstrap.user.id;
            var pending = ops.filter(function (o) { return o.status === 'pending'; });
            var foreign = pending.filter(function (o) { return o.user_id && o.user_id !== userId; });
            pending = pending.filter(function (o) { return !o.user_id || o.user_id === userId; });

            return Promise.all(foreign.map(function (o) {
                return markOp(o.uuid, 'failed', 'Opération saisie par un autre utilisateur sur cet appareil.');
            })).then(function () {
                var applied = 0;
                var chain = Promise.resolve();
                for (var i = 0; i < pending.length; i += BATCH_SIZE) {
                    (function (batch) {
                        chain = chain.then(function () {
                            return fetchJson('/sync/push', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': bootstrap.csrf_token },
                                body: JSON.stringify({
                                    device_id: deviceId(),
                                    operations: batch.map(function (o) {
                                        return { uuid: o.uuid, type: o.type, payload: o.payload, created_at: o.created_at };
                                    })
                                })
                            }).then(function (res) {
                                return Promise.all((res.results || []).map(function (r) {
                                    if (r.status === 'applied' || r.status === 'duplicate' || r.status === 'resolved') {
                                        applied++;
                                        return logDone(r).then(function () { return del('outbox', r.uuid); });
                                    }
                                    return markOp(r.uuid, r.status === 'conflict' ? 'conflict' : 'failed', r.message);
                                }));
                            });
                        });
                    })(pending.slice(i, i + BATCH_SIZE));
                }
                return chain.then(function () { return applied; });
            });
        });
    }

    function markOp(id, status, message) {
        return get('outbox', id).then(function (op) {
            if (!op) return;
            op.status = status; op.message = message; op.attempts = (op.attempts || 0) + 1;
            return put('outbox', op);
        });
    }

    function logDone(result) {
        return get('meta', 'history').then(function (h) {
            h = h || [];
            h.unshift({ at: Date.now(), message: result.message || 'Opération synchronisée.' });
            return put('meta', h.slice(0, 30), 'history');
        });
    }

    Store.history = function () { return get('meta', 'history').then(function (h) { return h || []; }); };

    // Synchronisation automatique au retour de la connexion.
    global.addEventListener('online', function () { Store.sync(); });
    global.addEventListener('offline', function () { emit(); });

    global.GesmaStore = Store;
})(window);
