/* global define */

define('custom:handlers/calendar-default-duration', [
    'crm:views/calendar/calendar',
    'crm:views/calendar/modals/edit',
], function (CalendarViewModule, CalendarEditModalModule) {

    const CalendarView = CalendarViewModule.default || CalendarViewModule;
    const CalendarEditModalView = CalendarEditModalModule.default || CalendarEditModalModule;

    const DEFAULT_DURATION_SECONDS = 5400;
    const APPUNTAMENTO_SCOPE = 'Appuntamento';

    const buildDateEnd = function (dateStart, dateTimeUtil) {
        return dateTimeUtil
            .toMoment(dateStart)
            .add(DEFAULT_DURATION_SECONDS, 'seconds')
            .format(dateTimeUtil.internalDateTimeFormat);
    };

    if (!CalendarView.prototype.__mecDefaultDurationPatched) {
        CalendarView.prototype.__mecDefaultDurationPatched = true;

        const originalCreateEvent = CalendarView.prototype.createEvent;

        CalendarView.prototype.createEvent = async function (values) {
            values = values || {};

            if (!values.allDay && values.dateStart) {
                values = {...values};
                values.dateEnd = buildDateEnd(values.dateStart, this.getDateTime());
            }

            return originalCreateEvent.call(this, values);
        };
    }

    if (!CalendarEditModalView.prototype.__mecDefaultDurationPatched) {
        CalendarEditModalView.prototype.__mecDefaultDurationPatched = true;

        const originalCreateRecordView = CalendarEditModalView.prototype.createRecordView;

        CalendarEditModalView.prototype.createRecordView = function (model, callback) {
            if (
                !this.id &&
                this.scope === APPUNTAMENTO_SCOPE &&
                !this.dateIsChanged &&
                !this.options.allDay &&
                this.options.dateStart
            ) {
                this.options.dateEnd = buildDateEnd(
                    this.options.dateStart,
                    this.getDateTime()
                );
            }

            originalCreateRecordView.call(this, model, (view) => {
                if (
                    !this.id &&
                    this.scope === APPUNTAMENTO_SCOPE &&
                    !this.dateIsChanged &&
                    !model.get('isAllDay')
                ) {
                    const dateStart = model.get('dateStart');

                    if (dateStart) {
                        model.set({
                            dateEnd: buildDateEnd(dateStart, this.getDateTime()),
                        }, {updatedByDuration: true});
                    }
                }

                callback(view);
            });
        };
    }
});
