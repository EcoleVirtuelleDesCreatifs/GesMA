/* GesMA — comportements de l'interface (sans dépendance). */
(function () {
    'use strict';

    var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
    var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };
    var fmt = new Intl.NumberFormat('fr-FR');
    var money = function (v) { return fmt.format(Math.round(v || 0)).replace(/ | /g, ' ') + ' F CFA'; };
    window.GesmaMoney = money;

    // ---------- Service worker (application installable + hors connexion) ----------
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
        });
    }

    // ---------- Menu mobile ----------
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-nav-toggle]')) document.body.classList.toggle('nav-open');
        if (e.target.closest('[data-nav-close]')) document.body.classList.remove('nav-open');
    });

    // ---------- Confirmation des actions ----------
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var msg = form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) e.preventDefault();
    }, true);

    // ---------- Indicateur de connexion et de synchronisation ----------
    function timeAgo(ts) {
        if (!ts) return 'jamais';
        var s = Math.round((Date.now() - ts) / 1000);
        if (s < 60) return "à l'instant";
        if (s < 3600) return 'il y a ' + Math.round(s / 60) + ' min';
        if (s < 86400) return 'il y a ' + Math.round(s / 3600) + ' h';
        return new Date(ts).toLocaleString('fr-FR');
    }
    window.GesmaTimeAgo = timeAgo;

    function renderState(st) {
        document.body.classList.toggle('is-offline', !st.online);
        var ind = $('[data-sync-indicator]');
        if (ind) {
            ind.classList.toggle('offline', !st.online);
            ind.classList.toggle('pending', st.online && st.pending > 0);
            var label = !st.online ? 'Hors connexion' : (st.syncing ? 'Synchronisation…' : 'En ligne · ' + timeAgo(st.lastSync));
            $('[data-sync-label]', ind).textContent = label;
            var p = $('[data-sync-pending]', ind);
            p.textContent = st.pending + ' en attente';
            p.classList.toggle('hidden', !st.pending);
            var f = $('[data-sync-failed]', ind);
            f.textContent = (st.failed + st.conflict) + ' à vérifier';
            f.classList.toggle('hidden', !(st.failed + st.conflict));
            ind.title = 'Dernière synchronisation : ' + timeAgo(st.lastSync) + (st.lastError ? '\n' + st.lastError : '');
        }
        var navCount = $('[data-sync-nav-count]');
        if (navCount) {
            var n = st.pending + st.failed + st.conflict;
            navCount.textContent = n;
            navCount.classList.toggle('hidden', !n);
        }
        $$('[data-sync-panel]').forEach(function (el) { renderPanel(el, st); });
    }

    function renderPanel(el, st) {
        var set = function (k, v) { var t = $('[data-' + k + ']', el); if (t) t.textContent = v; };
        set('st-connection', st.online ? 'Connecté' : 'Hors connexion');
        set('st-last', timeAgo(st.lastSync));
        set('st-pending', st.pending);
        set('st-failed', st.failed + st.conflict);
        set('st-error', st.lastError || '—');
    }

    window.addEventListener('gesma:sync-state', function (e) { renderState(e.detail); });

    if (window.GesmaStore && document.querySelector('meta[name="gesma-user"]')) {
        GesmaStore.state().then(renderState);
        if (navigator.onLine) GesmaStore.refreshIfStale();
        setInterval(function () { if (navigator.onLine) GesmaStore.refreshIfStale(); }, 60000);
        setInterval(function () { GesmaStore.state().then(renderState); }, 30000);
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-sync-now]');
        if (btn && window.GesmaStore) {
            e.preventDefault();
            btn.disabled = true;
            GesmaStore.sync().then(function () { btn.disabled = false; });
        }
    });

    // ---------- Déconnexion : ne pas perdre les opérations non synchronisées ----------
    var logout = $('[data-logout-form]');
    if (logout && window.GesmaStore) {
        logout.addEventListener('submit', function (e) {
            if (logout.dataset.confirmed) return;
            e.preventDefault();
            var go = function () { logout.dataset.confirmed = '1'; logout.submit(); };
            var check = function () {
                return GesmaStore.state().then(function (st) {
                    var n = st.pending + st.failed + st.conflict;
                    if (!n || window.confirm(n + " opération(s) enregistrée(s) hors connexion n'ont pas été synchronisées et seront effacées de cet appareil. Se déconnecter quand même ?")) go();
                });
            };
            (navigator.onLine ? GesmaStore.sync() : Promise.resolve()).then(check, check);
        });
    }

    // ---------- Filtre de période ----------
    $$('[data-period-select]').forEach(function (sel) {
        var toggle = function () {
            $$('[data-custom-dates]', sel.form).forEach(function (el) { el.hidden = sel.value !== 'custom'; });
        };
        sel.addEventListener('change', toggle);
    });

    // ---------- Affichage conditionnel ----------
    $$('[data-toggle-target]').forEach(function (input) {
        var target = $(input.getAttribute('data-toggle-target'));
        var update = function () { if (target) target.hidden = !input.checked; };
        input.addEventListener('change', update);
        update();
    });

    // ---------- Éditeur de lignes (commandes, devis, factures, transferts) ----------
    $$('[data-lines]').forEach(function (wrap) {
        var body = $('tbody', wrap);
        var tpl = $(wrap.getAttribute('data-template'));
        var storeSelect = wrap.getAttribute('data-store-select') ? $(wrap.getAttribute('data-store-select')) : null;
        var index = $$('tr', body).length;

        function storeId() {
            return storeSelect ? storeSelect.value : wrap.getAttribute('data-store-id');
        }

        function refreshRow(tr, setPrice) {
            var sel = $('[data-product]', tr);
            var opt = sel && sel.selectedOptions[0];
            var price = $('[data-price]', tr);
            if (opt && opt.value && setPrice && price) price.value = opt.getAttribute('data-price') || 0;
            var hint = $('[data-stock-hint]', tr);
            if (hint) {
                if (opt && opt.value) {
                    var stocks = JSON.parse(opt.getAttribute('data-stocks') || '{}');
                    var qty = stocks[storeId()] || 0;
                    var wanted = parseInt(($('[data-qty]', tr) || {}).value || '0', 10);
                    hint.textContent = 'Stock : ' + qty;
                    hint.className = 'stock-hint ' + (qty < wanted ? 'text-danger' : 'muted');
                } else hint.textContent = '';
            }
            var designation = $('[data-designation]', tr);
            if (designation && opt && opt.value && setPrice && !designation.value) designation.value = opt.textContent.trim().replace(/\s+—.*$/, '');
        }

        function totals() {
            var subtotal = 0;
            $$('tr', body).forEach(function (tr) {
                var q = parseInt(($('[data-qty]', tr) || {}).value || '0', 10) || 0;
                var p = parseInt(($('[data-price]', tr) || {}).value || '0', 10) || 0;
                var line = q * p;
                subtotal += line;
                var cell = $('[data-line-total]', tr);
                if (cell) cell.textContent = money(line);
            });
            var form = wrap.closest('form');
            var discount = parseInt((($('[data-discount]', form) || {}).value) || '0', 10) || 0;
            var feeInput = $('[data-fee]', form);
            var feeActive = feeInput && !(feeInput.closest('[hidden]'));
            var fee = feeActive ? (parseInt(feeInput.value || '0', 10) || 0) : 0;
            var set = function (k, v) { var el = $('[data-total-' + k + ']', form); if (el) el.textContent = money(v); };
            set('subtotal', subtotal);
            set('discount', discount);
            set('fee', fee);
            set('grand', Math.max(0, subtotal - discount + fee));
        }

        function addRow() {
            var html = tpl.innerHTML.replace(/__i__/g, index++);
            var tmp = document.createElement('tbody');
            tmp.innerHTML = html.trim();
            var tr = tmp.firstElementChild;
            body.appendChild(tr);
            var first = $('select, input', tr);
            if (first) first.focus();
            totals();
        }

        wrap.addEventListener('change', function (e) {
            var tr = e.target.closest('tr');
            if (e.target.matches('[data-product]')) refreshRow(tr, true);
            if (e.target.matches('[data-qty]')) refreshRow(tr, false);
            totals();
        });
        wrap.addEventListener('input', totals);
        wrap.addEventListener('click', function (e) {
            if (e.target.closest('[data-remove-line]')) {
                e.preventDefault();
                var rows = $$('tr', body);
                if (rows.length > 1) e.target.closest('tr').remove(); else $$('input, select', rows[0]).forEach(function (i) { i.value = i.matches('[data-qty]') ? 1 : ''; });
                totals();
            }
        });
        var add = $('[data-add-line]', wrap.closest('form') || document);
        if (add) add.addEventListener('click', function (e) { e.preventDefault(); addRow(); });
        var form = wrap.closest('form');
        if (form) form.addEventListener('input', function (e) { if (e.target.matches('[data-discount], [data-fee]')) totals(); });
        if (form) form.addEventListener('change', function (e) { if (e.target.matches('[data-toggle-target]')) totals(); });
        if (storeSelect) storeSelect.addEventListener('change', function () { $$('tr', body).forEach(function (tr) { refreshRow(tr, false); }); });
        if (!$$('tr', body).length) addRow();
        $$('tr', body).forEach(function (tr) { refreshRow(tr, false); });
        totals();
    });

    // ---------- Recherche rapide de client ----------
    $$('[data-customer-picker]').forEach(function (box) {
        var input = $('[data-customer-query]', box);
        var hidden = $('[data-customer-id]', box);
        var list = $('[data-customer-results]', box);
        var selected = $('[data-customer-selected]', box);
        var newFields = $('[data-customer-new]', box);
        var timer;

        function choose(c) {
            hidden.value = c ? c.id : '';
            if (selected) {
                selected.hidden = !c;
                if (c) $('[data-customer-label]', selected).textContent = c.name + (c.phone ? ' · ' + c.phone : '') + (c.city ? ' · ' + c.city : '');
            }
            if (newFields) newFields.hidden = !!c;
            input.closest('.field').hidden = !!c;
            list.innerHTML = '';
            list.hidden = true;
            var addr = document.querySelector('[name="delivery[address]"]');
            if (c && addr && !addr.value && c.address) addr.value = c.address;
            var city = document.querySelector('[name="delivery[city]"]');
            if (c && city && !city.value && c.city) city.value = c.city;
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { list.hidden = true; return; }
            timer = setTimeout(function () {
                fetch(box.getAttribute('data-url') + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (rows) {
                        list.innerHTML = '';
                        rows.forEach(function (c) {
                            var b = document.createElement('button');
                            b.type = 'button';
                            b.className = 'btn btn-secondary btn-sm';
                            b.style.cssText = 'display:block;width:100%;text-align:left;margin-bottom:.25rem;white-space:normal';
                            b.textContent = c.name + (c.phone ? ' · ' + c.phone : '') + (c.city ? ' · ' + c.city : '');
                            b.addEventListener('click', function () { choose(c); });
                            list.appendChild(b);
                        });
                        if (!rows.length) list.innerHTML = '<div class="muted small">Aucun client trouvé' + (newFields ? ' — renseignez un nouveau client ci-dessous.' : '.') + '</div>';
                        list.hidden = false;
                    }).catch(function () {});
            }, 250);
        });
        var change = $('[data-customer-change]', box);
        if (change) change.addEventListener('click', function (e) { e.preventDefault(); choose(null); input.focus(); });
    });
})();
