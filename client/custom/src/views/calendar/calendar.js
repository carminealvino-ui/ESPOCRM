/* global define */

define('custom:views/calendar/calendar', [
    'custom:handlers/calendar-default-duration',
    'crm:views/calendar/calendar',
], function (_calendarDurationHandler, CalendarViewModule) {

    const CalendarView = CalendarViewModule.default || CalendarViewModule;
    const DEFAULT_DURATION_SECONDS = 5400;

    return class CustomCalendarView extends CalendarView {

        normalizeCreateEventValues(values) {
            if (!values || values.allDay || !values.dateStart) {
                return values;
            }

            const next = {...values};

            next.dateEnd = this.getDefaultDateEnd(next.dateStart);

            return next;
        }

        getDefaultDateEnd(dateStart) {
            return this.getDateTime()
                .toMoment(dateStart)
                .add(DEFAULT_DURATION_SECONDS, 'seconds')
                .format(this.getDateTime().internalDateTimeFormat);
        }

        async createEvent(values) {
            values = this.normalizeCreateEventValues(values || {});

            const originalCreateView = this.createView.bind(this);

            this.createView = (name, viewName, options) => {
                if (name === 'dialog' && viewName === 'crm:views/calendar/modals/edit') {
                    viewName = 'custom:views/calendar/modals/edit';
                }

                return originalCreateView(name, viewName, options);
            };

            try {
                return await super.createEvent(values);
            } finally {
                this.createView = originalCreateView;
            }
        }
    };
});
