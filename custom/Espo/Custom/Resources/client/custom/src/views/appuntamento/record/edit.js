/* global define */

define('custom:views/appuntamento/record/edit', [
    'crm:views/meeting/record/edit',
    'custom:helpers/appuntamento-duration',
], function (MeetingEditModule, Helper) {

    const Parent = MeetingEditModule.default || MeetingEditModule;

    return class AppuntamentoEditView extends Parent {

        setup() {
            super.setup();

            if (!this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            this.listenTo(this.model, 'change:dateStart', () => {
                this.applyDefaultDuration();
            });

            this.once('after:render', () => {
                this.applyDefaultDuration();
            });
        }

        getDefaultDurationSeconds() {
            const fromField = this.model.getFieldParam('duration', 'default');

            if (fromField !== null && fromField !== undefined && fromField !== '') {
                return parseInt(fromField, 10);
            }

            const fromMeta = this.getMetadata().get(
                ['entityDefs', 'Appuntamento', 'fields', 'duration', 'default']
            );

            if (fromMeta !== null && fromMeta !== undefined && fromMeta !== '') {
                return parseInt(fromMeta, 10);
            }

            return Helper.FALLBACK_DURATION_SECONDS;
        }

        applyDefaultDuration() {
            if (!this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            const dateStart = this.model.get('dateStart');

            if (!dateStart) {
                return;
            }

            const seconds = this.getDefaultDurationSeconds();
            const dateEnd = Helper.addSecondsToSystemDateTime(
                this.getDateTime(),
                dateStart,
                seconds
            );

            if (!dateEnd || dateEnd === this.model.get('dateEnd')) {
                return;
            }

            this.model.set({
                dateEnd: dateEnd,
            }, {updatedByDuration: true, ui: true});
        }
    };
});
