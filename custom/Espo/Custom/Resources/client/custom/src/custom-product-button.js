// Pulsante «Crea prodotto» su scheda Contratto (sempre visibile in testata + sezione Articoli).
(function () {
    'use strict';

    var BTN_CLASS = 'mec-btn-crea-prodotto';

    function isQuotePage() {
        var h = (window.location.hash || '').toLowerCase();
        var p = (window.location.pathname || '').toLowerCase();

        if (h.indexOf('quote') !== -1 && (h.indexOf('/view/') !== -1 || h.indexOf('/edit/') !== -1)) {
            return true;
        }

        return p.indexOf('quote') !== -1;
    }

    function openCreateProduct() {
        if (window.Espo && Espo.Ui && typeof Espo.Ui.notify === 'function') {
            Espo.Ui.notify('Creazione prodotto...');
        }

        window.location.hash = '#Product/create';
    }

    function makeButton() {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-primary btn-sm ' + BTN_CLASS;
        button.style.marginLeft = '8px';
        button.innerHTML = '<span class="fas fa-cube"></span> Crea prodotto';
        button.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openCreateProduct();
        });

        return button;
    }

    /** In testata pagina, accanto a Modifica — sempre visibile */
    function injectInPageHeader() {
        if (!isQuotePage()) {
            return;
        }

        var existing = document.querySelector('.' + BTN_CLASS + '-header');

        if (existing) {
            return;
        }

        var headerActions = document.querySelector(
            '.header-buttons, .page-header .btn-group, .detail-button-container, ' +
            '.record-header .btn-group, .page-header .actions-panel, .header-row .btn-group'
        );

        if (!headerActions) {
            var editBtn = document.querySelector(
                '.btn[data-action="edit"], a.btn[data-action="edit"], ' +
                'button[data-action="edit"], a.action[data-action="edit"]'
            );

            if (!editBtn) {
                var candidates = document.querySelectorAll('.page-header button, .page-header a.btn, .header-row button, .header-row a.btn');

                for (var c = 0; c < candidates.length; c++) {
                    var label = (candidates[c].textContent || '').trim();

                    if (label === 'Modifica' || label === 'Edit') {
                        editBtn = candidates[c];
                        break;
                    }
                }
            }

            if (editBtn && editBtn.parentNode) {
                headerActions = editBtn.parentNode;
            }
        }

        if (!headerActions) {
            return;
        }

        var button = makeButton();
        button.classList.add(BTN_CLASS + '-header');
        headerActions.appendChild(button);
    }

    function findArticoliAnchor() {
        var byData = document.querySelector('[data-name="itemList"]');

        if (byData) {
            return byData;
        }

        var tables = document.querySelectorAll('table');

        for (var i = 0; i < tables.length; i++) {
            var text = (tables[i].textContent || '').toLowerCase();

            if (text.indexOf('prezzo codice') !== -1 || text.indexOf('prezzo di listino') !== -1) {
                return tables[i].closest('.panel, .tab-pane, .field') || tables[i].parentElement;
            }
        }

        return null;
    }

    function injectInArticoli() {
        if (!isQuotePage()) {
            return;
        }

        if (document.querySelector('.' + BTN_CLASS + '-articoli')) {
            return;
        }

        var anchor = findArticoliAnchor();

        if (!anchor) {
            return;
        }

        var button = makeButton();
        button.classList.add(BTN_CLASS + '-articoli');

        var btnGroup = anchor.querySelector('.btn-group');

        if (btnGroup && btnGroup.parentNode) {
            btnGroup.parentNode.insertBefore(button, btnGroup);
        } else {
            anchor.insertBefore(button, anchor.firstChild);
        }
    }

    function injectAll() {
        if (!isQuotePage()) {
            document.querySelectorAll('.' + BTN_CLASS).forEach(function (el) {
                el.remove();
            });
            return;
        }

        injectInPageHeader();
        injectInArticoli();
    }

    function boot() {
        injectAll();
        setInterval(injectAll, 600);

        new MutationObserver(injectAll).observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
