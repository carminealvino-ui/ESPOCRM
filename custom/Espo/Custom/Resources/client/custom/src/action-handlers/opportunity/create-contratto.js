// ========================================
// VERSIONE: 1.0.1
// DATA: 2026-05-23
// AUTORE: CARMINE ALVINO + IA
// FILE: client/custom/src/action-handlers/opportunity/create-contratto.js
// ========================================
//
// OBIETTIVO:
// Mostrare ed eseguire Crea Contratto solo quando
// Opportunity.stage = Closed Won.
// ========================================

/* global define, Espo */

define('custom:action-handlers/opportunity/create-contratto', ['action-handler'], function (Dep) {

    return class extends Dep {

        initCreateContratto() {
            this.listenTo(this.view.model, 'change:stage', () => {
                const header = this.view.getHeaderView?.();

                if (header) {
                    header.reRender();
                }
            });
        }

        isCreateContrattoVisible() {
            return this.view.model.get('stage') === 'Closed Won';
        }

        async createContratto() {
            if (!this.isCreateContrattoVisible()) {
                return;
            }

            this.view.disableMenuItem('createContratto');

            let result;

            try {
                result = await Espo.Ajax.postRequest(
                    'Opportunity/action/createContratto',
                    {
                        id: this.view.model.id
                    }
                );
            } catch (e) {
                this.view.enableMenuItem('createContratto');
                throw e;
            }

            this.view.enableMenuItem('createContratto');

            if (result && result.quoteId) {
                window.location.hash = '#Quote/view/' + result.quoteId;
            }
        }
    };
});
