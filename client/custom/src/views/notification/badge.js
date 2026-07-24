/* global define, Espo */

define('custom:views/notification/badge', ['views/notification/badge'], function (BadgeModule) {

    const Parent = BadgeModule.default || BadgeModule;

    /**
     * - Polling grouped se metadata.event.useWebSocket=false (daemon assente)
     * - Pulisce stato "collapsed" stale: su Espo 10 i popup collassati restano
     *   nascosti (solo modal-bar) e sembrano "non partire"
     */
    return class PopupNotificationBadgeView extends Parent {

        getCollapsedStorageKey(id) {
            return 'popupNotificationCollapsed-' + id;
        }

        clearCollapsedStateForItem(name, data) {
            const notificationId = data && data.id ? data.id : null;
            const ids = [];

            if (notificationId) {
                ids.push(name + '_' + notificationId);
            }

            const notificationData = (data && data.data) || {};
            const entityType = notificationData.entityType || '';
            const entityId = notificationData.id || '';

            if (entityType && entityId) {
                ids.push(name + '__' + entityType + '__' + entityId);
            }

            ids.forEach(id => {
                try {
                    this.getStorage().clear('state', this.getCollapsedStorageKey(id));
                }
                catch (e) {
                    // ignore
                }
            });
        }

        shouldPollGroupedPopupNotifications() {
            const eventMeta = (this.popupNotificationsData && this.popupNotificationsData.event) || {};

            if (eventMeta.useWebSocket === false) {
                return true;
            }

            return !this.useWebSocket;
        }

        showPopupNotification(name, data, isNotFirstCheck = false) {
            this.clearCollapsedStateForItem(name, data);

            return Parent.prototype.showPopupNotification.call(this, name, data, isNotFirstCheck);
        }

        checkGroupedPopupNotifications() {
            if (!this.checkBypass()) {
                Espo.Ajax.getRequest('PopupNotification/action/grouped')
                    .then(result => {
                        let total = 0;

                        for (const type in result) {
                            const list = result[type] || [];
                            total += list.length;
                            list.forEach(item => this.showPopupNotification(type, item));
                        }

                        if (total > 0) {
                            console.info('[popup] grouped items:', total, result);
                        }
                    })
                    .catch(err => {
                        console.error('PopupNotification/action/grouped failed', err);
                    });
            }

            if (!this.shouldPollGroupedPopupNotifications()) {
                return;
            }

            this.groupedTimeout = setTimeout(
                () => this.checkGroupedPopupNotifications(),
                this.groupedCheckInterval * 1000
            );
        }
    };
});
