/* global define */

define('custom:views/appuntamento/record/edit-small', [
    'views/record/edit',
    'custom:helpers/appuntamento-prospect-sync',
], function (Dep, ProspectSync) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            ProspectSync.setupProspectSync(this);
            ProspectSync.setupDefaultDuration(this);
        },

        getModalEditView: function () {
            let parent = this.getParentView();

            while (parent) {
                if (
                    parent.dialog &&
                    typeof parent.actionSave === 'function' &&
                    typeof parent.getRecordView === 'function'
                ) {
                    return parent;
                }

                parent = parent.getParentView ? parent.getParentView() : null;
            }

            return null;
        },

        closeModalEditView: function (modalView) {
            if (!modalView || !modalView.dialog) {
                return;
            }

            if (modalView.dialog.$el && !modalView.dialog.$el.is(':visible')) {
                return;
            }

            modalView.id = modalView.id || this.model.id;
            modalView.dialog.close();
        },

        afterNotModified: function () {
            const modalView = this.getModalEditView();

            if (modalView && this.model.id) {
                this.closeModalEditView(modalView);

                return;
            }

            Dep.prototype.afterNotModified.call(this);
        },

        save: function (options) {
            options = options || {};

            const modalView = this.getModalEditView();
            const view = this;

            return Dep.prototype.save.call(this, options).then(function () {
                if (!modalView) {
                    return;
                }

                window.setTimeout(function () {
                    view.closeModalEditView(modalView);
                }, 0);
            });
        },
    });
});
