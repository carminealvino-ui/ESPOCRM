/* global define, Espo */

define('custom:views/appuntamento/popup-notification', [
    'crm:views/meeting/popup-notification',
], function (MeetingPopupModule) {

    const Parent = MeetingPopupModule.default || MeetingPopupModule;

    const ESITO_POPUP_SCOPES = {
        Appuntamento: {
            layoutName: 'detailEsitoPopup',
            isComplete: function (model) {
                const status = model.get('status');

                if (!status || status === 'Planned') {
                    return false;
                }

                if (!model.get('sottostato') || !model.get('esito')) {
                    return false;
                }

                return !!(model.get('noteEsito') || '').trim();
            },
            incompleteMessage: 'Compilare Stato, Sottostato, Esito e Note Esito, poi cliccare Salva.',
        },
        Meeting: {
            layoutName: 'detailEsitoPopup',
            isComplete: function (model) {
                const status = model.get('status');

                return !!status && status !== 'Planned';
            },
            incompleteMessage: 'Selezionare Stato (Svolto o Non svolto) e cliccare Salva.',
        },
        Call: {
            layoutName: 'detailEsitoPopup',
            isComplete: function (model) {
                const status = model.get('status');

                if (!status || status === 'Planned') {
                    return false;
                }

                if (!(model.get('description') || '').trim()) {
                    return false;
                }

                if (model.get('daRichiamare')) {
                    if (!model.get('dataRichiamo') || !model.get('richiamo')) {
                        return false;
                    }
                }

                return true;
            },
            incompleteMessage: 'Compilare Stato, descrizione esito e dati richiamo (se attivo), poi cliccare Salva.',
        },
        Task: {
            layoutName: 'detailEsitoPopup',
            isComplete: function (model) {
                const status = model.get('status');

                if (!status || status === 'Not Started' || status === 'Started') {
                    return false;
                }

                if (status === 'Completed' && !model.get('dateCompleted')) {
                    return false;
                }

                return true;
            },
            incompleteMessage: 'Aggiornare Stato (es. Completato) e data completamento se necessario, poi cliccare Salva.',
        },
    };

    return class EsitoPopupNotificationView extends Parent {

        setup() {
            const entityType = this.notificationData.entityType;
            const config = ESITO_POPUP_SCOPES[entityType];

            if (!config) {
                this.template = 'crm:meeting/popup-notification';
                super.setup();

                return;
            }

            this.esitoPopupConfig = config;
            this.isEsitoPopup = true;
            this.closeButton = false;
            this.collapseButton = false;
            this.template = 'custom:appuntamento/popup-notification';

            this.addActionHandler('saveEsito', () => this.actionSaveEsito());

            this.setupEsitoPopup(entityType);
        }

        setupEsitoPopup(entityType) {
            const id = this.notificationData.id;

            const promise = this.getModelFactory().create(entityType)
                .then(model => {
                    model.id = id;

                    return model.fetch();
                })
                .then(model => {
                    this.esitoModel = model;
                    this.esitoEntityType = entityType;
                });

            this.wait(promise);
        }

        getPopupRootSelector() {
            return this.containerSelector || ('#' + this.id);
        }

        renderEsitoRecordView() {
            if (this.hasView('record')) {
                this.clearView('record');
            }

            return new Promise(resolve => {
                this.createView('record', 'views/record/edit', {
                    model: this.esitoModel,
                    scope: this.esitoEntityType,
                    type: 'edit',
                    layoutName: this.esitoPopupConfig.layoutName,
                    selector: this.getPopupRootSelector() + ' .esito-popup-record',
                    sideDisabled: true,
                    bottomDisabled: true,
                    buttonsDisabled: true,
                    focusForCreate: false,
                }, view => {
                    view.render();
                    resolve();
                });
            });
        }

        data() {
            if (!this.isEsitoPopup) {
                return super.data();
            }

            return {
                header: this.translate(this.notificationData.entityType, 'scopeNames'),
                entityType: this.notificationData.entityType,
                notificationData: this.notificationData,
                notificationId: this.notificationId,
                closeButton: false,
                collapseButton: false,
            };
        }

        afterRender() {
            super.afterRender();

            if (!this.isEsitoPopup) {
                return;
            }

            this.$el.find('[data-action="close"]').addClass('hidden');
            this.$el.find('[data-action="collapse"]').addClass('hidden');

            if (this.esitoFieldsRendered || !this.esitoModel) {
                return;
            }

            this.esitoFieldsRendered = true;
            this.renderEsitoRecordView();
        }

        resolveCancel() {
            if (this.isEsitoPopup && !this.isEsitoComplete()) {
                Espo.Ui.warning(this.getIncompleteMessage());

                return;
            }

            super.resolveCancel();
        }

        isEsitoComplete() {
            if (!this.esitoModel || !this.esitoPopupConfig) {
                return false;
            }

            return this.esitoPopupConfig.isComplete(this.esitoModel);
        }

        getIncompleteMessage() {
            return this.esitoPopupConfig.incompleteMessage;
        }

        actionSaveEsito() {
            if (!this.isEsitoComplete()) {
                Espo.Ui.warning(this.getIncompleteMessage());

                return;
            }

            Espo.Ui.notify(' ...');

            this.esitoModel.save()
                .then(() => {
                    Espo.Ui.notify();
                    super.resolveCancel();
                });
        }

        getTitle() {
            if (this.isEsitoPopup) {
                return this.notificationData.name ||
                    this.translate(this.notificationData.entityType, 'scopeNames');
            }

            return super.getTitle();
        }
    };
});
