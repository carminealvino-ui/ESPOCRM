/* global define, Espo */

define('custom:views/appuntamento/popup-notification', [
    'crm:views/meeting/popup-notification',
    'custom:views/opportunity/helpers/appuntamento-sync',
], function (MeetingPopupModule, AppuntamentoSyncModule) {

    const Parent = MeetingPopupModule.default || MeetingPopupModule;
    const AppuntamentoSync = AppuntamentoSyncModule.default || AppuntamentoSyncModule;

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
                super.setup();

                return;
            }

            this.esitoPopupConfig = config;
            this.isEsitoPopup = true;
            this.closeButton = false;
            this.collapseButton = false;
            this.template = 'custom:appuntamento/popup-notification';

            this.addActionHandler('saveEsito', () => this.actionSaveEsito());
            this.addActionHandler('createOpportunity', () => this.actionCreateOpportunity());
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
            this.$el.addClass('esito-popup-wide');

            if (!this.hasView('esitoRecord')) {
                this.createEsitoRecordView();
            }
        }

        createEsitoRecordView() {
            const entityType = this.notificationData.entityType;
            const config = this.esitoPopupConfig;
            const id = this.notificationData.id;
            const container = this.getSelector() + ' .esito-popup-record';

            const promise = this.getModelFactory().create(entityType)
                .then(model => {
                    model.id = id;

                    return model.fetch().then(() => {
                        this.esitoModel = model;
                        this.esitoEntityType = entityType;

                        return new Promise(resolve => {
                            this.createView('esitoRecord', 'views/record/edit', {
                                scope: entityType,
                                model: model,
                                type: 'edit',
                                layoutName: config.layoutName,
                                el: container,
                                sideDisabled: true,
                                bottomDisabled: true,
                                buttonsDisabled: true,
                                detailDisabled: true,
                                focusForCreate: false,
                                isWide: true,
                            }, view => {
                                view.render();
                                this.listenTo(model, 'change:status', () => this.updateActionButtons());
                                this.updateActionButtons();
                                resolve();
                            });
                        });
                    });
                });

            this.wait(promise);
        }

        getEsitoModel() {
            const recordView = this.getView('esitoRecord');

            if (recordView && recordView.model) {
                return recordView.model;
            }

            return this.esitoModel;
        }

        resolveCancel() {
            if (this.isEsitoPopup && !this.isEsitoComplete()) {
                Espo.Ui.warning(this.getIncompleteMessage());

                return;
            }

            super.resolveCancel();
        }

        isEsitoComplete() {
            const model = this.getEsitoModel();

            if (!model || !this.esitoPopupConfig) {
                return false;
            }

            return this.esitoPopupConfig.isComplete(model);
        }

        getIncompleteMessage() {
            if (this.shouldShowCreateOpportunity()) {
                return 'Compilare Stato, Sottostato, Esito e Note Esito, poi cliccare Crea Opportunità.';
            }

            return this.esitoPopupConfig.incompleteMessage;
        }

        shouldShowCreateOpportunity() {
            if (this.esitoEntityType !== 'Appuntamento') {
                return false;
            }

            const model = this.getEsitoModel();

            return !!model && model.get('status') === 'Held';
        }

        updateActionButtons() {
            if (this.esitoEntityType !== 'Appuntamento') {
                return;
            }

            const showCreateOpportunity = this.shouldShowCreateOpportunity();

            this.$el.find('[data-role="save"]').toggleClass('hidden', showCreateOpportunity);
            this.$el.find('[data-role="create-opportunity"]')
                .toggleClass('hidden', !showCreateOpportunity);
        }

        getAppuntamentoSyncPayload(model) {
            return {
                id: model.id,
                name: model.get('name'),
                dateStart: model.get('dateStart'),
                azienda: model.get('azienda'),
                fornitorePartnerId: model.get('fornitorePartnerId'),
                fornitorePartnerName: model.get('fornitorePartnerName'),
                productBrandId: model.get('productBrandId'),
                productBrandName: model.get('productBrandName'),
                productCategoryId: model.get('productCategoryId'),
                productCategoryName: model.get('productCategoryName'),
                prospectId: model.get('prospectId'),
                prospectName: model.get('prospectName'),
                telefono: model.get('telefono'),
            };
        }

        openCreateOpportunityModal(model) {
            const attributes = AppuntamentoSync.buildAttributesFromAppuntamento(
                this.getAppuntamentoSyncPayload(model)
            );

            this.createView('createOpportunityDialog', 'views/modals/edit', {
                scope: 'Opportunity',
                attributes: attributes,
            }, view => {
                view.render();

                this.listenToOnce(view, 'after:save', () => {
                    super.resolveCancel();
                });
            });
        }

        actionCreateOpportunity() {
            if (!this.shouldShowCreateOpportunity()) {
                return;
            }

            if (!this.isEsitoComplete()) {
                Espo.Ui.warning(this.getIncompleteMessage());

                return;
            }

            const model = this.getEsitoModel();

            Espo.Ui.notify(' ...');

            model.save()
                .then(() => {
                    Espo.Ui.notify();
                    this.openCreateOpportunityModal(model);
                });
        }

        actionSaveEsito() {
            if (!this.isEsitoComplete()) {
                Espo.Ui.warning(this.getIncompleteMessage());

                return;
            }

            const model = this.getEsitoModel();

            Espo.Ui.notify(' ...');

            model.save()
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
