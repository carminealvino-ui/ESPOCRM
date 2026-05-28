// ========================================
// VERSIONE: 1.0.3
// DATA: 2026-05-28
// FILE: custom/Espo/Custom/Resources/client/custom/src/custom-product-button.js
// ----------------------------------------
// Bottone "Crea prodotto" nella sezione Articoli del Contratto.
// ========================================

(function () {
    'use strict';

    var initialized = false;

    function isQuotePage() {
        var hash = window.location.hash || '';
        return hash.indexOf('Quote/view') !== -1 || hash.indexOf('Quote/edit') !== -1;
    }

    function injectButton() {
        if (!isQuotePage()) {
            return;
        }

        var target = document.querySelector('[data-name="itemList"]')
            || Array.from(document.querySelectorAll('.cell, .panel, .field')).find(function (el) {
                return /articoli/i.test((el.textContent || '').trim());
            });

        if (!target) {
            return;
        }

        if (target.querySelector('.custom-new-product-btn')) {
            return;
        }

        var anchor = target.querySelector('.field-label, .panel-heading, .header')
            || target;

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-primary btn-sm custom-new-product-btn';
        button.style.marginLeft = '12px';
        button.innerHTML = '<span class="fas fa-cube"></span> Crea prodotto';

        button.addEventListener('click', function () {
            if (window.Espo && Espo.Ui) {
                Espo.Ui.notify('Apertura creazione prodotto...');
            }

            window.open('#Product/create', '_blank');
        });

        anchor.appendChild(button);
    }

    function init() {
        if (initialized) {
            return;
        }

        initialized = true;

        setInterval(injectButton, 1200);
    }

    init();
})();
