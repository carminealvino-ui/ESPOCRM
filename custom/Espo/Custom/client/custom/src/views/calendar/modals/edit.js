/* global define */

/**
 * Calendario: pre-compila Utente Assegnato + forza durata 1h30 (dateEnd).
 */
define('custom:views/calendar/modals/edit', [
    'crm:views/calendar/modals/edit',
    'custom:helpers/appuntamento-duration',
], function (CalendarEditModalModule, AppuntamentoDurationHelper) {

    const Dep = CalendarEditModalModule.default || CalendarEditModalModule;
    const DEFAULT_DURATION_SECONDS = 5400;

    return Dep.extend({

        setupLate: async function () {
            if (Dep.prototype.setupLate) {
                await Dep.prototype.setupLate.call(this);
            }

            this.ensureCalendarAssignee();
            this.ensureCalendarDefaultDuration();
        },

        createRecordView: function (model, callback) {
            Dep.prototype.createRecordView.call(this, model, view => {
                this.ensureCalendarAssignee();
                this.ensureCalendarDefaultDuration();

                if (view && typeof view.applyDefaultDuration === 'function') {
                    view.applyDefaultDuration();
                }

                if (typeof callback === 'function') {
                    callback(view);
                }
            });
        },

        ensureCalendarAssignee: function () {
            if (this.id || !this.model) {
                return;
            }

            const model = this.model;
            const userId = this.resolveCalendarAssigneeUserId();
            const userName = this.resolveCalendarAssigneeUserName(userId);

            if (!userId) {
                return;
            }

            if (model.hasField('assignedUsers')) {
                const ids = model.get('assignedUsersIds') || [];

                if (ids.length) {
                    if (!model.get('assignedUserId')) {
                        model.set({
                            assignedUserId: ids[0],
                            assignedUserName: (model.get('assignedUsersNames') || {})[ids[0]] || userName,
                        }, {ui: true});
                    }

                    return;
                }

                const names = model.get('assignedUsersNames') || {};
                names[userId] = userName;

                model.set({
                    assignedUsersIds: [userId],
                    assignedUsersNames: names,
                    assignedUserId: userId,
                    assignedUserName: userName,
                }, {ui: true});

                return;
            }

            if (model.hasField('assignedUser') && !model.get('assignedUserId')) {
                model.set({
                    assignedUserId: userId,
                    assignedUserName: userName,
                }, {ui: true});
            }
        },

        ensureCalendarDefaultDuration: function () {
            if (this.id || !this.model || !this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            if (this.model.entityType && this.model.entityType !== 'Appuntamento') {
                return;
            }

            const dateStart = this.model.get('dateStart');

            if (!dateStart) {
                return;
            }

            const dateEnd = AppuntamentoDurationHelper.addSecondsToSystemDateTime(
                this.getDateTime(),
                dateStart,
                DEFAULT_DURATION_SECONDS
            );

            if (!dateEnd || this.model.get('dateEnd') === dateEnd) {
                return;
            }

            this.model.set({
                dateEnd: dateEnd,
            }, {updatedByDuration: true, ui: true});
        },

        resolveCalendarAssigneeUserId: function () {
            const attributes = this.options.attributes || {};

            if (attributes.assignedUserId) {
                return attributes.assignedUserId;
            }

            if (this.options.userId) {
                return this.options.userId;
            }

            return this.getUser().id;
        },

        resolveCalendarAssigneeUserName: function (userId) {
            const attributes = this.options.attributes || {};

            if (attributes.assignedUserName) {
                return attributes.assignedUserName;
            }

            if (this.options.userName && this.options.userId === userId) {
                return this.options.userName;
            }

            if (userId === this.getUser().id) {
                return this.getUser().get('name');
            }

            return userId;
        },
    });
});
