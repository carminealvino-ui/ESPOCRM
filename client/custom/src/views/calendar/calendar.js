/* global define */

define('custom:views/calendar/calendar', ['crm:views/calendar/calendar'], function (CalendarViewModule) {

    const CalendarView = CalendarViewModule.default || CalendarViewModule;

    return class CustomCalendarView extends CalendarView {

        getDefaultDurationSeconds() {
            const fromMeta = this.getMetadata().get(
                ['entityDefs', 'Appuntamento', 'fields', 'duration', 'default']
            );

            if (fromMeta !== null && fromMeta !== undefined && fromMeta !== '') {
                return parseInt(fromMeta, 10);
            }

            return 5400;
        }

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
                .add(this.getDefaultDurationSeconds(), 'seconds')
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
