/*
 * GesMA — interface et synchronisation des opérations hors connexion.
 */
(function (global) {
    'use strict';

    const cfg = global.GesMA || {};
    const userId = cfg.userId;

    /* ---------------- Utilitaires d'interface ---------------- */
    const UI = {
        money(n) {
            const v = Math.round(Number(n) || 0);
            return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' F CFA';
        },
        date(iso) {
            if (!iso) return '—';
            const d = new Date(iso);
            return d.toLocaleDateString('fr-FR') + ' ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        },
        esc(s) {
            return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        },
    };
    global.GesMAUI = UI;

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-menu-toggle]')) document.body.classList.toggle('nav-open');
        else if (document.body.classList.contains('nav-open') && !e.target.closest('#sidebar')) document.body.classList.remove('nav-open');

        const tabBtn = e.target.closest('[data-tabs] [data-tab]');
        if (tabBtn) {
            const tabs = tabBtn.closest('[data-tabs]');
            tabs.querySelectorAll('[data-tab]').forEach((b) => b.classList.toggle('active', b === tabBtn));
            const scope = tabs.parentElement;
            scope.querySelectorAll('[data-tab-panel]').forEach((p) => p.classList.toggle('hidden', p.dataset.tabPanel !== tabBtn.dataset.tab));
        }
    });

    // Période personnalisée : affiche les dates
    document.querySelectorAll('[data-period-select]').forEach((sel) => {
        const form = sel.closest('form');
        const toggle = () => form.querySelectorAll('[data-custom-period]').forEach((f) => f.classList.toggle('hidden', sel.value !== 'custom'));
        sel.addEventListener('change', () => {
            toggle();
            if (form.hasAttribute('data-autosubmit') && sel.value !== 'custom') form.submit();
        });
        toggle();
    });

    // Formulaire de mouvement de stock
    const moveForm = document.querySelector('[data-correction-hint]')?.closest('form');
    if (moveForm) {
        const update = () => {
            const mode = moveForm.querySelector('input[name=mode]:checked')?.value;
            moveForm.querySelector('[data-correction-hint]').classList.toggle('hidden', mode !== 'correction');
            moveForm.querySelector('[data-qty-label]').textContent = mode === 'correction' ? 'Quantité réelle comptée *' : 'Quantité *';
            moveForm.querySelector('[data-reason-required]').classList.toggle('hidden', mode === 'entree');
            moveForm.querySelector('#reason').required = mode !== 'entree';
        };
        moveForm.addEventListener('change', update);
        update();
    }

    // Transfert : quantité disponible au départ
    const tProduct = document.querySelector('[data-transfer-product]');
    if (tProduct) {
        const from = document.querySelector('[data-transfer-from]');
        const out = document.querySelector('[data-transfer-available]');
        const update = () => {
            const opt = tProduct.selectedOptions[0];
            if (!opt || !opt.dataset.stocks) { out.textContent = ''; return; }
            const stocks = JSON.parse(opt.dataset.stocks || '{}');
            out.textContent = 'Disponible dans ce magasin : ' + (stocks[from.value] ?? 0);
        };
        tProduct.addEventListener('change', update);
        from.addEventListener('change', update);
        update();
    }

    /* ---------------- Service worker (mode hors connexion) ---------------- */
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').then(() => navigator.serviceWorker.ready).then((reg) => {
            // Met en cache la page de saisie hors connexion de l'utilisateur connecté
            if (userId && navigator.onLine && reg.active) reg.active.postMessage({ type: 'cache-offline-page', url: cfg.offlineUrl });
        }).catch(() => {});
    }

    if (!userId || !global.GesMAStore) return;
    const Store = global.GesMAStore;

    /* ---------------- Synchronisation ---------------- */
    const KEY_SNAPSHOT = 'snapshot:' + userId;
    const LS_LAST_SYNC = 'gesma:lastSync:' + userId;
    const LS_LAST_DATA = 'gesma:lastData:' + userId;
    const LS_DEVICE = 'gesma:device';
    let syncing = false;
    let sessionExpired = false;

    function deviceId() {
        let id = null;
        try { id = localStorage.getItem(LS_DEVICE); } catch (e) { /* stockage bloqué */ }
        if (!id) { id = Store.uuid(); try { localStorage.setItem(LS_DEVICE, id); } catch (e) { /* ignore */ } }
        return id;
    }
    function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
    function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* ignore */ } }

    async function fetchJson(url, options = {}) {
        const res = await fetch(url, Object.assign({ credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }, options));
        if (res.status === 401 || res.status === 419 || res.redirected) {
            sessionExpired = true;
            throw new Error('session');
        }
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    /** Télécharge les données consultables hors connexion */
    async function refreshData(force = false) {
        if (!navigator.onLine) return null;
        const last = Number(lsGet(LS_LAST_DATA) || 0);
        if (!force && Date.now() - last < 5 * 60 * 1000) return Store.get(KEY_SNAPSHOT);
        const data = await fetchJson(cfg.dataUrl);
        sessionExpired = false;
        await Store.set(KEY_SNAPSHOT, data);
        lsSet(LS_LAST_DATA, String(Date.now()));
        document.dispatchEvent(new CustomEvent('gesma:data', { detail: data }));
        return data;
    }

    /** Envoie la file d'attente au serveur */
    async function syncNow() {
        if (syncing || !navigator.onLine) return;
        const ops = (await Store.queue(userId)).filter((o) => o.status === 'pending');
        if (!ops.length) { await updateIndicator(); return; }
        syncing = true;
        await updateIndicator();
        try {
            // Jeton CSRF à jour (la page hors connexion peut venir du cache)
            const data = await refreshData(true);
            const token = data.csrf_token;
            for (let i = 0; i < ops.length; i += 50) {
                const batch = ops.slice(i, i + 50);
                const body = {
                    device_id: deviceId(),
                    operations: batch.map((o) => ({ uuid: o.uuid, type: o.type, payload: o.payload, recorded_at: o.created_at })),
                };
                const res = await fetchJson(cfg.syncUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(body),
                });
                for (const r of res.results) {
                    const status = r.status === 'appliquee' ? 'synced' : (r.status === 'conflit' ? 'conflict' : 'failed');
                    await Store.updateOp(r.uuid, { status, message: r.message, synced_at: new Date().toISOString() });
                }
            }
            lsSet(LS_LAST_SYNC, new Date().toISOString());
            await Store.pruneSynced(userId);
            await refreshData(true); // stocks à jour après synchronisation
        } catch (e) {
            // Réseau coupé ou session expirée : les opérations restent en attente
        } finally {
            syncing = false;
            await updateIndicator();
            document.dispatchEvent(new CustomEvent('gesma:synced'));
        }
    }

    async function updateIndicator() {
        const el = document.getElementById('sync-status');
        const banner = document.getElementById('offline-banner');
        const online = navigator.onLine;
        let counts = { pending: 0, failed: 0 };
        try { counts = await Store.counts(userId); } catch (e) { /* ignore */ }
        if (banner) banner.classList.toggle('hidden', online);
        if (!el) return;
        el.classList.toggle('offline', !online);
        el.classList.toggle('syncing', syncing);
        const last = lsGet(LS_LAST_SYNC);
        const label = el.querySelector('[data-sync-label]');
        label.textContent = !online ? 'Hors connexion' : (syncing ? 'Synchronisation…' : (sessionExpired ? 'Reconnexion requise' : 'En ligne'));
        el.title = (online ? 'Connecté' : 'Pas de connexion Internet') + ' — dernière synchronisation : ' + (last ? UI.date(last) : 'jamais');
        const p = el.querySelector('[data-sync-pending]');
        const f = el.querySelector('[data-sync-failed]');
        p.textContent = counts.pending; p.classList.toggle('hidden', !counts.pending);
        f.textContent = counts.failed; f.classList.toggle('hidden', !counts.failed);
        document.dispatchEvent(new CustomEvent('gesma:status', { detail: { online, syncing, counts, last, sessionExpired } }));
    }

    global.GesMASync = {
        userId,
        snapshotKey: KEY_SNAPSHOT,
        refreshData,
        syncNow,
        updateIndicator,
        lastSync: () => lsGet(LS_LAST_SYNC),
        lastData: () => Number(lsGet(LS_LAST_DATA) || 0),
        isSessionExpired: () => sessionExpired,
        async enqueue(type, payload, label) {
            const id = await Store.enqueue(userId, type, payload, label);
            await updateIndicator();
            if (navigator.onLine) syncNow();
            return id;
        },
    };

    addEventListener('online', () => { updateIndicator(); syncNow(); });
    addEventListener('offline', updateIndicator);
    setInterval(() => { if (navigator.onLine) syncNow(); }, 60 * 1000);

    // Déconnexion : tente d'abord d'envoyer les opérations en attente
    document.querySelectorAll('[data-logout]').forEach((a) => a.addEventListener('click', async (e) => {
        e.preventDefault();
        try { await syncNow(); } catch (err) { /* ignore */ }
        const { pending } = await Store.counts(userId).catch(() => ({ pending: 0 }));
        if (pending && !confirm(pending + ' opération(s) ne sont pas encore synchronisées. Elles resteront sur cet appareil et seront envoyées à votre prochaine connexion. Se déconnecter ?')) return;
        document.getElementById('logout-form').submit();
    }));

    updateIndicator();
    if (navigator.onLine) {
        syncNow().then(() => refreshData()).catch(() => {});
    }
})(window);
