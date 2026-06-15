/* global define, Espo */

define('custom:views/notification/badge', ['views/notification/badge'], function (BadgeModule) {

    const Parent = BadgeModule.default || BadgeModule;

    return class QueuedNotificationBadgeView extends Parent {

        setup() {
            super.setup();

            this.popupDisplayQueue = [];
            this.popupDisplayActive = false;
        }

        getPopupNotificationView(id) {
            return this.getView('popup-' + id);
        }

        getCollapsedStorageKey(id) {
            return 'popupNotificationCollapsed-' + id;
        }

        markPopupRemoved(id) {
            const index = this.shownNotificationIds.indexOf(id);

            if (index > -1) {
                this.shownNotificationIds.splice(index, 1);
            }

            if (this.shownNotificationIds.length === 0) {
                this.$popupContainer.addClass('hidden');
            }

            this.closedNotificationIds.push(id);
        }

        checkBypass() {
            const last = this.getRouter().getLast() || {};
            const pageAction = (last.options || {}).page || null;

            if (
                last.controller === 'Admin' &&
                last.action === 'page' &&
                ['upgrade', 'extensions'].includes(pageAction)
            ) {
                return true;
            }

            return false;
        }

        collapsePopupNotification(id, silent = false) {
            const view = this.getPopupNotificationView(id);

            if (!view) {
                return;
            }

            if (!silent || !view.isCollapsed) {
                this.modalBarProvider.get()?.addModalView(view, {
                    title: view.getTitle() ?? this.translate('Notification'),
                });
            }

            if (silent) {
                view.makeCollapsed();

                return;
            }

            localStorage.setItem('messageCollapsePopupNotificationId', id);
            this.getStorage().set('state', this.getCollapsedStorageKey(id), true);
        }

        getPopupSortDate(data) {
            const notificationData = data.data || {};
            const dateField = notificationData.dateField || 'dateStart';
            const attributes = notificationData.attributes || {};

            return attributes[dateField] || attributes.dateStart || '';
        }

        sortPopupItems(items) {
            return items.slice().sort((a, b) => {
                return this.getPopupSortDate(a).localeCompare(this.getPopupSortDate(b));
            });
        }

        buildPopupQueueKey(name, data) {
            const notificationId = data.id || null;

            if (notificationId) {
                return name + '_' + notificationId;
            }

            const entityId = (data.data && data.data.id) || '';

            return name + '_entity_' + entityId;
        }

        enqueuePopupNotification(name, data, isNotFirstCheck = false) {
            const notificationId = data.id || null;

            if (notificationId) {
                const id = name + '_' + notificationId;

                if (this.shownNotificationIds.includes(id)) {
                    const notificationView = this.getPopupNotificationView(id);

                    if (notificationView) {
                        notificationView.trigger('update-data', data.data);
                    }

                    return;
                }

                if (this.closedNotificationIds.includes(notificationId)) {
                    return;
                }
            }

            const key = this.buildPopupQueueKey(name, data);
            const exists = this.popupDisplayQueue.some(item => item.key === key);

            if (exists) {
                return;
            }

            this.popupDisplayQueue.push({
                key: key,
                name: name,
                data: data,
                isNotFirstCheck: isNotFirstCheck,
            });

            this.popupDisplayQueue.sort((a, b) => {
                return this.getPopupSortDate(a.data).localeCompare(this.getPopupSortDate(b.data));
            });

            this.processPopupDisplayQueue();
        }

        onPopupDisplayFinished() {
            this.popupDisplayActive = false;
            this.processPopupDisplayQueue();
        }

        processPopupDisplayQueue() {
            if (this.popupDisplayActive || !this.popupDisplayQueue.length) {
                return;
            }

            const item = this.popupDisplayQueue.shift();

            this.popupDisplayActive = true;

            this.displayPopupNotificationNow(item.name, item.data, item.isNotFirstCheck)
                .catch(() => {
                    this.onPopupDisplayFinished();
                });
        }

        showPopupNotification(name, data, isNotFirstCheck = false) {
            this.enqueuePopupNotification(name, data, isNotFirstCheck);
        }

        async displayPopupNotificationNow(name, data, isNotFirstCheck = false) {
            const viewName = this.popupNotificationsData[name].view;

            if (!viewName) {
                this.onPopupDisplayFinished();

                return;
            }

            let id;

            const notificationId = data.id || null;

            if (notificationId) {
                id = name + '_' + notificationId;
            } else {
                id = this.lastId++;
            }

            this.shownNotificationIds.push(id);

            const view = await this.createView('popup-' + id, viewName, {
                notificationData: data.data ?? {},
                notificationId: data.id,
                id: id,
                isFirstCheck: !isNotFirstCheck,
                onCollapse: () => {
                    this.collapsePopupNotification(id);
                },
                onExpand: () => {
                    this.expandPopupNotification(id);
                },
            });

            this.$popupContainer.removeClass('hidden');

            this.listenTo(view, 'remove', () => {
                this.markPopupRemoved(id);

                localStorage.setItem('messageClosePopupNotificationId', id);
                this.onPopupDisplayFinished();
            });

            await view.render();

            if (data.id && this.getStorage().get('state', this.getCollapsedStorageKey(id))) {
                this.collapsePopupNotification(id, true);
            }
        }

        checkGroupedPopupNotifications() {
            if (!this.checkBypass()) {
                Espo.Ajax.getRequest('PopupNotification/action/grouped')
                    .then(result => {
                        for (const type in result) {
                            const list = this.sortPopupItems(result[type] || []);

                            list.forEach(item => this.enqueuePopupNotification(type, item));
                        }
                    });
            }

            if (this.useWebSocket) {
                return;
            }

            this.groupedTimeout = setTimeout(
                () => this.checkGroupedPopupNotifications(),
                this.groupedCheckInterval * 1000
            );
        }
    };
});
