/* global define */

define('custom:views/calendar/modals/edit', [
    'crm:views/calendar/modals/edit',
    'custom:views/appuntamento/helpers/prospect-sync',
], function (CalendarEditModalModule, ProspectSync) {

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

            return ProspectSync.FALLBACK_DURATION_SECONDS;
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

            this.options.dateEnd = ProspectSync.computeDateEnd(
                this,
                this.options.dateStart,
                this.getDefaultDurationSeconds()
            );
        }

        createRecordView(model, callback) {
            this.patchAppuntamentoDurationOptions();

            super.createRecordView(model, (view) => {
                ProspectSync.setupProspectSync(view);
                ProspectSync.setupDefaultDuration(view);

                if (typeof callback === 'function') {
                    callback(view);
                }
            });
        }
    };
});
