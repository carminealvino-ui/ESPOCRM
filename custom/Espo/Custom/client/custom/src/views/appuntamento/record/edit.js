/* global define */

define('custom:views/appuntamento/record/edit', ['views/record/edit'], function (Dep) {

    const FALLBACK_DURATION_SECONDS = 5400;

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

            return FALLBACK_DURATION_SECONDS;
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
            const dateEnd = this.getDateTime()
                .toMoment(dateStart)
                .add(seconds, 'seconds')
                .format(this.getDateTime().internalDateTimeFormat);

            this.model.set({
                dateEnd: dateEnd,
                duration: seconds,
            }, {ui: true});
        },
    });
});
