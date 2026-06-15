/* global define, Espo */

define('custom:views/appuntamento/popup-notification', [
    'crm:views/meeting/popup-notification',
], function (MeetingPopupModule) {

    const Parent = MeetingPopupModule.default || MeetingPopupModule;

    const ESITO_POPUP_SCOPES = {
        Appuntamento: {
            fields: ['status', 'sottostato', 'esito', 'noteEsito'],
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
            fields: ['status'],
            isComplete: function (model) {
                const status = model.get('status');

                return !!status && status !== 'Planned';
            },
            incompleteMessage: 'Selezionare Stato (Svolto o Non svolto) e cliccare Salva.',
        },
        Call: {
            fields: ['status', 'description', 'daRichiamare', 'dataRichiamo', 'richiamo'],
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
            fields: ['status', 'dateCompleted'],
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

            this.setupEsitoPopup(entityType, config);
        }

        setupEsitoPopup(entityType, config) {
            const id = this.notificationData.id;
            const dateField = this.notificationData.dateField || 'dateStart';
            const selectFields = config.fields.concat([dateField, 'name']);

            this.esitoDateField = dateField;

            const promise = this.getModelFactory().create(entityType)
                .then(model => {
                    model.id = id;

                    return model.fetch({
                        select: selectFields.join(','),
                    }).then(() => {
                        this.esitoModel = model;
                        this.esitoEntityType = entityType;
                    });
                });

            this.wait(promise);
        }

        getPopupRootSelector() {
            return this.containerSelector || ('#' + this.id);
        }

        createFieldView(viewKey, fieldName, mode, readOnly) {
            const model = this.esitoModel;
            const selector = this.getPopupRootSelector() + ' .field[data-name="' + fieldName + '"]';
            const $target = $(selector);

            if (!$target.length) {
                return Promise.resolve();
            }

            const fieldType = model.getFieldType(fieldName) || 'base';
            const viewName = this.getFieldManager().getViewName(fieldType);

            if (this.hasView(viewKey)) {
                this.clearView(viewKey);
            }

            return new Promise(resolve => {
                this.createView(viewKey, viewName, {
                    model: model,
                    mode: mode,
                    el: $target.get(0),
                    name: fieldName,
                    readOnly: readOnly,
                }, view => {
                    view.render();
                    resolve();
                });
            });
        }

        renderEsitoFieldViews() {
            const config = this.esitoPopupConfig;
            const dateField = this.esitoDateField;
            const tasks = [
                this.createFieldView('date', dateField, 'detail', true),
            ];

            config.fields.forEach(fieldName => {
                tasks.push(this.createFieldView(fieldName, fieldName, 'edit', false));
            });

            return Promise.all(tasks);
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
                fieldNames: this.esitoPopupConfig.fields,
                dateFieldName: this.esitoDateField || 'dateStart',
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
            this.renderEsitoFieldViews();
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
