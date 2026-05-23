from pathlib import Path
import json

CLIENT_DEFS = {
    "controller": "controllers/record",
    "views": {"detail": "crm:views/opportunity/detail"},
    "recordViews": {
        "detail": "custom:views/opportunity/record/detail",
        "edit": "crm:views/opportunity/record/edit",
        "editSmall": "crm:views/opportunity/record/edit-small",
        "list": "crm:views/opportunity/record/list",
        "kanban": "crm:views/opportunity/record/kanban",
    },
    "buttonList": [
        "__APPEND__",
        {
            "name": "createContratto",
            "label": "Crea Contratto",
            "style": "primary",
            "action": "createContratto",
            "hidden": True,
        },
    ],
}

DETAIL_JS = r"""// ========================================
// VERSIONE: 1.2.0
// DATA: 2026-05-23
// AUTORE: CARMINE ALVINO + IA
// FILE: client/custom/src/views/opportunity/record/detail.js
// ========================================
//
// FIX 1.2.0
// I pulsanti header Opportunity sono su buttonList (addButton /
// showActionItem), NON su addMenuItem del MainView.
// clientDefs.buttonList registra il bottone; la vista lo mostra
// solo su opportunita concluse positivamente.
// ========================================

/* global define, Espo */

define('custom:views/opportunity/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            if (Dep.prototype.setup) {
                Dep.prototype.setup.apply(this, arguments);
            }

            this.updateCreateContrattoButton();

            this.listenTo(this.model, 'change:stage change:probability sync', function () {
                this.updateCreateContrattoButton();
            });
        },

        afterRender: function () {
            if (Dep.prototype.afterRender) {
                Dep.prototype.afterRender.apply(this, arguments);
            }

            this.updateCreateContrattoButton();
        },

        isClosedWon: function () {
            var stage = this.model.get('stage');

            if (stage === 'Closed Won' || stage === 'Chiuso Positivamente') {
                return true;
            }

            if (stage === 'Closed Lost' || stage === 'Chiusa persa' || stage === 'Chiuso Negativamente') {
                return false;
            }

            var probability = this.model.get('probability');

            return probability === 100 || probability === '100';
        },

        updateCreateContrattoButton: function () {
            if (!this.showActionItem || !this.hideActionItem) {
                return;
            }

            if (this.isClosedWon()) {
                this.showActionItem('createContratto');

                return;
            }

            this.hideActionItem('createContratto');
        },

        actionCreateContratto: function () {
            if (!this.isClosedWon()) {
                Espo.Ui.warning('Disponibile solo su opportunita concluse positivamente.');

                return;
            }

            var self = this;

            this.disableActionItem('createContratto');

            Espo.Ajax.postRequest(
                'Opportunity/action/createContratto',
                {
                    id: this.model.id
                }
            ).then(function (result) {
                self.enableActionItem('createContratto');

                if (result && result.quoteId) {
                    window.location.hash = '#Quote/view/' + result.quoteId;
                }
            }).catch(function (e) {
                self.enableActionItem('createContratto');
                throw e;
            });
        }
    });
});
"""

paths = [
    Path("/workspace/client/custom/src/views/opportunity/record/detail.js"),
    Path("/workspace/custom/Espo/Custom/Resources/client/custom/src/views/opportunity/record/detail.js"),
    Path("/workspace/custom/Espo/Custom/Resources/client/views/opportunity/record/detail.js"),
]

Path("/workspace/custom/Espo/Custom/Resources/metadata/clientDefs/Opportunity.json").write_text(
    json.dumps(CLIENT_DEFS, indent=4, ensure_ascii=False) + "\n"
)

for p in paths:
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(DETAIL_JS)

backup = Path("/workspace/backup/hooks_cleanup")
backup.mkdir(parents=True, exist_ok=True)
(backup / "backup-opportunity-detail-1.2.0-buttonList-stabile.js").write_text(DETAIL_JS)
(backup / "backup-opportunity-clientdefs-1.2.0-buttonList-stabile.json").write_text(
    json.dumps(CLIENT_DEFS, indent=4, ensure_ascii=False) + "\n"
)

print("ok")
