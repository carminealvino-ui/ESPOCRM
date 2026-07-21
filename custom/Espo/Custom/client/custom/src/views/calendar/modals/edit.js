/* global define */

/**
 * Calendario: pre-compila Utente Assegnato (assignedUsers) alla creazione.
 * Appuntamento usa assignedUsers; il core calendario passa solo assignedUserId.
 */
define('custom:views/calendar/modals/edit', ['crm:views/calendar/modals/edit'], function (CalendarEditModalModule) {

    const Dep = CalendarEditModalModule.default || CalendarEditModalModule;

    return Dep.extend({

        setupLate: async function () {
            if (Dep.prototype.setupLate) {
                await Dep.prototype.setupLate.call(this);
            }

            this.ensureCalendarAssignee();
        },

        createRecordView: function (model, callback) {
            Dep.prototype.createRecordView.call(this, model, view => {
                this.ensureCalendarAssignee();

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
