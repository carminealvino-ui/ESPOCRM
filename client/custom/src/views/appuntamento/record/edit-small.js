/* global define */

define('custom:views/appuntamento/record/edit-small', ['views/record/edit-small'], function (Dep) {

    const DEFAULT_DURATION_SECONDS = 5400;

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            if (!this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            this.listenTo(this.model, 'change:dateStart', () => {
                this.applyDefaultDuration();
            });

            this.listenTo(this.model, 'change:dateEnd', () => {
                if (this._applyingDefaultDuration) {
                    return;
                }

                this.applyDefaultDuration();
            });

            this.once('after:render', () => {
                this.applyDefaultDuration();
                setTimeout(() => this.applyDefaultDuration(), 300);
                setTimeout(() => this.applyDefaultDuration(), 1000);
            });
        },

        getExpectedDateEnd: function (dateStart) {
            return this.getDateTime()
                .toMoment(dateStart)
                .add(DEFAULT_DURATION_SECONDS, 'seconds')
                .format(this.getDateTime().internalDateTimeFormat);
        },

        applyDefaultDuration: function () {
            if (!this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            const dateStart = this.model.get('dateStart');

            if (!dateStart) {
                return;
            }

            const expectedEnd = this.getExpectedDateEnd(dateStart);
            const currentEnd = this.model.get('dateEnd');

            if (currentEnd === expectedEnd) {
                return;
            }

            this._applyingDefaultDuration = true;

            try {
                this.model.set({
                    dateEnd: expectedEnd,
                    duration: DEFAULT_DURATION_SECONDS,
                }, {ui: true, updatedByDuration: true});
            } finally {
                this._applyingDefaultDuration = false;
            }
        },
    });
});
