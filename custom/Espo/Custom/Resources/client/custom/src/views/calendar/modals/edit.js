/* global define */

define('custom:views/calendar/modals/edit', [
    'crm:views/calendar/modals/edit',
    'custom:helpers/appuntamento-duration',
], function (CalendarEditModalModule, Helper) {

    const CalendarEditModalView = CalendarEditModalModule.default || CalendarEditModalModule;
    const APPUNTAMENTO_SCOPE = 'Appuntamento';

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

            return Helper.FALLBACK_DURATION_SECONDS;
        }

        computeDateEnd(dateStart, seconds) {
            return Helper.addSecondsToSystemDateTime(this.getDateTime(), dateStart, seconds);
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

            const dateEnd = this.computeDateEnd(
                this.options.dateStart,
                this.getDefaultDurationSeconds()
            );

            if (dateEnd) {
                this.options.dateEnd = dateEnd;
            }
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
