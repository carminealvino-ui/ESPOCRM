/* global define, Espo */

define('custom:views/appuntamento/popup-notification', [
    'crm:views/meeting/popup-notification',
], function (MeetingPopupModule) {

    const Parent = MeetingPopupModule.default || MeetingPopupModule;
    const APPUNTAMENTO = 'Appuntamento';
    const ESITO_FIELDS = ['status', 'sottostato', 'esito', 'noteEsito'];

    return class AppuntamentoPopupNotificationView extends Parent {

        setup() {
            if (this.notificationData.entityType !== APPUNTAMENTO) {
                this.template = 'crm:meeting/popup-notification';
                super.setup();

                return;
            }

            this.isAppuntamentoEsitoPopup = true;
            this.closeButton = false;
            this.collapseButton = false;
            this.template = 'custom:appuntamento/popup-notification';

            this.addActionHandler('saveEsito', () => this.actionSaveEsito());

            this.setupAppuntamentoEsito();
        }

        setupAppuntamentoEsito() {
            const id = this.notificationData.id;
            const dateField = this.notificationData.dateField || 'dateStart';

            const promise = this.getModelFactory().create(APPUNTAMENTO)
                .then(model => {
                    model.id = id;

                    return model.fetch({
                        select: ESITO_FIELDS.concat([dateField, 'name']).join(','),
                    }).then(() => {
                        this.appuntamentoModel = model;

                        const tasks = [
                            this.createFieldView('date', dateField, 'detail', true),
                        ];

                        ESITO_FIELDS.forEach(fieldName => {
                            tasks.push(this.createFieldView(fieldName, fieldName, 'edit', false));
                        });

                        return Promise.all(tasks);
                    });
                });

            this.wait(promise);
        }

        createFieldView(viewKey, fieldName, mode, readOnly) {
            const model = this.appuntamentoModel;
            const fieldType = model.getFieldType(fieldName) || 'base';
            const viewName = this.getFieldManager().getViewName(fieldType);

            return new Promise(resolve => {
                this.createView(viewKey, viewName, {
                    model: model,
                    mode: mode,
                    selector: '.field[data-name="' + fieldName + '"]',
                    name: fieldName,
                    readOnly: readOnly,
                }, view => {
                    view.render();
                    resolve();
                });
            });
        }

        data() {
            if (!this.isAppuntamentoEsitoPopup) {
                return super.data();
            }

            return {
                header: this.translate(APPUNTAMENTO, 'scopeNames'),
                notificationData: this.notificationData,
                notificationId: this.notificationId,
                closeButton: false,
                collapseButton: false,
            };
        }

        afterRender() {
            super.afterRender();

            if (!this.isAppuntamentoEsitoPopup) {
                return;
            }

            this.$el.find('[data-action="close"]').addClass('hidden');
            this.$el.find('[data-action="collapse"]').addClass('hidden');
        }

        resolveCancel() {
            if (this.isAppuntamentoEsitoPopup && !this.isEsitoComplete()) {
                Espo.Ui.warning(this.getIncompleteMessage());

                return;
            }

            super.resolveCancel();
        }

        isEsitoComplete() {
            if (!this.appuntamentoModel) {
                return false;
            }

            const status = this.appuntamentoModel.get('status');
            const sottostato = this.appuntamentoModel.get('sottostato');
            const esito = this.appuntamentoModel.get('esito');
            const noteEsito = (this.appuntamentoModel.get('noteEsito') || '').trim();

            if (!status || status === 'Planned') {
                return false;
            }

            if (!sottostato || !esito || !noteEsito) {
                return false;
            }

            return true;
        }

        getIncompleteMessage() {
            return 'Compilare Stato, Sottostato, Esito e Note Esito, poi cliccare Salva.';
        }

        actionSaveEsito() {
            if (!this.isEsitoComplete()) {
                Espo.Ui.warning(this.getIncompleteMessage());

                return;
            }

            Espo.Ui.notify(' ...');

            this.appuntamentoModel.save()
                .then(() => {
                    Espo.Ui.notify();
                    super.resolveCancel();
                });
        }

        getTitle() {
            if (this.isAppuntamentoEsitoPopup) {
                return this.notificationData.name ||
                    this.translate(APPUNTAMENTO, 'scopeNames');
            }

            return super.getTitle();
        }
    };
});
