/* global define */

define('custom:views/calendar/calendar', [
    'crm:views/calendar/calendar',
    'custom:helpers/appuntamento-duration',
], function (CalendarViewModule, Helper) {

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

            return Helper.FALLBACK_DURATION_SECONDS;
        }

        getDefaultDateEnd(dateStart) {
            return Helper.addSecondsToSystemDateTime(
                this.getDateTime(),
                dateStart,
                this.getDefaultDurationSeconds()
            );
        }

        normalizeCreateEventValues(values) {
            if (!values || values.allDay || !values.dateStart) {
                return values;
            }

            const dateEnd = this.getDefaultDateEnd(values.dateStart);

            if (!dateEnd) {
                return values;
            }

            return {
                ...values,
                dateEnd: dateEnd,
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
