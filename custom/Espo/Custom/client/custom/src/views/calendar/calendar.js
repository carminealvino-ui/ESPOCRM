/* global define */

define('custom:views/calendar/calendar', ['crm:views/calendar/calendar'], function (CalendarViewModule) {

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

        /**
         * toMoment() → fuso utente; storage Espo = UTC.
         * Senza .utc() Date End +offset (1h30 → 3h30 estate Roma).
         */
        getDefaultDateEnd(dateStart) {
            const dateTime = this.getDateTime();
            const endMoment = dateTime
                .toMoment(dateStart)
                .clone()
                .add(this.getDefaultDurationSeconds(), 'seconds');

            return endMoment.clone().utc().format(dateTime.internalDateTimeFullFormat);
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
