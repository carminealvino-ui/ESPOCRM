/* global define */

define('custom:views/calendar/calendar', [
    'crm:views/calendar/calendar',
    'helpers/record-modal',
], function (CalendarViewModule, RecordModalModule) {

    const CalendarView = CalendarViewModule.default || CalendarViewModule;
    const RecordModal = RecordModalModule.default || RecordModalModule;
    const APPUNTAMENTO_SCOPE = 'Appuntamento';

    return class CustomCalendarView extends CalendarView {

        getDefaultDurationSeconds() {
            const fromMeta = this.getMetadata().get(
                ['entityDefs', APPUNTAMENTO_SCOPE, 'fields', 'duration', 'default']
            );

            if (fromMeta !== null && fromMeta !== undefined && fromMeta !== '') {
                return parseInt(fromMeta, 10);
            }

            return 5400;
        }

        getDefaultDateEnd(dateStart) {
            return this.getDateTime()
                .toMoment(dateStart)
                .add(this.getDefaultDurationSeconds(), 'seconds')
                .format(this.getDateTime().internalDateTimeFormat);
        }

        normalizeCreateEventValues(values) {
            if (!values || values.allDay || !values.dateStart) {
                return values;
            }

            return {
                ...values,
                dateEnd: this.getDefaultDateEnd(values.dateStart),
            };
        }

        async createView(name, viewName, options) {
            if (name === 'dialog' && viewName === 'crm:views/calendar/modals/edit') {
                viewName = 'custom:views/calendar/modals/edit';
            }

            return super.createView(name, viewName, options);
        }

        async createEvent(values) {
            return super.createEvent(this.normalizeCreateEventValues(values || {}));
        }

        afterRender() {
            super.afterRender();

            if (!this.calendar) {
                return;
            }

            this.calendar.setOption('eventClick', info => {
                this.handleEventClick(info);
            });
        }

        async handleEventClick(info) {
            const event = info.event;
            const scope = event.extendedProps.scope;
            const recordId = event.extendedProps.recordId;

            if (scope === APPUNTAMENTO_SCOPE) {
                await this.openAppuntamentoEsitoModal(recordId);

                return;
            }

            await this.openDefaultEventModal(scope, recordId);
        }

        async openAppuntamentoEsitoModal(recordId) {
            const helper = new RecordModal();

            let modalView;

            modalView = await helper.showEdit(this, {
                entityType: APPUNTAMENTO_SCOPE,
                id: recordId,
                layoutName: 'detailEsito',
                beforeRender: view => {
                    const name = view.model.get('name') || view.model.id;

                    view.headerText = this.translate('Esito', 'labels', 'Appuntamento') + ' — ' + name;
                },
                beforeSave: () => {
                    if (this.options.onSave) {
                        this.options.onSave();
                    }
                },
                afterSave: (model, o) => {
                    if (this.options.onSave) {
                        this.options.onSave();
                    }

                    this.updateModel(model);

                    if (!o.bypassClose) {
                        modalView.close();
                    }
                },
            });
        }

        async openDefaultEventModal(scope, recordId) {
            const helper = new RecordModal();

            let modalView;

            modalView = await helper.showDetail(this, {
                entityType: scope,
                id: recordId,
                removeDisabled: false,
                beforeSave: () => {
                    if (this.options.onSave) {
                        this.options.onSave();
                    }
                },
                beforeDestroy: () => {
                    if (this.options.onSave) {
                        this.options.onSave();
                    }
                },
                afterSave: (model, o) => {
                    if (!o.bypassClose) {
                        modalView.close();
                    }

                    this.updateModel(model);
                },
                afterDestroy: model => {
                    this.removeModel(model);
                },
            });
        }
    };
});
