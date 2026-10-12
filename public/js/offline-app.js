/* GesMA — application hors connexion (commandes, ventes, dépenses, livraisons, consultation). */
(function () {
    'use strict';

    var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
    var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };
    var money = window.GesmaMoney;
    var esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };
    var Store = window.GesmaStore;
    var data = null;
    var outbox = [];
    var chosenCustomer = null;

    var DELIVERY_FLOW = {
        pending: ['preparing', 'in_transit'],
        preparing: ['in_transit', 'pending'],
        in_transit: ['delivered', 'failed', 'returned'],
        failed: ['in_transit', 'returned'],
        delivered: ['returned'],
        returned: []
    };
    var TYPE_LABELS = {
        'order.create': 'Commande', 'sale.create': 'Vente directe', 'expense.create': 'Dépense',
        'delivery.status': 'Livraison', 'customer.create': 'Client'
    };
    var STATUS_LABELS = { pending: 'En attente', failed: 'Échec', conflict: 'Conflit à vérifier' };
    var STATUS_BADGES = { pending: 'warning', failed: 'danger', conflict: 'danger' };

    // ---------- Chargement ----------
    function load() {
        return Promise.all([Store.bootstrap(), Store.outbox()]).then(function (r) {
            data = r[0];
            outbox = r[1];
            if (!data) {
                $('[data-not-configured]').classList.remove('hidden');
                $('[data-user-line]').textContent = 'Appareil non configuré';
                return;
            }
            $('[data-app]').hidden = false;
            $('[data-user-line]').textContent = data.user.name + ' · ' + data.user.role_label;
            $$('[data-perm]').forEach(function (b) { b.hidden = !data.user.permissions[b.getAttribute('data-perm')]; });
            fillSelects();
            renderAll();
        });
    }

    function renderAll() {
        renderOutbox();
        renderDeliveries();
        renderBrowse();
        renderOrderTotals();
        Store.history().then(renderHistory);
        Store.state().then(function (st) { window.dispatchEvent(new CustomEvent('gesma:sync-state', { detail: st })); });
    }

    function fillSelects() {
        var stores = data.stores || [];
        $$('[data-store]').forEach(function (sel) {
            sel.innerHTML = stores.map(function (s) {
                return '<option value="' + s.id + '"' + (s.id === data.user.store_id ? ' selected' : '') + '>' + esc(s.name) + '</option>';
            }).join('');
            sel.closest('[data-store-field]').hidden = stores.length <= 1;
        });
        $$('[data-payment]').forEach(function (sel) {
            sel.innerHTML = '<option value="">—</option>' + Object.keys(data.payment_methods || {}).map(function (k) {
                return '<option value="' + k + '">' + esc(data.payment_methods[k]) + '</option>';
            }).join('');
        });
        $('[data-exp-categories]').innerHTML = (data.expense_categories || []).map(function (c) {
            return '<option value="' + c.id + '">' + esc(c.name) + '</option>';
        }).join('');
        $('[data-drivers]').innerHTML = '<option value="">—</option>' + (data.drivers || []).map(function (d) {
            return '<option value="' + d.id + '">' + esc(d.name) + '</option>';
        }).join('');
        var today = new Date().toISOString().slice(0, 10);
        $('[name="spent_at"]').value = today;
        $('[name="spent_at"]').max = today;
        if (!$('[data-lines-body]').children.length) addLine();
    }

    // ---------- Stock disponible (stock local moins les commandes en attente) ----------
    function pendingQuantities(storeId) {
        var q = {};
        outbox.forEach(function (op) {
            if ((op.type === 'order.create' || op.type === 'sale.create') && op.status !== 'failed' && op.payload && +op.payload.store_id === +storeId) {
                (op.payload.items || []).forEach(function (i) { q[i.product_id] = (q[i.product_id] || 0) + i.quantity; });
            }
        });
        return q;
    }

    function stockOf(productId, storeId) {
        var row = (data.stock || []).filter(function (s) { return s.product_id === productId && +s.store_id === +storeId; })[0];
        return (row ? row.quantity : 0) - (pendingQuantities(storeId)[productId] || 0);
    }

    // ---------- Onglets ----------
    document.addEventListener('click', function (e) {
        var tab = e.target.closest('[data-tab]');
        if (tab) {
            $$('[data-tab]').forEach(function (b) { b.classList.toggle('active', b === tab); });
            $$('[data-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== tab.getAttribute('data-tab'); });
        }
        var sub = e.target.closest('[data-sub]');
        if (sub) {
            $$('[data-sub]').forEach(function (b) { b.classList.toggle('active', b === sub); });
            renderBrowse();
        }
    });

    // ---------- État de synchronisation ----------
    function renderStatus(st) {
        $('[data-online-hint]').hidden = !st.online;
        $('[data-bootstrap-at]').textContent = st.bootstrapAt ? new Date(st.bootstrapAt).toLocaleString('fr-FR') : '—';
        var err = $('[data-error-line]');
        err.hidden = !st.lastError;
        err.textContent = st.lastError || '';
        $('[data-login-link]').hidden = !st.authRequired;
    }

    window.addEventListener('gesma:sync-state', function (e) {
        renderStatus(e.detail);
        if (!e.detail.syncing) {
            Promise.all([Store.bootstrap(), Store.outbox()]).then(function (r) {
                if (r[0]) data = r[0];
                outbox = r[1];
                renderOutbox();
                renderDeliveries();
                renderBrowse();
                Store.history().then(renderHistory);
            });
        }
    });

    function renderHistory(items) {
        $('[data-history]').innerHTML = items.length ? items.map(function (h) {
            return '<li><div>' + esc(h.message) + '</div><div class="when">' + new Date(h.at).toLocaleString('fr-FR') + '</div></li>';
        }).join('') : '<li class="muted">Aucune pour le moment.</li>';
    }

    // ---------- Commande / vente ----------
    var form = $('[data-order-form]');

    function productOptions() {
        return '<option value="">— Produit —</option>' + (data.products || []).map(function (p) {
            return '<option value="' + p.id + '" data-price="' + p.price + '">' + esc(p.name) + ' — ' + esc(p.reference) + '</option>';
        }).join('');
    }

    function addLine() {
        var tr = document.createElement('tr');
        tr.innerHTML = '<td class="col-product"><select data-l-product>' + productOptions() + '</select><div class="stock-hint muted" data-l-hint></div></td>' +
            '<td class="col-qty"><input type="number" min="1" value="1" data-l-qty></td>' +
            '<td class="col-price"><input type="number" min="0" data-l-price' + (data.user.permissions.price ? '' : ' readonly') + '></td>' +
            '<td class="col-total num" data-l-total>0 F CFA</td>' +
            '<td class="col-del"><button type="button" class="btn btn-ghost btn-sm" data-l-remove aria-label="Retirer">✕</button></td>';
        $('[data-lines-body]').appendChild(tr);
    }

    function orderStore() { return +$('[data-store]', form).value; }

    function renderOrderTotals() {
        if (!data) return;
        var subtotal = 0;
        var warnings = [];
        $$('[data-lines-body] tr').forEach(function (tr) {
            var pid = +$('[data-l-product]', tr).value;
            var qty = +$('[data-l-qty]', tr).value || 0;
            var price = +$('[data-l-price]', tr).value || 0;
            subtotal += qty * price;
            $('[data-l-total]', tr).textContent = money(qty * price);
            var hint = $('[data-l-hint]', tr);
            if (pid) {
                var available = stockOf(pid, orderStore());
                hint.textContent = 'Stock local estimé : ' + available;
                hint.className = 'stock-hint ' + (available < qty ? 'text-danger' : 'muted');
                if (available < qty) warnings.push($('[data-l-product]', tr).selectedOptions[0].textContent.split(' — ')[0]);
            } else hint.textContent = '';
        });
        var delivery = $('[data-delivery-toggle]').checked && !isSale();
        var fee = delivery ? (+form.d_fee.value || 0) : 0;
        var total = Math.max(0, subtotal - (+form.discount.value || 0) + fee);
        $('[data-order-total]').textContent = money(total);
        var w = $('[data-stock-warning]');
        w.classList.toggle('hidden', !warnings.length);
        w.textContent = warnings.length ? 'Stock local insuffisant pour : ' + warnings.join(', ') + '. La disponibilité sera vérifiée à la synchronisation (conflit possible).' : '';
        return total;
    }

    function isSale() { return form.type.value === 'sale'; }

    form.addEventListener('change', function (e) {
        if (e.target.matches('[data-l-product]')) {
            var opt = e.target.selectedOptions[0];
            $('[data-l-price]', e.target.closest('tr')).value = opt && opt.value ? opt.getAttribute('data-price') : '';
        }
        if (e.target.name === 'type') {
            $('[data-delivery-toggle-wrap]').hidden = isSale();
            if (isSale()) { $('[data-delivery-toggle]').checked = false; $('[data-delivery-fields]').hidden = true; }
        }
        if (e.target.matches('[data-delivery-toggle]')) $('[data-delivery-fields]').hidden = !e.target.checked;
        renderOrderTotals();
    });
    form.addEventListener('input', renderOrderTotals);
    form.addEventListener('click', function (e) {
        if (e.target.closest('[data-add-line]')) { e.preventDefault(); addLine(); }
        if (e.target.closest('[data-l-remove]')) {
            e.preventDefault();
            if ($$('[data-lines-body] tr').length > 1) e.target.closest('tr').remove();
            renderOrderTotals();
        }
    });

    // Recherche de client dans les données locales
    $('[data-cust-search]').addEventListener('input', function (e) {
        var q = e.target.value.trim().toLowerCase();
        var box = $('[data-cust-results]');
        if (q.length < 2) { box.innerHTML = ''; return; }
        var digits = q.replace(/\D/g, '');
        var found = (data.customers || []).filter(function (c) {
            return (c.name || '').toLowerCase().indexOf(q) >= 0 || (digits.length >= 4 && (c.phone || '').indexOf(digits) >= 0);
        }).slice(0, 10);
        box.innerHTML = found.map(function (c, i) {
            return '<button type="button" class="btn btn-secondary btn-sm" style="display:block;width:100%;text-align:left;margin-bottom:.25rem" data-cust-pick="' + i + '">' +
                esc(c.name) + (c.phone ? ' · ' + esc(c.phone) : '') + (c.city ? ' · ' + esc(c.city) : '') + '</button>';
        }).join('') || '<div class="muted small">Aucun client trouvé : saisissez un nouveau client ci-dessous.</div>';
        box.onclick = function (ev) {
            var b = ev.target.closest('[data-cust-pick]');
            if (!b) return;
            chooseCustomer(found[+b.getAttribute('data-cust-pick')]);
        };
    });

    function chooseCustomer(c) {
        chosenCustomer = c;
        var chosen = $('[data-cust-chosen]');
        $('[data-cust-results]').innerHTML = '';
        $('[data-cust-search]').value = '';
        chosen.classList.toggle('hidden', !c);
        $('[data-cust-new]').hidden = !!c;
        if (c) {
            chosen.innerHTML = 'Client : <strong>' + esc(c.name) + '</strong>' + (c.phone ? ' · ' + esc(c.phone) : '') + ' <button type="button" class="btn btn-ghost btn-sm" data-cust-clear>Changer</button>';
            $('[data-cust-clear]', chosen).onclick = function () { chooseCustomer(null); };
            if (!form.d_address.value && c.address) form.d_address.value = c.address;
            if (!form.d_city.value && c.city) form.d_city.value = c.city;
            if (!form.d_phone.value && c.phone) form.d_phone.value = c.phone;
        }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var items = $$('[data-lines-body] tr').map(function (tr) {
            var item = { product_id: +$('[data-l-product]', tr).value, quantity: +$('[data-l-qty]', tr).value || 0 };
            if (data.user.permissions.price) item.unit_price = +$('[data-l-price]', tr).value || 0;
            return item;
        }).filter(function (i) { return i.product_id && i.quantity > 0; });
        if (!items.length) { alert('Ajoutez au moins un produit.'); return; }

        var sale = isSale();
        var payload = {
            uuid: Store.uuid(),
            store_id: orderStore(),
            items: items,
            discount: +form.discount.value || 0,
            amount_paid: +form.amount_paid.value || 0,
            payment_method: form.payment_method.value || null,
            requires_delivery: !sale && $('[data-delivery-toggle]').checked,
            notes: form.notes.value || null,
            ordered_at: new Date().toISOString()
        };
        if (chosenCustomer) {
            payload.customer_id = chosenCustomer.id;
        } else if (form.c_name.value.trim()) {
            payload.customer = { uuid: Store.uuid(), name: form.c_name.value.trim(), phone: form.c_phone.value || null, city: form.c_city.value || null, address: form.c_address.value || null };
        }
        if (payload.requires_delivery) {
            if (!form.d_address.value.trim()) { alert("Indiquez l'adresse de livraison."); return; }
            payload.delivery = {
                address: form.d_address.value.trim(), city: form.d_city.value || null, contact_phone: form.d_phone.value || null,
                contact_name: chosenCustomer ? chosenCustomer.name : (form.c_name.value || null),
                scheduled_date: form.d_date.value || null, fee: +form.d_fee.value || 0, driver_id: +form.d_driver.value || null
            };
        }
        var total = renderOrderTotals();
        var who = chosenCustomer ? chosenCustomer.name : (payload.customer ? payload.customer.name : 'client comptoir');
        Store.queue(sale ? 'sale.create' : 'order.create', payload, (sale ? 'Vente' : 'Commande') + ' · ' + who + ' · ' + money(total)).then(function () {
            form.reset();
            chooseCustomer(null);
            $('[data-lines-body]').innerHTML = '';
            fillSelects();
            $('[data-delivery-fields]').hidden = true;
            $('[data-delivery-toggle-wrap]').hidden = false;
            return Store.outbox();
        }).then(function (ops) {
            outbox = ops;
            renderAll();
            alert('Enregistré sur l\'appareil. Envoi automatique au retour de la connexion.');
        });
    });

    // ---------- Dépense ----------
    var expenseForm = $('[data-expense-form]');
    expenseForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var f = expenseForm;
        var payload = {
            uuid: Store.uuid(),
            spent_at: f.spent_at.value,
            amount: +f.amount.value,
            expense_category_id: +f.expense_category_id.value,
            store_id: +f.store_id.value,
            description: f.description.value.trim(),
            payment_method: f.payment_method.value || null
        };
        var cat = f.expense_category_id.selectedOptions[0];
        Store.queue('expense.create', payload, 'Dépense · ' + (cat ? cat.textContent : '') + ' · ' + money(payload.amount)).then(function () {
            f.reset();
            fillSelects();
            return Store.outbox();
        }).then(function (ops) {
            outbox = ops;
            renderOutbox();
            alert('Dépense enregistrée sur l\'appareil.');
        });
    });

    // ---------- Livraisons ----------
    function deliveryLocalStatus(d) {
        var ops = outbox.filter(function (o) { return o.type === 'delivery.status' && o.payload && o.payload.delivery_id === d.id && o.status === 'pending'; });
        return ops.length ? ops[ops.length - 1].payload.status : d.status;
    }

    function renderDeliveries() {
        if (!data) return;
        var q = ($('[data-deliv-search]').value || '').toLowerCase();
        var list = (data.deliveries || []).filter(function (d) {
            return !q || [d.order_number, d.customer, d.address].join(' ').toLowerCase().indexOf(q) >= 0;
        });
        var canManage = data.user.permissions.deliveries;
        $('[data-deliveries]').innerHTML = list.length ? list.map(function (d) {
            var status = deliveryLocalStatus(d);
            var changed = status !== d.status;
            var actions = canManage ? (DELIVERY_FLOW[status] || []).map(function (s) {
                return '<button type="button" class="btn btn-sm ' + (s === 'failed' || s === 'returned' ? 'btn-danger' : (s === 'delivered' ? 'btn-success' : 'btn-secondary')) + '" data-deliv="' + d.id + '" data-to="' + s + '">' + esc(data.delivery_statuses[s]) + '</button>';
            }).join('') : '';
            return '<div class="delivery-item"><div class="op-head" style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap">' +
                '<strong>' + esc(d.order_number) + ' · ' + esc(d.customer || '') + '</strong>' +
                '<span class="badge ' + (changed ? 'badge-warning' : 'badge-info') + '">' + esc(data.delivery_statuses[status]) + (changed ? ' (en attente d\'envoi)' : '') + '</span></div>' +
                '<div class="small">' + esc(d.address) + (d.contact_phone ? ' · <a href="tel:' + esc(d.contact_phone) + '">' + esc(d.contact_phone) + '</a>' : '') + '</div>' +
                '<div class="small muted">' + (d.scheduled_date ? 'Prévue le ' + new Date(d.scheduled_date).toLocaleDateString('fr-FR') : 'Date à définir') + (d.driver ? ' · ' + esc(d.driver) : '') +
                ' · Reste à encaisser : ' + money(d.balance) + '</div>' +
                '<div class="actions">' + actions + '</div></div>';
        }).join('') : '<div class="empty card">Aucune livraison en cours sur cet appareil.</div>';
    }

    $('[data-deliv-search]').addEventListener('input', renderDeliveries);
    $('[data-deliveries]').addEventListener('click', function (e) {
        var b = e.target.closest('[data-deliv]');
        if (!b) return;
        var id = +b.getAttribute('data-deliv');
        var to = b.getAttribute('data-to');
        var d = data.deliveries.filter(function (x) { return x.id === id; })[0];
        var reason = null;
        if (to === 'failed' || to === 'returned') {
            reason = prompt('Motif (' + data.delivery_statuses[to] + ') :');
            if (!reason) return;
        }
        var expected = deliveryLocalStatus(d);
        Store.queue('delivery.status', { delivery_id: id, status: to, expected_status: expected, reason: reason },
            'Livraison ' + d.order_number + ' → ' + data.delivery_statuses[to]).then(function () {
            return Store.outbox();
        }).then(function (ops) { outbox = ops; renderDeliveries(); renderOutbox(); });
    });

    // ---------- Consultation ----------
    function renderBrowse() {
        if (!data) return;
        var sub = ($('[data-sub].active') || {}).getAttribute ? $('[data-sub].active').getAttribute('data-sub') : 'products';
        var q = ($('[data-browse-search]').value || '').toLowerCase();
        var match = function (s) { return !q || s.toLowerCase().indexOf(q) >= 0; };
        var stores = data.stores || [];
        var html = '';
        if (sub === 'products') {
            var rows = (data.products || []).filter(function (p) { return match([p.name, p.reference, p.category || ''].join(' ')); }).slice(0, 200);
            html = '<table class="table"><thead><tr><th>Produit</th><th class="num">Prix</th>' + stores.map(function (s) { return '<th class="num">' + esc(s.name) + '</th>'; }).join('') + '</tr></thead><tbody>' +
                rows.map(function (p) {
                    return '<tr><td><strong>' + esc(p.name) + '</strong><div class="muted small mono">' + esc(p.reference) + '</div></td><td class="num">' + money(p.price) + '</td>' +
                        stores.map(function (s) { var n = stockOf(p.id, s.id); return '<td class="num' + (n <= 0 ? ' text-danger' : '') + '">' + n + '</td>'; }).join('') + '</tr>';
                }).join('') + '</tbody></table>';
        } else if (sub === 'customers') {
            var cs = (data.customers || []).filter(function (c) { return match([c.name, c.phone || '', c.city || ''].join(' ')); }).slice(0, 200);
            html = '<table class="table"><thead><tr><th>Client</th><th>Téléphone</th><th>Ville</th><th>Adresse</th></tr></thead><tbody>' +
                cs.map(function (c) { return '<tr><td>' + esc(c.name) + '</td><td>' + (c.phone ? '<a href="tel:' + esc(c.phone) + '">' + esc(c.phone) + '</a>' : '') + '</td><td>' + esc(c.city) + '</td><td>' + esc(c.address) + '</td></tr>'; }).join('') + '</tbody></table>';
        } else {
            var os = (data.recent_orders || []).filter(function (o) { return match([o.number, o.customer || '', o.status].join(' ')); });
            html = '<table class="table"><thead><tr><th>Commande</th><th>Date</th><th>Client</th><th>Statut</th><th class="num">Total</th></tr></thead><tbody>' +
                os.map(function (o) { return '<tr><td class="mono">' + esc(o.number) + '</td><td>' + new Date(o.ordered_at).toLocaleString('fr-FR') + '</td><td>' + esc(o.customer) + '</td><td>' + esc(o.status) + '</td><td class="num">' + money(o.total) + '</td></tr>'; }).join('') + '</tbody></table>';
        }
        $('[data-browse]').innerHTML = html;
    }
    $('[data-browse-search]').addEventListener('input', renderBrowse);

    // ---------- File d'attente ----------
    function renderOutbox() {
        $('[data-outbox-count]').textContent = outbox.length;
        $('[data-outbox]').innerHTML = outbox.length ? outbox.map(function (op) {
            return '<div class="op"><div class="op-head"><strong>' + esc(op.summary || TYPE_LABELS[op.type] || op.type) + '</strong>' +
                '<span class="badge badge-' + STATUS_BADGES[op.status] + '">' + STATUS_LABELS[op.status] + '</span></div>' +
                '<div class="small muted">Saisie le ' + new Date(op.created_at).toLocaleString('fr-FR') + ' · ' + esc(TYPE_LABELS[op.type] || op.type) + '</div>' +
                (op.message ? '<div class="small ' + (op.status === 'pending' ? '' : 'text-danger') + '" style="margin-top:.3rem">' + esc(op.message) + '</div>' : '') +
                (op.status === 'conflict' ? '<div class="small muted">Le responsable du magasin peut appliquer ou rejeter cette opération depuis la page Synchronisation.</div>' : '') +
                '<div style="margin-top:.4rem;display:flex;gap:.4rem">' +
                (op.status === 'failed' ? '<button class="btn btn-sm btn-secondary" data-retry="' + op.uuid + '">Réessayer</button>' : '') +
                (op.status !== 'pending' ? '<button class="btn btn-sm btn-ghost" data-remove="' + op.uuid + '">Retirer de la liste</button>' :
                    '<button class="btn btn-sm btn-ghost" data-remove="' + op.uuid + '" data-confirm-remove>Annuler la saisie</button>') +
                '</div></div>';
        }).join('') : '<div class="empty card">Aucune opération en attente. Tout est synchronisé.</div>';
    }

    $('[data-outbox]').addEventListener('click', function (e) {
        var r = e.target.closest('[data-retry]');
        if (r) Store.retryOperation(r.getAttribute('data-retry'));
        var d = e.target.closest('[data-remove]');
        if (d) {
            if (d.hasAttribute('data-confirm-remove') && !confirm('Annuler cette saisie ? Elle ne sera pas envoyée au serveur.')) return;
            Store.removeOperation(d.getAttribute('data-remove'));
        }
    });

    load().then(function () {
        if (navigator.onLine && data) Store.sync();
    });
})();
