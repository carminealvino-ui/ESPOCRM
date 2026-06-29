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

        applyAppuntamentoDurationToRecordView(view) {
            if (!view || !view.model || !this.shouldPatchAppuntamentoDuration()) {
                return;
            }

            const dateStart = view.model.get('dateStart') || this.options.dateStart;

            if (!dateStart) {
                return;
            }

            const seconds = this.getDefaultDurationSeconds();
            const dateEnd = this.computeDateEnd(dateStart, seconds);

            view.model.set('dateEnd', dateEnd, {
                updatedByDuration: true,
                fromField: 'duration',
            });

            const durationView = view.getFieldView && view.getFieldView('duration');

            if (durationView) {
                durationView.seconds = seconds;

                if (typeof durationView.updateDuration === 'function') {
                    durationView.updateDuration();
                }
            }
        }

        scheduleAppuntamentoDurationFix(view) {
            const run = () => this.applyAppuntamentoDurationToRecordView(view);

            run();

            if (view && typeof view.once === 'function') {
                view.once('after:render', run);
            }

            [300, 800, 1500].forEach(delay => {
                setTimeout(run, delay);
            });
        }

        createRecordView(model, callback) {
            this.patchAppuntamentoDurationOptions();

            super.createRecordView(model, (view) => {
                if (!this.id && !this.dateIsChanged && this.shouldPatchAppuntamentoDuration()) {
                    const dateStart = this.options.dateStart;
                    const dateEnd = this.options.dateEnd;

                    if (dateStart && dateEnd) {
                        model.set('dateStart', dateStart);
                        model.set('dateEnd', dateEnd, {
                            updatedByDuration: true,
                            fromField: 'duration',
                        });
                    }
                }

                this.scheduleAppuntamentoDurationFix(view);

                if (typeof callback === 'function') {
                    callback(view);
                }
            });
        }
    };
});
