/* global define */

define('custom:views/calendar/modals/edit', [
    'crm:views/calendar/modals/edit',
    'custom:helpers/appuntamento-prospect-sync',
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

        applyAppuntamentoDurationToModel(model) {
            const dateStart = model.get('dateStart') || this.options.dateStart;

            if (!dateStart) {
                return;
            }

            const seconds = this.getDefaultDurationSeconds();

            model.set({
                dateStart: dateStart,
                dateEnd: ProspectSync.computeDateEnd(this, dateStart, seconds),
            });
        }

        createRecordView(model, callback) {
            this.patchAppuntamentoDurationOptions();

            if (this.shouldPatchAppuntamentoDuration()) {
                this.applyAppuntamentoDurationToModel(model);
            }

            super.createRecordView(model, (view) => {
                if (this.shouldPatchAppuntamentoDuration()) {
                    this.applyAppuntamentoDurationToModel(view.model);

                    view.once('after:render', () => {
                        ProspectSync.refreshDurationField(view);
                    });
                }

                ProspectSync.setupProspectSync(view);

                callback(view);
            });
        }
    };
});
