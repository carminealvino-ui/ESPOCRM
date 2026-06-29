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

        getExpectedDateEnd: function (dateStart, seconds) {
            return this.getDateTime()
                .toMoment(dateStart)
                .add(seconds, 'seconds')
                .format(this.getDateTime().internalDateTimeFormat);
        },

        getDateTimeUnix: function (value) {
            if (!value) {
                return null;
            }

            return this.getDateTime().toMoment(value).unix();
        },

        isSameDateTime: function (left, right) {
            const leftUnix = this.getDateTimeUnix(left);
            const rightUnix = this.getDateTimeUnix(right);

            if (leftUnix === null || rightUnix === null) {
                return left === right;
            }

            return leftUnix === rightUnix;
        },

        shouldEnforceDefaultDuration: function () {
            return this.model.isNew() && !this.model.get('isAllDay');
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

        reRenderDateEndField: function () {
            const recordView = this.findRecordView();

            if (!recordView) {
                return;
            }

            const dateEndView = recordView.getFieldView(this.endField);

            if (dateEndView && typeof dateEndView.reRender === 'function') {
                dateEndView.reRender();
            }
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

            const defaultSeconds = this.getDefaultDurationSeconds();
            const expectedEnd = this.getExpectedDateEnd(dateStart, defaultSeconds);
            const currentEnd = this.model.get(this.endField);

            this.seconds = defaultSeconds;

            if (this.isSameDateTime(currentEnd, expectedEnd)) {
                return;
            }

            this._enforcingDefaultDuration = true;

            try {
                this.model.set({
                    [this.endField]: expectedEnd,
                    duration: defaultSeconds,
                }, {ui: true, updatedByDuration: true});
            } finally {
                this._enforcingDefaultDuration = false;
            }

            this.reRenderDateEndField();
        },

        calculateSeconds: function () {
            if (this.shouldEnforceDefaultDuration()) {
                this.enforceDefaultDuration();

                return;
            }

            Dep.prototype.calculateSeconds.call(this);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (!this.shouldEnforceDefaultDuration()) {
                return;
            }

            const run = () => {
                this.enforceDefaultDuration();

                if (typeof this.updateDuration === 'function') {
                    this.updateDuration();
                }
            };

            run();
            setTimeout(run, 300);
            setTimeout(run, 1200);
        },
    });
});
