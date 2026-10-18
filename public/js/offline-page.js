/*
 * GesMA — page de saisie hors connexion.
 * Utilise les données enregistrées sur l'appareil (catalogue, stocks, clients, livraisons)
 * et place chaque saisie dans la file de synchronisation.
 */
(function () {
    'use strict';
    const root = document.querySelector('[data-offline-app]');
    if (!root || !window.GesMASync) return;

    const Sync = window.GesMASync;
    const Store = window.GesMAStore;
    const UI = window.GesMAUI;
    const $ = (sel, el = document) => el.querySelector(sel);
    const $$ = (sel, el = document) => [...el.querySelectorAll(sel)];
    const today = () => new Date().toISOString().slice(0, 10);
    let data = null;

    const TYPE_LABELS = { order: 'Commande / vente', expense: 'Dépense', delivery_status: 'Livraison' };
    const STATUS = {
        pending: ['En attente', 'badge-warning'],
        synced: ['Synchronisée', 'badge-success'],
        conflict: ['À vérifier', 'badge-warning'],
        failed: ['Refusée', 'badge-danger'],
    };

    /* ---------- Chargement des données locales ---------- */
    async function load() {
        data = await Store.get(Sync.snapshotKey);
        if (!data && navigator.onLine) {
            try { data = await Sync.refreshData(true); } catch (e) { /* ignore */ }
        }
        render();
    }

    async function saveSnapshot() {
        await Store.set(Sync.snapshotKey, data);
    }

    function render() {
        $('[data-out="no-data"]').classList.toggle('hidden', !!data);
        $('[data-out="not-encrypted"]').classList.toggle('hidden', Store.encrypted);
        const t = Sync.lastData();
        $('[data-out="data-date"]').textContent = data ? UI.date(data.generated_at) : '—';
        if (!data) { renderQueue(); return; }

        // Onglets selon les droits
        $$('[data-perm]', root).forEach((b) => b.classList.toggle('hidden', !data.user.can[b.dataset.perm]));
        const firstVisible = $$('[data-tabs] [data-tab]', root).find((b) => !b.classList.contains('hidden'));
        const active = $('[data-tabs] .active', root);
        if (active && active.classList.contains('hidden') && firstVisible) firstVisible.click();

        fillSelects();
        renderStock();
        renderCustomers();
        renderDeliveries();
        renderQueue();
        if (!$('[data-lines]').children.length) addLine();
        recomputeOrder();
    }

    function fillSelects() {
        $$('[data-fill]', root).forEach((sel) => {
            const kind = sel.dataset.fill;
            const current = sel.value;
            let options = [];
            if (kind === 'stores') options = data.stores.map((s) => [s.id, s.name]);
            if (kind === 'payment_methods') options = Object.entries(data.payment_methods);
            if (kind === 'expense_categories') options = [['', '— Choisir —'], ...Object.entries(data.expense_categories)];
            sel.innerHTML = options.map(([v, l]) => `<option value="${UI.esc(v)}">${UI.esc(l)}</option>`).join('');
            if (current) sel.value = current;
            else if (kind === 'stores' && data.user.store_id) sel.value = data.user.store_id;
        });
        // Un seul magasin : champ masqué
        $$('[data-store-field]', root).forEach((f) => f.classList.toggle('hidden', data.stores.length <= 1));
        const spent = $('[data-form="expense"] [name=spent_at]');
        if (!spent.value) spent.value = today();
        spent.max = today();
    }

    function storeId(form) {
        return Number($('[name=store_id]', form).value || data.user.store_id || (data.stores[0] && data.stores[0].id));
    }

    /* ---------- Vente / commande ---------- */
    const orderForm = $('[data-form="order"]');
    const lines = $('[data-lines]', orderForm);

    function productOptions() {
        return '<option value="">— Choisir —</option>' + data.products.map((p) => `<option value="${p.id}">${UI.esc(p.name)} — ${UI.esc(p.reference)}</option>`).join('');
    }
    function addLine() {
        if (!data) return;
        const tr = document.createElement('tr');
        tr.innerHTML = `<td><select data-product>${productOptions()}</select><small class="muted" data-available></small></td>
            <td><input type="number" min="1" value="1" data-qty></td>
            <td class="right num nowrap" data-price>—</td><td class="right num nowrap" data-total>—</td>
            <td><button type="button" class="btn btn-sm" data-remove>✕</button></td>`;
        lines.appendChild(tr);
    }
    function productById(id) { return data.products.find((p) => p.id === Number(id)); }

    function recomputeOrder() {
        if (!data) return;
        const sid = storeId(orderForm);
        const mode = $('[name=mode]:checked', orderForm).value;
        let subtotal = 0;
        $$('tr', lines).forEach((tr) => {
            const p = productById($('[data-product]', tr).value);
            const qty = parseInt($('[data-qty]', tr).value || '0', 10);
            const av = $('[data-available]', tr);
            if (!p) { $('[data-price]', tr).textContent = '—'; $('[data-total]', tr).textContent = '—'; av.textContent = ''; return; }
            const stock = Number(p.stocks[sid] ?? 0);
            av.textContent = ` Stock connu : ${stock}${qty > stock ? ' — insuffisant, sera vérifié' : ''}`;
            av.style.color = qty > stock ? 'var(--danger)' : '';
            $('[data-price]', tr).textContent = UI.money(p.price);
            $('[data-total]', tr).textContent = UI.money(p.price * qty);
            subtotal += p.price * qty;
        });
        const discount = Math.min(Number($('[name=discount]', orderForm).value || 0), subtotal);
        const fee = mode === 'delivery' ? Number($('[name=d_fee]', orderForm).value || 0) : 0;
        $('[data-order-total]', orderForm).textContent = UI.money(subtotal - discount + fee);
        $('[data-delivery-fields]', orderForm).classList.toggle('hidden', mode !== 'delivery');
        $('[name=d_address]', orderForm).required = mode === 'delivery';
        return subtotal - discount + fee;
    }

    orderForm.addEventListener('input', recomputeOrder);
    orderForm.addEventListener('change', recomputeOrder);
    $('[data-add-line]', orderForm).addEventListener('click', () => { addLine(); recomputeOrder(); });
    lines.addEventListener('click', (e) => { if (e.target.matches('[data-remove]')) { e.target.closest('tr').remove(); recomputeOrder(); } });

    // Recherche client dans les données locales
    const cSearch = $('[data-customer-search]', orderForm);
    const cResults = $('[data-customer-results]', orderForm);
    cSearch.addEventListener('input', () => {
        const q = cSearch.value.trim().toLowerCase();
        cResults.innerHTML = '';
        if (q.length < 2 || !data) return;
        data.customers.filter((c) => (c.name || '').toLowerCase().includes(q) || (c.phone || '').includes(q)).slice(0, 8).forEach((c) => {
            const li = document.createElement('li');
            li.innerHTML = `<span><strong>${UI.esc(c.name)}</strong><br><small class="muted">${UI.esc([c.phone, c.city].filter(Boolean).join(' · '))}</small></span><button type="button" class="btn btn-sm">Choisir</button>`;
            li.querySelector('button').addEventListener('click', () => chooseCustomer(c));
            cResults.appendChild(li);
        });
    });
    function chooseCustomer(c) {
        $('[name=customer_id]', orderForm).value = c.id;
        const box = $('[data-customer-chosen]', orderForm);
        box.querySelector('span').textContent = c.name + (c.phone ? ' · ' + c.phone : '');
        box.classList.remove('hidden');
        cResults.innerHTML = ''; cSearch.value = ''; cSearch.classList.add('hidden');
        $('[data-new-customer]', orderForm).classList.add('hidden');
        if (!$('[name=d_address]', orderForm).value) $('[name=d_address]', orderForm).value = c.address || '';
        if (!$('[name=d_city]', orderForm).value) $('[name=d_city]', orderForm).value = c.city || '';
        if (!$('[name=d_phone]', orderForm).value) $('[name=d_phone]', orderForm).value = c.phone || '';
    }
    $('[data-customer-clear]', orderForm).addEventListener('click', () => {
        $('[name=customer_id]', orderForm).value = '';
        $('[data-customer-chosen]', orderForm).classList.add('hidden');
        cSearch.classList.remove('hidden');
        $('[data-new-customer]', orderForm).classList.remove('hidden');
    });

    orderForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!data) return alert('Aucune donnée locale : connectez-vous une première fois à Internet.');
        const f = orderForm;
        const sid = storeId(f);
        const mode = $('[name=mode]:checked', f).value;
        const items = $$('tr', lines).map((tr) => {
            const p = productById($('[data-product]', tr).value);
            const qty = parseInt($('[data-qty]', tr).value || '0', 10);
            return p && qty > 0 ? { product_id: p.id, quantity: qty, unit_price: p.price, name: p.name } : null;
        }).filter(Boolean);
        if (!items.length) return alert('Ajoutez au moins un produit.');
        if (mode === 'delivery' && !$('[name=d_address]', f).value.trim()) return alert('L’adresse de livraison est obligatoire.');

        const total = recomputeOrder();
        const customerId = $('[name=customer_id]', f).value;
        const newName = $('[name=c_name]', f).value.trim();
        const payload = {
            store_id: sid,
            items: items.map(({ product_id, quantity, unit_price }) => ({ product_id, quantity, unit_price })),
            direct_sale: mode === 'direct',
            discount: Number($('[name=discount]', f).value || 0),
            payment_method: $('[name=payment_method]', f).value || null,
            notes: $('[name=notes]', f).value || null,
            ordered_at: new Date().toISOString(),
        };
        const paid = $('[name=amount_paid]', f).value;
        if (paid !== '') payload.amount_paid = Number(paid);
        if (customerId) payload.customer_id = Number(customerId);
        else if (newName) payload.customer = { name: newName, phone: $('[name=c_phone]', f).value || null, address: $('[name=c_address]', f).value || null, city: $('[name=c_city]', f).value || null, client_uuid: Store.uuid() };
        if (mode === 'delivery') {
            payload.delivery = {
                address: $('[name=d_address]', f).value, city: $('[name=d_city]', f).value || null,
                contact_phone: $('[name=d_phone]', f).value || null, scheduled_date: $('[name=d_date]', f).value || null,
                fee: Number($('[name=d_fee]', f).value || 0),
            };
        }

        const who = customerId ? $('[data-customer-chosen] span', f).textContent : (newName || 'Client comptoir');
        const label = `${mode === 'direct' ? 'Vente' : 'Commande'} — ${who} — ${items.map((i) => i.quantity + ' × ' + i.name).join(', ')} — ${UI.money(total)}`;

        // Stock local diminué en attendant la vérification du serveur
        items.forEach((i) => { const p = productById(i.product_id); p.stocks[sid] = Number(p.stocks[sid] ?? 0) - i.quantity; });
        if (payload.customer) data.customers.unshift({ id: null, name: payload.customer.name, phone: payload.customer.phone, address: payload.customer.address, city: payload.customer.city });
        await saveSnapshot();
        await Sync.enqueue('order', payload, label);

        f.reset();
        lines.innerHTML = '';
        $('[data-customer-clear]', f).click();
        addLine();
        render();
        flash('Opération enregistrée sur l’appareil. Elle sera envoyée dès que la connexion sera disponible.');
    });

    /* ---------- Dépense ---------- */
    const expenseForm = $('[data-form="expense"]');
    expenseForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!data) return;
        const f = expenseForm;
        const payload = {
            store_id: storeId(f),
            spent_at: $('[name=spent_at]', f).value,
            amount: Number($('[name=amount]', f).value),
            category: $('[name=category]', f).value,
            description: $('[name=description]', f).value || null,
        };
        const label = `Dépense — ${data.expense_categories[payload.category]} — ${UI.money(payload.amount)}${payload.description ? ' — ' + payload.description : ''}`;
        await Sync.enqueue('expense', payload, label);
        f.reset();
        $('[name=spent_at]', f).value = today();
        fillSelects();
        renderQueue();
        flash('Dépense enregistrée sur l’appareil.');
    });

    /* ---------- Livraisons ---------- */
    const NEXT = { preparation: ['en_route', 'livree'], en_route: ['livree', 'echec', 'retour'], echec: ['en_route', 'retour'] };
    function renderDeliveries() {
        const box = $('[data-deliveries]', root);
        if (!data.deliveries.length) { box.innerHTML = '<p class="empty">Aucune livraison en cours.</p>'; return; }
        const canEdit = data.user.can.deliveries;
        box.innerHTML = data.deliveries.map((d) => `
            <div class="card" data-delivery="${d.id}">
                <div class="card-head">
                    <div><strong>${UI.esc(d.order_number)}</strong> — ${UI.esc(d.customer || '')}<br>
                        <small class="muted">${UI.esc(d.address)}${d.phone ? ' · <a href="tel:' + UI.esc(d.phone) + '">' + UI.esc(d.phone) + '</a>' : ''}${d.scheduled_date ? ' · prévue le ' + UI.esc(d.scheduled_date) : ''} · ${UI.esc(d.driver)}</small></div>
                    <span class="badge badge-info">${UI.esc(data.delivery_statuses[d.local_status || d.status])}${d.local_status ? ' (non synchronisé)' : ''}</span>
                </div>
                ${d.balance > 0 ? `<p>À encaisser : <strong>${UI.money(d.balance)}</strong></p>` : ''}
                ${canEdit ? `<div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center">
                    ${(NEXT[d.local_status || d.status] || []).map((s) => `<button type="button" class="btn btn-sm ${s === 'livree' ? 'btn-success' : (s === 'en_route' ? 'btn-primary' : '')}" data-set-status="${s}">${UI.esc(data.delivery_statuses[s])}</button>`).join('')}
                    <input type="text" placeholder="Motif (échec / retour)" data-reason style="flex:1;min-width:160px">
                </div>` : ''}
            </div>`).join('');
    }
    $('[data-deliveries]', root).addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-set-status]');
        if (!btn) return;
        const card = btn.closest('[data-delivery]');
        const d = data.deliveries.find((x) => x.id === Number(card.dataset.delivery));
        const status = btn.dataset.setStatus;
        const reason = $('[data-reason]', card).value.trim();
        if ((status === 'echec' || status === 'retour') && !reason) { $('[data-reason]', card).focus(); return alert('Indiquez le motif.'); }
        const payload = { delivery_id: d.id, status, reason: reason || null, expected_status: d.local_status || d.status };
        await Sync.enqueue('delivery_status', payload, `Livraison ${d.order_number} — ${data.delivery_statuses[status]}${reason ? ' — ' + reason : ''}`);
        d.local_status = status;
        if (status === 'livree' || status === 'retour') data.deliveries = data.deliveries.filter((x) => x !== d);
        await saveSnapshot();
        renderDeliveries();
        renderQueue();
        flash('Suivi de livraison enregistré sur l’appareil.');
    });

    /* ---------- Consultation ---------- */
    function renderStock() {
        const q = ($('[data-stock-search]', root).value || '').toLowerCase();
        const sid = data.user.store_id;
        const rows = data.products.filter((p) => !q || p.name.toLowerCase().includes(q) || p.reference.toLowerCase().includes(q)).slice(0, 300);
        $('[data-stock-rows]', root).innerHTML = rows.map((p) => {
            const stockText = data.stores.length > 1
                ? data.stores.map((s) => `${UI.esc(s.city)} : <strong>${Number(p.stocks[s.id] ?? 0)}</strong>`).join(' · ')
                : `<strong>${Number(p.stocks[sid] ?? 0)}</strong>`;
            return `<tr><td data-label="Produit">${UI.esc(p.name)}<br><small class="muted">${UI.esc(p.reference)}${p.category ? ' · ' + UI.esc(p.category) : ''}</small></td><td data-label="Prix" class="right num">${UI.money(p.price)}</td><td data-label="Stock" class="right">${stockText}</td></tr>`;
        }).join('') || '<tr><td colspan="3" class="empty">Aucun produit.</td></tr>';
    }
    function renderCustomers() {
        const q = ($('[data-customers-search]', root).value || '').toLowerCase();
        const rows = data.customers.filter((c) => !q || (c.name || '').toLowerCase().includes(q) || (c.phone || '').includes(q)).slice(0, 200);
        $('[data-customer-rows]', root).innerHTML = rows.map((c) => `<tr><td data-label="Client">${UI.esc(c.name)}${c.id ? '' : ' <span class="badge badge-warning">nouveau</span>'}</td><td data-label="Téléphone">${c.phone ? `<a href="tel:${UI.esc(c.phone)}">${UI.esc(c.phone)}</a>` : '—'}</td><td data-label="Adresse">${UI.esc([c.address, c.city].filter(Boolean).join(', ') || '—')}</td></tr>`).join('') || '<tr><td colspan="3" class="empty">Aucun client.</td></tr>';
    }
    $('[data-stock-search]', root).addEventListener('input', () => data && renderStock());
    $('[data-customers-search]', root).addEventListener('input', () => data && renderCustomers());

    /* ---------- File d'attente ---------- */
    async function renderQueue() {
        const ops = (await Store.queue(Sync.userId)).reverse();
        const tbody = $('[data-queue-rows]', root);
        tbody.innerHTML = ops.map((o) => {
            const [label, cls] = STATUS[o.status] || [o.status, 'badge-muted'];
            const actions = o.status === 'failed'
                ? `<button type="button" class="btn btn-sm" data-retry="${o.uuid}">Renvoyer</button> <button type="button" class="btn btn-sm" data-delete="${o.uuid}">Supprimer</button>`
                : (o.status === 'pending' ? `<button type="button" class="btn btn-sm" data-delete="${o.uuid}" title="Supprimer cette saisie">Supprimer</button>` : '');
            return `<tr>
                <td data-label="Saisie le" class="nowrap">${UI.date(o.created_at)}</td>
                <td data-label="Opération"><strong>${UI.esc(TYPE_LABELS[o.type] || o.type)}</strong><br><small>${UI.esc(o.label || '')}</small></td>
                <td data-label="État"><span class="badge ${cls}">${label}</span>${o.synced_at ? '<br><small class="muted">' + UI.date(o.synced_at) + '</small>' : ''}</td>
                <td data-label="Message">${UI.esc(o.message || '—')}</td>
                <td data-label="" class="actions">${actions}</td></tr>`;
        }).join('') || '<tr><td colspan="5" class="empty">Aucune opération enregistrée sur cet appareil.</td></tr>';
    }
    $('[data-queue-rows]', root).addEventListener('click', async (e) => {
        const retry = e.target.closest('[data-retry]');
        const del = e.target.closest('[data-delete]');
        if (retry) { await Store.updateOp(retry.dataset.retry, { status: 'pending', message: null }); await Sync.updateIndicator(); Sync.syncNow(); renderQueue(); }
        if (del && confirm('Supprimer définitivement cette saisie de l’appareil ? Elle ne sera pas envoyée.')) { await Store.removeOp(del.dataset.delete); await Sync.updateIndicator(); renderQueue(); }
    });

    /* ---------- État ---------- */
    document.addEventListener('gesma:status', (e) => {
        const s = e.detail;
        $('[data-out="online"]').innerHTML = s.online ? '<span style="color:var(--success)">● Connecté</span>' : '<span style="color:var(--danger)">● Hors connexion</span>';
        $('[data-out="last-sync"]').textContent = s.last ? UI.date(s.last) : 'Jamais';
        $('[data-out="pending"]').textContent = s.counts.pending;
        $('[data-out="failed-sub"]').textContent = s.counts.failed ? s.counts.failed + ' refusée(s) — voir la file' : '';
        $('[data-out="session"]').classList.toggle('hidden', !s.sessionExpired);
    });
    document.addEventListener('gesma:synced', () => { renderQueue(); });
    document.addEventListener('gesma:data', (e) => { data = e.detail; render(); });

    root.closest('.content').querySelector('[data-action="sync"]').addEventListener('click', () => Sync.syncNow());
    root.closest('.content').querySelector('[data-action="refresh"]').addEventListener('click', async () => {
        if (!navigator.onLine) return alert('Pas de connexion Internet.');
        try { await Sync.refreshData(true); flash('Données mises à jour.'); } catch (e) { alert('Mise à jour impossible. Vérifiez votre connexion ou reconnectez-vous.'); }
    });

    function flash(msg) {
        const div = document.createElement('div');
        div.className = 'alert alert-success';
        div.textContent = msg;
        root.parentElement.insertBefore(div, root);
        setTimeout(() => div.remove(), 5000);
    }

    if (location.hash === '#file') $('[data-tab="queue"]', root).click();
    load().then(() => Sync.updateIndicator());
})();
