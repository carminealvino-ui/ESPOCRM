/* global define */

/**
 * Campo Relazionato a — sync da Prospect (delega a appuntamento-prospect-sync).
 * VERSIONE: 1.3.0
 */
define('custom:views/fields/appuntamento-parent', [
    'views/fields/link-parent',
    'custom:helpers/appuntamento-prospect-sync',
], function (Dep, ProspectSync) {

    const VERSION = '1.3.0';

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this._prospectSyncTimer = null;

            this.listenTo(this.model, 'change:parentId change:parentType', () => {
                this.scheduleProspectSync();
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.scheduleProspectSync();
        },

        findRecordView: function () {
            let parent = this.getParentView && this.getParentView();

            while (parent) {
                if (typeof parent.getFieldView === 'function') {
                    return parent;
                }

                parent = parent.getParentView && parent.getParentView();
            }

            return null;
        },

        scheduleProspectSync: function () {
            if (this._prospectSyncTimer) {
                window.clearTimeout(this._prospectSyncTimer);
            }

            this._prospectSyncTimer = window.setTimeout(() => {
                this._prospectSyncTimer = null;
                this.runProspectSync();
            }, 250);
        },

        runProspectSync: function () {
            if (this.model.get('parentType') !== 'Prospect' || !this.model.get('parentId')) {
                return;
            }

            const recordView = this.findRecordView();

            if (!recordView) {
                return;
            }

            ProspectSync.syncFromProspect(recordView).catch(error => {
                console.error('[appuntamento-parent ' + VERSION + ']', error);
            });
        },
    });
});
