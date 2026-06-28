/* global define */

define('custom:views/appuntamento/record/edit-small', ['views/record/edit'], function (EditModule) {

    const Parent = EditModule.default || EditModule;
    const DEFAULT_DURATION_SECONDS = 5400;

    return class AppuntamentoEditSmallView extends Parent {

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
                setTimeout(() => this.applyDefaultDuration(), 0);
                setTimeout(() => this.applyDefaultDuration(), 150);
            });
        }

        applyDefaultDuration() {
            if (!this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            const dateStart = this.model.get('dateStart');

            if (!dateStart) {
                return;
            }

            const dateEnd = this.getDateTime()
                .toMoment(dateStart)
                .add(DEFAULT_DURATION_SECONDS, 'seconds')
                .format(this.getDateTime().internalDateTimeFormat);

            this.model.set({
                dateEnd: dateEnd,
            }, {updatedByDuration: true});
        }
    };
});
