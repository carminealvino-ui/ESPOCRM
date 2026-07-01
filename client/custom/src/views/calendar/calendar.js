/* global define */

define('custom:views/calendar/calendar', ['crm:views/calendar/calendar', 'moment'], function (CalendarViewModule, moment) {

    const CalendarView = CalendarViewModule.default || CalendarViewModule;
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
            const endUnix = moment.utc(dateStart).unix() + this.getDefaultDurationSeconds();

            return moment.unix(endUnix).utc().format(this.getDateTime().internalDateTimeFormat);
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
    };
});
