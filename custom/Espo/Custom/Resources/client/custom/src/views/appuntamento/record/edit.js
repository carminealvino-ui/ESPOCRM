/* global define */

define('custom:views/appuntamento/record/edit', [
    'views/record/edit',
    'custom:helpers/appuntamento-duration',
], function (Dep, Helper) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            if (!this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            this.listenTo(this.model, 'change:dateStart', () => {
                this.applyDefaultDuration();
            });

            this.once('after:render', () => {
                this.applyDefaultDuration();
            });
        },

        getDefaultDurationSeconds: function () {
            const fromField = this.model.getFieldParam('duration', 'default');

            if (fromField !== null && fromField !== undefined && fromField !== '') {
                return parseInt(fromField, 10);
            }

            const entityType = this.model.entityType || this.model.name;
            const fromMeta = this.getMetadata().get(
                ['entityDefs', entityType, 'fields', 'duration', 'default']
            );

            if (fromMeta !== null && fromMeta !== undefined && fromMeta !== '') {
                return parseInt(fromMeta, 10);
            }

            return Helper.FALLBACK_DURATION_SECONDS;
        },

        applyDefaultDuration: function () {
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

            if (!dateEnd) {
                return;
            }

            this.model.set({
                dateEnd: dateEnd,
            }, {updatedByDuration: true, ui: true});
        },
    });
});
