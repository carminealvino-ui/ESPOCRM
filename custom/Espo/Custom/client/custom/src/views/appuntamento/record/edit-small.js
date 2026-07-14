/* global define */

define('custom:views/appuntamento/record/edit-small', ['views/record/edit-small'], function (Dep) {

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

        /**
         * toMoment() → fuso utente; lo storage Espo è UTC.
         * Senza .utc() Date End risultava +offset (es. 1h30 → 3h30 in estate Roma).
         */
        computeDateEndFromStart: function (dateStart, seconds) {
            const dateTime = this.getDateTime();
            const endMoment = dateTime.toMoment(dateStart).clone().add(seconds, 'seconds');

            return endMoment.clone().utc().format(dateTime.internalDateTimeFullFormat);
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

            this.model.set({
                dateEnd: this.computeDateEndFromStart(dateStart, seconds),
                duration: seconds,
            }, {ui: true});
        },
    });
});
