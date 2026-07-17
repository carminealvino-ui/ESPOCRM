/* global define */

define('custom:views/appuntamento/fields/duration', ['views/fields/duration'], function (Dep) {

    return Dep.extend({

        _enforcingDefaultDuration: false,

        getDefaultDurationSeconds: function () {
            const fromField = this.model.getFieldParam(this.name, 'default');

            if (fromField !== null && fromField !== undefined && fromField !== '') {
                return parseInt(fromField, 10);
            }

            return 5400;
        },

        getDateTimeUnix: function (value) {
            if (!value) {
                return null;
            }

            return this.getDateTime().toMoment(value).unix();
        },

        shouldEnforceDefaultDuration: function () {
            return this.model.isNew() && !this.model.get('isAllDay');
        },

        needsDefaultDateEnd: function () {
            if (!this.shouldEnforceDefaultDuration()) {
                return false;
            }

            const dateStart = this.model.get(this.startField);

            if (!dateStart) {
                return false;
            }

            const defaultSeconds = this.getDefaultDurationSeconds();
            const currentEnd = this.model.get(this.endField);

            if (!currentEnd) {
                return true;
            }

            const diff = this.getDateTimeUnix(currentEnd) - this.getDateTimeUnix(dateStart);

            return diff !== defaultSeconds;
        },

        wireEndFieldView: function () {
            if (this.endFieldView) {
                return;
            }

            const recordView = this.findRecordView();

            if (!recordView || typeof recordView.getFieldView !== 'function') {
                return;
            }

            this.endFieldView = recordView.getFieldView(this.endField);
        },

        findRecordView: function () {
            let parent = this.getParentView && this.getParentView();

            while (parent) {
                if (typeof parent.getFieldView === 'function') {
                    return parent;
                }

                parent = parent.getParentView && parent.getParentView();
            }

            return null;
        },

        enforceDefaultDuration: function () {
            if (!this.shouldEnforceDefaultDuration() || this._enforcingDefaultDuration) {
                return;
            }

            const dateStart = this.model.get(this.startField);

            if (!dateStart) {
                this.seconds = this.getDefaultDurationSeconds();

                return;
            }

            if (!this.needsDefaultDateEnd()) {
                this.seconds = this.getDefaultDurationSeconds();

                return;
            }

            this._enforcingDefaultDuration = true;

            try {
                this.seconds = this.getDefaultDurationSeconds();
                this.wireEndFieldView();
                Dep.prototype.updateDateEnd.call(this, this.name);
            } finally {
                this._enforcingDefaultDuration = false;
            }
        },

        calculateSeconds: function () {
            if (this.shouldEnforceDefaultDuration()) {
                this.seconds = this.getDefaultDurationSeconds();

                const dateStart = this.model.get(this.startField);
                const dateEnd = this.model.get(this.endField);

                if (dateStart && dateEnd) {
                    const diff = this.getDateTimeUnix(dateEnd) - this.getDateTimeUnix(dateStart);

                    if (diff !== this.seconds) {
                        this.enforceDefaultDuration();

                        return;
                    }
                } else if (dateStart) {
                    this.enforceDefaultDuration();

                    return;
                }

                return;
            }

            Dep.prototype.calculateSeconds.call(this);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (!this.shouldEnforceDefaultDuration()) {
                return;
            }

            this.wireEndFieldView();

            const run = () => {
                this.enforceDefaultDuration();

                if (typeof this.updateDuration === 'function') {
                    this.updateDuration();
                }
            };

            run();
            setTimeout(run, 150);
            setTimeout(run, 500);
            setTimeout(run, 1200);
        },
    });
});
