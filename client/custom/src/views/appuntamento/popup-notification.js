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
            getMissingFields: function (model) {
                if (!model.get('status') || model.get('status') === 'Planned') {
                    return ['Stato'];
                }

                return [];
            },
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
                if (model.get('verificaInstallazioneContratto')) {
                    return ['Installato', 'Rinviato'].includes(model.get('esitoVerificaInstallazione'));
                }

                const status = model.get('status');

                if (!status || status === 'Not Started' || status === 'Started') {
                    return false;
                }

                // EspoCRM sets dateCompleted server-side when the status is saved.
                return true;
            },
            incompleteMessage: 'Aggiornare Stato (es. Completato), poi cliccare Salva.',
            getMissingFields: function (model) {
                if (model.get('verificaInstallazioneContratto')) {
                    return ['Installato', 'Rinviato'].includes(model.get('esitoVerificaInstallazione')) ?
                        [] : ['Esito verifica installazione'];
                }

                const status = model.get('status');

                return !status || status === 'Not Started' || status === 'Started' ? ['Stato'] : [];
            },
        },
    };

    return class EsitoPopupNotificationView extends Parent {

        setup() {
            const entityType = (this.options.notificationData || this.notificationData || {}).entityType;
            const config = ESITO_POPUP_SCOPES[entityType];

            if (!config) {
                super.setup();

                return;
            }

            // NON chiamare super.setup(): il parent Meeting assume un popup
            // standard e può rompere il render del form esito.
            // Inizializziamo solo i campi che servono (come prima del fix Nascondi).
            this.notificationData = this.options.notificationData || {};
            this.notificationId = this.options.notificationId;
            this.id = this.options.id || this.id;
            this.onCollapse = this.options.onCollapse;
            this.onExpand = this.options.onExpand;

            this.esitoPopupConfig = config;
            this.isEsitoPopup = true;
            this.closeButton = false;
            this.collapseButton = true;
            this.template = 'custom:appuntamento/popup-notification';

            this.addActionHandler('saveEsito', () => this.actionSaveEsito());
            this.addActionHandler('rememberTesto', () => this.actionRememberTesto());
            this.addActionHandler('createOpportunity', () => this.actionCreateOpportunity());
            this.addActionHandler('hideEsitoPopup', () => this.actionHideEsitoPopup());
            this.addActionHandler('collapse', () => this.actionHideEsitoPopup());
        }

        data() {
            if (!this.isEsitoPopup) {
                return super.data();
            }

            return {
                header: this.translate(this.notificationData.entityType, 'scopeNames'),
                entityType: this.notificationData.entityType,
                persistentTestoHelp: this.notificationData.entityType === 'Call',
                notificationData: this.notificationData,
                notificationId: this.notificationId,
                closeButton: false,
                collapseButton: true,
            };
        }

        afterRender() {
            super.afterRender();

            if (!this.isEsitoPopup) {
                return;
            }

            this.$el.find('[data-action="close"]').addClass('hidden');
            this.$el.addClass('esito-popup-wide');

            if (!this.hasView('esitoRecord')) {
                this.createEsitoRecordView();
            }
        }

        /** Nasconde per 30 minuti la sola notifica, senza cambiare il record CRM. */
        actionHideEsitoPopup() {
            this.remove();
            this.notifyReminderQueue('hidden');
        }

        notifyReminderQueue(kind) {
            window.dispatchEvent(new CustomEvent('crm:reminder-next', {
                detail: {id: this.notificationId, kind: kind},
            }));
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
                        if (entityType !== 'Call') return;
                        return Espo.Ajax.getRequest('CallStandardTesto/action/read').then(response => {
                            const current = (model.get('testo') || '').trim();
                            const legacy = 'Salve, sono Carmine Alvino di ARIEL ENERGIA, mi fa sapere entro la giornata di oggi poi cosa ha deciso rispetto alla proposta che le ho fatto, Grazie';
                            if (!current || current === legacy) model.set('testo', response.testo);
                        });
                    }).then(() => {
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
                                    this.setupPersistentTesto(view, model);
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

        actionRememberTesto() {
            if (this.rememberingTesto || this.esitoEntityType !== 'Call') return;
            const model = this.getEsitoModel();
            const textarea = this.$el.find('.field[data-name="testo"] textarea');
            const testo = (textarea.length ? textarea.val() : model.get('testo') || '').trim();
            if (!testo) { Espo.Ui.warning('Scrivi il messaggio da memorizzare.'); return; }
            this.rememberingTesto = true;
            Espo.Ajax.postRequest('CallStandardTesto/action/save', {id: model.id, testo: testo}).then(() => {
                model.set('testo', testo, {silent: true});
                Espo.Ui.success('Testo memorizzato per i prossimi messaggi.');
            }).finally(() => { this.rememberingTesto = false; });
        }

        setupPersistentTesto(recordView, model) {
            const $field = recordView.$el.find('.field[data-name="testo"]');
            const $textarea = $field.find('textarea');

            if (!$textarea.length) {
                return;
            }

            $textarea.off('input.crmPopupTesto').on('input.crmPopupTesto', event => {
                model.set('testo', $(event.currentTarget).val(), {silent: true});
            });

            this.once('remove', () => $textarea.off('input.crmPopupTesto'));
        }

        getEsitoModel() {
            this.syncEsitoRecordModel();

            const recordView = this.getView('esitoRecord');

            if (recordView && recordView.model) {
                return recordView.model;
            }

            return this.esitoModel;
        }

        getEditableFieldNames(entityType) {
            if (entityType === 'Task') {
                return ['status', 'esitoVerificaInstallazione'];
            }

            if (entityType === 'Call') {
                return [
                    'status',
                    'direction',
                    'tipologia',
                    'whatsApp',
                    'testo',
                    'daRichiamare',
                    'dataRichiamo',
                    'richiamo',
                ];
            }

            return [
                'status',
                'direction',
                'sottostato',
                'esito',
                'noteEsito',
                'tipologia',
                'canaleContatto',
                'description',
                'daRichiamare',
                'dataRichiamo',
                'richiamo',
            ];
        }

        fetchEditableFields(recordView, entityType) {
            if (!recordView) {
                return;
            }

            this.getEditableFieldNames(entityType).forEach(fieldName => {
                const fieldView = recordView.getFieldView && recordView.getFieldView(fieldName);

                if (!fieldView || typeof fieldView.fetch !== 'function') {
                    return;
                }

                try {
                    fieldView.fetch();
                } catch (e) {
                    // Campi read-only o DOM non pronto: ignora.
                }
            });
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
            const entityType = this.esitoEntityType || this.notificationData.entityType;

            if (model && fetchedAttributes && typeof fetchedAttributes === 'object') {
                model.set(fetchedAttributes, {silent: true});
            }

            this.fetchEditableFields(recordView, entityType);

            const domStatus = this.getCurrentStatus();

            if (model && domStatus) {
                model.set('status', domStatus, {silent: true});
            }
        }

        getMissingEsitoFields() {
            this.syncEsitoRecordModel();
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
            this.syncEsitoRecordModel();
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
                this.listenTo(recordModel, 'change:status change:sottostato change:esito', () => this.updateActionButtons());
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
            const isPlanned = status === 'Planned' || status === '';
            const current = (model.get('sottostato') || '').toString();
            const currentEsito = (model.get('esito') || '').toString();

            if (isPlanned) {
                if (current) {
                    model.set('sottostato', '', {silent: true});
                }

                if (currentEsito) {
                    model.set('esito', '', {silent: true});
                }
            }

            const $sottostatoField = recordView.$el.find('.field[data-name="sottostato"]');
            const $esitoField = recordView.$el.find('.field[data-name="esito"]');
            const $sottostatoCell = $sottostatoField.closest('.cell, .field-container');
            const $esitoCell = $esitoField.closest('.cell, .field-container');

            if ($sottostatoField.length || $sottostatoCell.length) {
                $sottostatoField.toggleClass('hidden', isPlanned);
                $sottostatoCell.toggleClass('hidden', isPlanned);
            }

            if ($esitoField.length || $esitoCell.length) {
                $esitoField.toggleClass('hidden', isPlanned);
                $esitoCell.toggleClass('hidden', isPlanned);
            }

            this.updateActionButtons();
        }

        getCurrentStatus() {
            const recordView = this.getView('esitoRecord');
            let status = null;

            if (recordView && recordView.getFieldView) {
                const statusField = recordView.getFieldView('status');

                if (statusField && typeof statusField.getValue === 'function') {
                    const fieldValue = statusField.getValue();

                    if (fieldValue) {
                        status = fieldValue;
                    }
                }
            }

            const $select = this.$el.find('.field[data-name="status"] select');

            if ($select.length) {
                const domValue = $select.val();

                if (domValue) {
                    status = domValue;
                }
            }

            if (!status && recordView && recordView.model) {
                status = recordView.model.get('status');
            }

            if (!status && this.esitoModel) {
                status = this.esitoModel.get('status');
            }

            return status || null;
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

            const model = this.esitoModel;
            $save.text(model && model.get('sottostato') === 'Rifissato' ? 'Scegli nuova data' : 'Salva');
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

        /**
         * Nasconde TEMPORANEAMENTE lo stack popup mentre si compila l'Opportunità.
         * Non usa collapse/storage: evita di "perdere" i popup nelle sessioni successive.
         */
        sendAllEsitoPopupsToBackground() {
            const $container = this.$el.closest('.popup-notification-container');
            const activeElement = document.activeElement;

            if ($container && $container.length) {
                // Evita warning focus su elementi che diventano non-interagibili.
                if (activeElement && $container.has(activeElement).length && typeof activeElement.blur === 'function') {
                    activeElement.blur();
                }
                $container.attr('data-opportunity-parked', '1');
                $container.addClass('hidden');
                return;
            }

            this.$el.addClass('hidden');
        }

        restoreEsitoPopupsFromBackground() {
            this.$el.removeClass('hidden');
            const $container = this.$el.closest('.popup-notification-container');

            if (!$container || !$container.length) {
                return;
            }

            if ($container.attr('data-opportunity-parked') === '1') {
                $container.removeAttr('data-opportunity-parked');
                $container.removeClass('hidden');
            }
        }

        openCreateOpportunityModal(model) {
            const attributes = AppuntamentoSync.buildAttributesFromAppuntamento(
                this.getAppuntamentoSyncPayload(model)
            );

            // Park temporaneo: altrimenti lo stack copre "Crea Opportunità".
            this.sendAllEsitoPopupsToBackground();

            this.createView('createOpportunityDialog', 'views/modals/edit', {
                scope: 'Opportunity',
                attributes: attributes,
                // Riduce i warning browser dovuti a focus forzato in contesti non interagibili.
                focusForCreate: false,
                noFocus: true,
            }, view => {
                // Apri il modal al tick successivo: il container popup è già nascosto.
                setTimeout(() => view.render(), 0);

                let opportunitySaved = false;

                this.listenToOnce(view, 'after:save', () => {
                    opportunitySaved = true;
                    this.restoreEsitoPopupsFromBackground();
                    super.resolveCancel();
                    this.notifyReminderQueue('completed');
                });

                // Annulla / chiusura senza salvataggio → ripristina i popup.
                this.listenToOnce(view, 'remove', () => {
                    if (!opportunitySaved) {
                        this.creatingOpportunity = false;
                        this.restoreEsitoPopupsFromBackground();
                    }
                });
            });
        }

        reviewProspectIdentity(model) {
            const id = model.get('prospectId');
            if (!id) {
                this.openCreateOpportunityModal(model);
                return;
            }
            this.sendAllEsitoPopupsToBackground();
            this.createView('reviewProspectIdentity', 'views/modals/edit', {
                scope: 'Prospect', id: id,
                layoutName: 'identityReview',
                headerText: 'Esito appuntamento: verifica nominativo e B2C/B2B',
                fullFormDisabled: true,
            }, view => {
                let saved = false;
                this.listenToOnce(view, 'after:save', () => {
                    saved = true;
                    model.fetch().then(() => this.openCreateOpportunityModal(model)).catch(() => {
                        this.creatingOpportunity = false;
                        this.restoreEsitoPopupsFromBackground();
                        Espo.Ui.error('Anagrafica salvata. Riaprire l’esito per aggiornare l’appuntamento.');
                    });
                });
                this.listenToOnce(view, 'remove', () => {
                    if (!saved) {
                        this.creatingOpportunity = false;
                        this.restoreEsitoPopupsFromBackground();
                    }
                });
                view.render();
            });
        }

        actionCreateOpportunity() {
            if (this.creatingOpportunity) return;
            if (!this.shouldShowCreateOpportunity()) {
                return;
            }

            if (!this.isEsitoComplete()) {
                Espo.Ui.warning(this.getIncompleteMessage());

                return;
            }

            const model = this.getEsitoModel();

            this.creatingOpportunity = true;
            this.notifyReminderQueue('opportunity-pending');
            Espo.Ui.notify(' ...');

            model._appointmentOpportunityManaged = true;
            model.save()
                .then(() => {
                    Espo.Ui.notify();
                    this.reviewProspectIdentity(model);
                })
                .catch(error => {
                    this.creatingOpportunity = false;
                    this.notifyReminderQueue('opportunity-save-failed');
                    Espo.Ui.notify(false);
                    Espo.Ui.error((error && error.message) || 'Errore salvataggio esito.');
                }).finally(() => { delete model._appointmentOpportunityManaged; });
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

        openRifissatoModal(model) {
            if (this.creatingRifissato) return;
            this.creatingRifissato = true;
            this.sendAllEsitoPopupsToBackground();
            this.createView('rifissatoDialog', 'custom:views/appuntamento/modals/rifissato-create', {
                sourceId: model.id,
                originalDateStart: model.get('dateStart'),
                sourceAttributes: {
                    status: 'Not Held', sottostato: 'Rifissato',
                    esito: model.get('esito') || null, noteEsito: model.get('noteEsito') || null
                }
            }, view => {
                view.render();
                let saved = false;
                this.listenToOnce(view, 'after:save', () => {
                    saved = true;
                    this.restoreEsitoPopupsFromBackground();
                    super.resolveCancel();
                    this.notifyReminderQueue('completed');
                });
                this.listenToOnce(view, 'remove', () => {
                    this.creatingRifissato = false;
                    if (!saved) this.restoreEsitoPopupsFromBackground();
                });
            });
        }

        actionSaveEsito() {
            this.syncEsitoRecordModel();
            const source = this.getEsitoModel();
            if ((this.esitoEntityType || this.notificationData.entityType) === 'Appuntamento' &&
                source && source.get('sottostato') === 'Rifissato' && source.get('status') !== 'Planned') {
                this.openRifissatoModal(source);
                return;
            }

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

            const recordView = this.getView('esitoRecord');
            let payload = saveAttributes;

            if (entityType === 'Call' && recordView && typeof recordView.fetch === 'function') {
                try {
                    payload = Object.assign({}, recordView.fetch(), saveAttributes || {});
                } catch (e) {
                    payload = saveAttributes;
                }
            }

            const savePromise = entityType === 'Call' ?
                model.save(payload) :
                model.save();

            savePromise
                .then(() => {
                    Espo.Ui.notify();
                    super.resolveCancel();
                    this.notifyReminderQueue('completed');
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
