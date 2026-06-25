/* global define */

define('custom:views/calendar/modals/edit', ['crm:views/calendar/modals/edit'], function (CalendarEditModalModule) {

    const CalendarEditModalView = CalendarEditModalModule.default || CalendarEditModalModule;
    const APPUNTAMENTO_SCOPE = 'Appuntamento';
    const FALLBACK_DURATION_SECONDS = 5400;

    return class CustomCalendarEditModalView extends CalendarEditModalView {

        setup() {
            super.setup();
            this.patchAppuntamentoDurationOptions();
        }

        getDefaultDurationSeconds() {
            const fromMeta = this.getMetadata().get(
                ['entityDefs', APPUNTAMENTO_SCOPE, 'fields', 'duration', 'default']
            );

            if (fromMeta !== null && fromMeta !== undefined && fromMeta !== '') {
                return parseInt(fromMeta, 10);
            }

            return FALLBACK_DURATION_SECONDS;
        }

        computeDateEnd(dateStart, seconds) {
            return this.getDateTime()
                .toMoment(dateStart)
                .add(seconds, 'seconds')
                .format(this.getDateTime().internalDateTimeFormat);
        }

        getActiveScope() {
            return this.scope || this.options.scope;
        }

        shouldPatchAppuntamentoDuration() {
            return !this.id &&
                !this.options.allDay &&
                Boolean(this.options.dateStart) &&
                this.getActiveScope() === APPUNTAMENTO_SCOPE;
        }

        patchAppuntamentoDurationOptions() {
            if (!this.shouldPatchAppuntamentoDuration()) {
                return;
            }

            this.options.dateEnd = this.computeDateEnd(
                this.options.dateStart,
                this.getDefaultDurationSeconds()
            );
        }

        createRecordView(model, callback) {
            this.patchAppuntamentoDurationOptions();

            super.createRecordView(model, (view) => {
                if (typeof callback === 'function') {
                    callback(view);
                }
            });
        }
    };
});
