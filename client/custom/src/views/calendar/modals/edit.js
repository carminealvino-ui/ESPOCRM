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

            this.once('after:render', () => {
                this.applyDefaultDurationToEditView();
            });
        }

        createRecordView(model, callback) {
            this.applyDefaultDurationOptions();

            super.createRecordView(model, (view) => {
                this.applyDefaultDurationToModel(model);
                ProspectSync.setupDefaultDuration(view);
                ProspectSync.setupProspectSync(view);

                callback(view);

                this.applyDefaultDurationToEditView();
            });
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

        applyDefaultDurationOptions() {
            if (!this.shouldApplyDefaultDuration()) {
                return;
            }

            this.options.dateEnd = this.getAppuntamentoDefaultDateEnd(this.options.dateStart);
        }

        applyDefaultDurationToModel(model) {
            if (!this.shouldApplyDefaultDuration()) {
                return;
            }

            const dateStart = model.get('dateStart') || this.options.dateStart;

            if (!dateStart) {
                return;
            }

            const seconds = this.getDefaultDurationSeconds();

            model.set({
                dateEnd: this.getAppuntamentoDefaultDateEnd(dateStart),
                duration: seconds,
            }, {defaultDuration: true});
        }

        applyDefaultDurationToEditView() {
            if (!this.shouldApplyDefaultDuration()) {
                return;
            }

            const editView = this.hasView('edit') ? this.getView('edit') : null;

            if (!editView || !editView.model) {
                return;
            }

            this.applyDefaultDurationToModel(editView.model);
        }

        shouldApplyDefaultDuration() {
            if (this.options.allDay) {
                return false;
            }

            if (this.scope !== APPUNTAMENTO_SCOPE) {
                return false;
            }

            if (!this.options.dateStart) {
                return false;
            }

            // Nuovo appuntamento da calendario: sovrascrive la durata dello slot
            return !this.id;
        }

        getAppuntamentoDefaultDateEnd(dateStart) {
            return this.getDateTime()
                .toMoment(dateStart)
                .add(this.getDefaultDurationSeconds(), 'seconds')
                .format(this.getDateTime().internalDateTimeFormat);
        }
    };
});
