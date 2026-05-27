/* global define */

define('custom:views/calendar/modals/edit', ['crm:views/calendar/modals/edit'], function (CalendarEditModalModule) {

    const CalendarEditModalView = CalendarEditModalModule.default || CalendarEditModalModule;

    const APPUNTAMENTO_SCOPE = 'Appuntamento';
    const DEFAULT_DURATION_SECONDS = 5400;

    return class CustomCalendarEditModalView extends CalendarEditModalView {

        createRecordView(model, callback) {
            if (
                !this.id &&
                this.scope === APPUNTAMENTO_SCOPE &&
                !this.dateIsChanged &&
                !this.options.allDay &&
                this.options.dateStart
            ) {
                this.options.dateEnd = this.getAppuntamentoDefaultDateEnd(this.options.dateStart);
            }

            super.createRecordView(model, (view) => {
                if (
                    !this.id &&
                    this.scope === APPUNTAMENTO_SCOPE &&
                    !this.dateIsChanged &&
                    !model.get('isAllDay')
                ) {
                    const dateStart = model.get('dateStart');

                    if (dateStart) {
                        model.set({
                            dateEnd: this.getAppuntamentoDefaultDateEnd(dateStart),
                        });
                    }
                }

                callback(view);
            });
        }

        getAppuntamentoDefaultDateEnd(dateStart) {
            return this.getDateTime()
                .toMoment(dateStart)
                .add(DEFAULT_DURATION_SECONDS, 'seconds')
                .format(this.getDateTime().internalDateTimeFormat);
        }
    };
});
