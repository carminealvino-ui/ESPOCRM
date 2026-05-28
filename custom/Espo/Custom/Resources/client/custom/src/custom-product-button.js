// Pulsante «Crea prodotto» su Contratto (view + edit). Path deploy: client/custom/src/
(function () {
    'use strict';

    var BTN_CLASS = 'mec-btn-crea-prodotto';

    function isQuotePage() {
        var h = window.location.hash || '';
        return /#Quote\/(view|edit)\//i.test(h);
    }

    function openCreateProduct() {
        if (window.Espo && Espo.Ui && typeof Espo.Ui.notify === 'function') {
            Espo.Ui.notify('Apertura creazione prodotto...');
        }

        if (window.Espo && Espo.Ui && typeof Espo.Ui.openView === 'function') {
            try {
                Espo.Ui.openView({
                    scope: 'Product',
                    name: 'create',
                });
                return;
            } catch (e) {
                // fallback sotto
            }
        }

        window.location.hash = '#Product/create';
    }

    function findArticoliAnchor() {
        var byData = document.querySelector('[data-name="itemList"]');

        if (byData) {
            return byData;
        }

        var tables = document.querySelectorAll('table');

        for (var i = 0; i < tables.length; i++) {
            var text = (tables[i].textContent || '').toLowerCase();

            if (text.indexOf('prezzo di listino') !== -1 || text.indexOf('prezzo codice') !== -1) {
                var panel = tables[i].closest('.panel, .tab-pane, .field, .record-grid, .detail');

                return panel || tables[i].parentElement;
            }
        }

        var labels = document.querySelectorAll('.field-label, label, h4, .panel-heading');

        for (var j = 0; j < labels.length; j++) {
            var lbl = (labels[j].textContent || '').trim().toLowerCase();

            if (lbl === 'articoli' || lbl.indexOf('articoli') === 0) {
                return labels[j].closest('.field, .panel, .cell') || labels[j].parentElement;
            }
        }

        return null;
    }

    function injectButton() {
        if (!isQuotePage()) {
            document.querySelectorAll('.' + BTN_CLASS).forEach(function (el) {
                el.remove();
            });
            return;
        }

        if (document.querySelector('.' + BTN_CLASS)) {
            return;
        }

        var anchor = findArticoliAnchor();

        if (!anchor) {
            return;
        }

        var bar = document.createElement('div');
        bar.className = 'mec-articoli-toolbar';
        bar.style.cssText = 'margin:8px 0 12px 0; display:flex; align-items:center; gap:8px; flex-wrap:wrap;';

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-primary btn-sm ' + BTN_CLASS;
        button.innerHTML = '<span class="fas fa-cube"></span> Crea prodotto';
        button.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openCreateProduct();
        });

        bar.appendChild(button);

        var btnGroup = anchor.querySelector('.btn-group');

        if (btnGroup && btnGroup.parentNode) {
            btnGroup.parentNode.insertBefore(bar, btnGroup);
        } else if (anchor.querySelector('table')) {
            anchor.insertBefore(bar, anchor.querySelector('table'));
        } else {
            anchor.insertBefore(bar, anchor.firstChild);
        }
    }

    function boot() {
        injectButton();
        setInterval(injectButton, 800);

        var obs = new MutationObserver(function () {
            injectButton();
        });

        obs.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
