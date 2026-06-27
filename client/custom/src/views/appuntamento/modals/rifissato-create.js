/* global define, Espo */

define('custom:views/appuntamento/modals/rifissato-create', ['views/modal'], function (ModalDep) {

    const Parent = ModalDep.default || ModalDep;

    return class AppuntamentoRifissatoCreateModal extends Parent {

        className = 'dialog dialog-record rifissato-create-dialog';

        template = 'custom:appuntamento/rifissato-create';

        setup() {
            this.headerText = 'Nuovo appuntamento rifissato';
            this.sourceId = this.options.sourceId;
            this.assignedUsersIds = this.options.assignedUsersIds || [];
            this.originalDateStart = this.options.originalDateStart || null;

            this.buttonList = [
                {
                    name: 'save',
                    label: 'Crea appuntamento',
                    style: 'primary',
                    onClick: () => this.actionSave(),
                },
                {
                    name: 'cancel',
                    label: 'Annulla',
                    onClick: () => this.close(),
                },
            ];

            this.wait(true);

            return this.getModelFactory().create('Appuntamento')
                .then(model => {
                    this.model = model;
                    this.model.set({status: 'Planned'});
                    this.wait(false);

                    Parent.prototype.setup.call(this);
                });
        }

        data() {
            return {
                originalDateLabel: this.getOriginalDateLabel(),
            };
        }

        getOriginalDateLabel() {
            if (!this.originalDateStart) {
                return null;
            }

            return this.getDateTime().toDisplay(this.originalDateStart);
        }

        afterRender() {
            Parent.prototype.afterRender.call(this);

            this.createView('record', 'views/record/edit', {
                scope: 'Appuntamento',
                model: this.model,
                type: 'edit',
                layoutName: 'rifissatoCreate',
                el: this.getSelector() + ' .rifissato-record',
                sideDisabled: true,
                bottomDisabled: true,
                buttonsDisabled: true,
                detailDisabled: true,
                focusForCreate: true,
            }, view => {
                view.render();
            });
        }

        actionSave() {
            const recordView = this.getView('record');

            if (recordView && typeof recordView.fetch === 'function') {
                recordView.fetch();
            }

            const dateStart = this.model.get('dateStart');

            if (!dateStart) {
                Espo.Ui.warning('Inserire data e ora del nuovo appuntamento.');

                return;
            }

            if (!this.sourceId) {
                Espo.Ui.error('Appuntamento origine non valido.');

                return;
            }

            Espo.Ui.notify(' ...');

            Espo.Ajax.postRequest('Appuntamento/action/createRifissato', {
                sourceId: this.sourceId,
                dateStart: dateStart,
                assignedUsersIds: this.assignedUsersIds,
            })
                .then(response => {
                    Espo.Ui.notify(false);
                    Espo.Ui.success('Nuovo appuntamento creato.');
                    this.trigger('after:save', response);
                    this.close();
                })
                .catch(() => {
                    Espo.Ui.notify(false);
                });
        }
    };
});
