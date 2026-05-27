/* global define */

define('custom:views/calendar/modals/edit', [
    'custom:handlers/calendar-default-duration',
    'crm:views/calendar/modals/edit',
], function (_calendarDurationHandler, CalendarEditModalModule) {

    const CalendarEditModalView = CalendarEditModalModule.default || CalendarEditModalModule;

    const APPUNTAMENTO_SCOPE = 'Appuntamento';
    const DEFAULT_DURATION_SECONDS = 5400;

    return class CustomCalendarEditModalView extends CalendarEditModalView {

        setup() {
            super.setup();

            this.once('after:render', () => {
                this.applyDefaultDurationToEditView();
            });
        }

        createRecordView(model, callback) {
            this.applyDefaultDurationOptions();

            super.createRecordView(model, (view) => {
                this.applyDefaultDurationToModel(model);

                callback(view);

                this.applyDefaultDurationToEditView();
            });
        }

        applyDefaultDurationOptions() {
            if (!this.shouldApplyDefaultDuration()) {
                return;
            }

            this.options.dateEnd = this.getAppuntamentoDefaultDateEnd(this.options.dateStart);
        }

        applyDefaultDurationToModel(model) {
            if (!this.shouldApplyDefaultDuration()) {
                return;
            }

            const dateStart = model.get('dateStart') || this.options.dateStart;

            if (!dateStart) {
                return;
            }

            model.set({
                dateEnd: this.getAppuntamentoDefaultDateEnd(dateStart),
            }, {updatedByDuration: true});
        }

        applyDefaultDurationToEditView() {
            if (!this.shouldApplyDefaultDuration()) {
                return;
            }

            const editView = this.hasView('edit') ? this.getView('edit') : null;

            if (!editView || !editView.model) {
                return;
            }

            this.applyDefaultDurationToModel(editView.model);
        }

        shouldApplyDefaultDuration() {
            if (this.id || this.dateIsChanged || this.options.allDay) {
                return false;
            }

            if (this.scope !== APPUNTAMENTO_SCOPE) {
                return false;
            }

            return Boolean(this.options.dateStart);
        }

        getAppuntamentoDefaultDateEnd(dateStart) {
            return this.getDateTime()
                .toMoment(dateStart)
                .add(DEFAULT_DURATION_SECONDS, 'seconds')
                .format(this.getDateTime().internalDateTimeFormat);
        }
    };
});
