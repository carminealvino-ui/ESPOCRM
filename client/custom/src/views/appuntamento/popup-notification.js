/* global define, Espo */

define('custom:views/appuntamento/popup-notification', [
    'crm:views/meeting/popup-notification',
    'custom:views/opportunity/helpers/appuntamento-sync',
    'custom:helpers/call-esito-popup-defaults',
], function (MeetingPopupModule, AppuntamentoSyncModule, CallEsitoDefaultsModule) {

    const Parent = MeetingPopupModule.default || MeetingPopupModule;
    const AppuntamentoSync = AppuntamentoSyncModule.default || AppuntamentoSyncModule;
    const CallEsitoDefaults = CallEsitoDefaultsModule.default || CallEsitoDefaultsModule;

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

                return true;
            },
            incompleteMessage: 'Compilare Stato, Sottostato ed Esito, poi cliccare Salva.',
            getMissingFields: function (model) {
                const missing = [];

                if (!model.get('status') || model.get('status') === 'Planned') {
                    missing.push('Stato');
                }

                if (!model.get('sottostato')) {
                    missing.push('Sottostato');
                }

                if (!model.get('esito')) {
                    missing.push('Esito');
                }

                return missing;
            },
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

                if (model.get('daRichiamare')) {
                    if (!model.get('dataRichiamo') || !model.get('richiamo')) {
                        return false;
                    }

                    return true;
                }

                return !!status && status !== 'Planned';
            },
            incompleteMessage: 'Selezionare Stato (Svolto o Non svolto), oppure Rinvia richiamo / Crea nuova chiamata con data e tipologia.',
            getMissingFields: function (model) {
                const missing = [];

                if (model.get('daRichiamare')) {
                    if (!model.get('dataRichiamo')) {
                        missing.push('Data Richiamo');
                    }

                    if (!model.get('richiamo')) {
                        missing.push('Richiamo');
                    }

                    return missing;
                }

                if (!model.get('status') || model.get('status') === 'Planned') {
                    missing.push('Stato');
                }

                return missing;
            },
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
            const container = this.getSelector() + ' .esito-popup-record .record';

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

                                if (entityType === 'Call') {
                                    CallEsitoDefaults.applyDefaults(model, this.notificationData.name);
                                    CallEsitoDefaults.applyWithRetry(view, this.notificationData.name);
                                    this.setupRinvioDefaults(view);
                                }

                                if (entityType === 'Appuntamento') {
                                    this.setupAppuntamentoSottostatoFilter(view);
                                }

                                this.setupActionButtonListeners(view);
                                this.updateActionButtons();
                                resolve();
                            });
                        });
                    });
                });

            this.wait(promise);
        }

        getEsitoModel() {
            this.syncEsitoRecordModel();

            const recordView = this.getView('esitoRecord');

            if (recordView && recordView.model) {
                return recordView.model;
            }

            return this.esitoModel;
        }

        syncEsitoRecordModel() {
            const recordView = this.getView('esitoRecord');

            if (!recordView) {
                return;
            }

            let fetchedAttributes = null;

            if (typeof recordView.fetch === 'function') {
                try {
                    fetchedAttributes = recordView.fetch();
                } catch (e) {
                    fetchedAttributes = {};
                }
            }

            const model = recordView.model || this.esitoModel;

            if (model && fetchedAttributes && typeof fetchedAttributes === 'object') {
                model.set(fetchedAttributes, {silent: true});
            }

            const domStatus = this.getCurrentStatus();

            if (model && domStatus) {
                model.set('status', domStatus, {silent: true});
            }
        }

        getMissingEsitoFields() {
            const model = this.getEsitoModel();

            if (!model || !this.esitoPopupConfig) {
                return ['Stato', 'Sottostato', 'Esito'];
            }

            if (typeof this.esitoPopupConfig.getMissingFields === 'function') {
                return this.esitoPopupConfig.getMissingFields(model);
            }

            if (!this.isEsitoComplete()) {
                return ['campi obbligatori'];
            }

            return [];
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

            const domStatus = this.getCurrentStatus();

            if (domStatus && domStatus !== model.get('status')) {
                model.set('status', domStatus, {silent: true});
            }

            return this.esitoPopupConfig.isComplete(model);
        }

        getIncompleteMessage() {
            const missing = this.getMissingEsitoFields();

            if (missing.length) {
                const actionLabel = this.shouldShowCreateOpportunity() ?
                    'Crea Opportunità' :
                    'Salva';

                return 'Compilare ' + missing.join(', ') + ', poi cliccare ' + actionLabel + '.';
            }

            if (this.shouldShowCreateOpportunity()) {
                return 'Compilare Stato, Sottostato ed Esito, poi cliccare Crea Opportunità.';
            }

            return this.esitoPopupConfig.incompleteMessage;
        }

        setupActionButtonListeners(recordView) {
            const recordModel = recordView.model || this.esitoModel;

            if (recordModel) {
                this.listenTo(recordModel, 'change:status', () => this.updateActionButtons());
                this.listenTo(recordModel, 'change', () => {
                    if (recordModel.hasChanged('status')) {
                        this.updateActionButtons();
                    }
                });
            }

            const bindField = (fieldName) => {
                if (!recordView.getFieldView) {
                    return false;
                }

                const field = recordView.getFieldView(fieldName);

                if (!field) {
                    return false;
                }

                this.listenTo(field, 'change', () => this.updateActionButtons());

                return true;
            };

            if (!bindField('status')) {
                this.listenToOnce(recordView, 'after:render', () => {
                    bindField('status');
                    this.updateActionButtons();
                });
            }

            this.$el.off('change.esitoStatus input.esitoStatus click.esitoStatus');

            this.$el.on(
                'change.esitoStatus input.esitoStatus',
                '.field[data-name="status"] select, .field[data-name="status"] input',
                () => {
                    window.setTimeout(() => this.updateActionButtons(), 0);
                }
            );

            this.$el.on(
                'click.esitoStatus',
                '.field[data-name="status"] .selectize-dropdown-content .option',
                () => {
                    window.setTimeout(() => this.updateActionButtons(), 50);
                }
            );
        }

        setupRinvioDefaults(recordView) {
            const entityType = this.esitoEntityType || this.notificationData.entityType;

            if (entityType !== 'Call' && entityType !== 'Appuntamento') {
                return;
            }

            const model = recordView.model;

            if (!model) {
                return;
            }

            CallEsitoDefaults.setupRinvioFieldListeners(
                recordView,
                model,
                this.getDateTime(),
                this
            );

            this.listenTo(model, 'change:status', () => this.updateActionButtons());
            this.updateActionButtons();
        }

        setupAppuntamentoSottostatoFilter(recordView) {
            const entityType = this.esitoEntityType || this.notificationData.entityType;

            if (entityType !== 'Appuntamento' || !recordView || !recordView.model) {
                return;
            }

            const apply = () => this.applyAppuntamentoSottostatoFilter(recordView);

            this.listenTo(recordView.model, 'change:status', apply);
            this.listenToOnce(recordView, 'after:render', apply);
            window.setTimeout(apply, 0);
        }

        applyAppuntamentoSottostatoFilter(recordView) {
            const model = recordView && recordView.model;

            if (!model || !recordView.$el) {
                return;
            }

            const status = (model.get('status') || '').toString();
            const allowedMap = {
                Held: [
                    'Pending',
                    'Gestito',
                    'Rifissato',
                    'Chiuso Positivamente',
                    'Non Interessato',
                    'Solo Informazioni',
                    'Prodotto non Conforme',
                    'Fuori Target',
                ],
                'Not Held': [
                    'Non Confermato',
                    'Non Ricevuto',
                    'Infattibilità Tecnica',
                    'Non Gestito',
                    'Annullato',
                ],
                Ingestibile: [
                    'Non Confermato',
                    'Non Ricevuto',
                    'Infattibilità Tecnica',
                    'Non Gestito',
                    'Annullato',
                ],
            };

            const allowed = allowedMap[status] || [];
            const current = (model.get('sottostato') || '').toString();

            if (current && allowed.length && !allowed.includes(current)) {
                model.set('sottostato', '', {silent: true});
            }

            const $select = recordView.$el.find('.field[data-name="sottostato"] select');

            if ($select.length) {
                $select.find('option').each(function () {
                    const value = (this.value || '').toString();
                    const visible = value === '' || allowed.length === 0 || allowed.includes(value);

                    this.hidden = !visible;
                    this.disabled = !visible;
                });
            }
        }

        getCurrentStatus() {
            const recordView = this.getView('esitoRecord');
            let status = null;

            if (recordView && recordView.model) {
                status = recordView.model.get('status');
            } else if (this.esitoModel) {
                status = this.esitoModel.get('status');
            }

            const $select = this.$el.find('.field[data-name="status"] select');

            if ($select.length) {
                const domValue = $select.val();

                if (domValue) {
                    status = domValue;
                }
            }

            return status;
        }

        shouldShowCreateOpportunity() {
            const entityType = this.esitoEntityType || this.notificationData.entityType;

            if (entityType !== 'Appuntamento') {
                return false;
            }

            return this.getCurrentStatus() === 'Held';
        }

        updateActionButtons() {
            const entityType = this.esitoEntityType || this.notificationData.entityType;
            const $save = this.$el.find('[data-role="save"]');
            const $createOpportunity = this.$el.find('[data-role="create-opportunity"]');

            $createOpportunity.addClass('hidden');

            if (entityType !== 'Appuntamento') {
                $save.removeClass('hidden');

                return;
            }

            const showCreateOpportunity = this.shouldShowCreateOpportunity();

            if (showCreateOpportunity) {
                $save.addClass('hidden');
                $createOpportunity.removeClass('hidden');
            } else {
                $save.removeClass('hidden');
            }
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
                })
                .catch(error => {
                    Espo.Ui.notify(false);
                    Espo.Ui.error((error && error.message) || 'Errore salvataggio esito.');
                });
        }

        buildSaveAttributes() {
            const entityType = this.esitoEntityType || this.notificationData.entityType;
            const model = this.getEsitoModel();
            const domStatus = this.getCurrentStatus();

            if (domStatus && model) {
                model.set('status', domStatus, {silent: true});
            }

            if (entityType !== 'Call') {
                return {};
            }

            const saveAttributes = CallEsitoDefaults.getSaveAttributes(
                model,
                this.notificationData.name
            );

            if (domStatus) {
                saveAttributes.status = domStatus;
            }

            if (!saveAttributes.status) {
                return null;
            }

            if (model) {
                model.set(saveAttributes, {silent: true});
            }

            return saveAttributes;
        }

        actionSaveEsito() {
            if (!this.isEsitoComplete()) {
                Espo.Ui.warning(this.getIncompleteMessage());

                return;
            }

            const entityType = this.esitoEntityType || this.notificationData.entityType;
            const model = this.getEsitoModel();
            const saveAttributes = this.buildSaveAttributes();

            if (entityType === 'Call' && !saveAttributes) {
                Espo.Ui.error('Stato mancante: seleziona Svolto o Non svolto.');

                return;
            }

            Espo.Ui.notify(' ...');

            const savePromise = entityType === 'Call' ?
                model.save(saveAttributes) :
                model.save();

            savePromise
                .then(() => {
                    Espo.Ui.notify();
                    super.resolveCancel();
                })
                .catch(error => {
                    Espo.Ui.notify(false);
                    Espo.Ui.error((error && error.message) || 'Errore salvataggio esito.');
                });
        }

        getTitle() {
            if (this.isEsitoPopup) {
                return this.notificationData.name ||
                    this.translate(this.notificationData.entityType, 'scopeNames');
            }

            return super.getTitle();
        }

        onCancel() {
            if (!this.notificationId) {
                return;
            }

            // Attività scadute senza reminder Espo (id sintetico past-EntityType-entityId).
            if (String(this.notificationId).startsWith('past-')) {
                return;
            }

            super.onCancel();
        }
    };
});
