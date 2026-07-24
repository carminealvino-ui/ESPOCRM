/* global define, Espo */

define('custom:views/notification/badge', ['views/notification/badge'], function (BadgeModule) {

    const Parent = BadgeModule.default || BadgeModule;

    /**
     * - Polling grouped se metadata.event.useWebSocket=false
     * - All'avvio cancella TUTTI gli stati collapsed popup in localStorage
     *   (sessione normale vs anonima: in anonima lo storage è vuoto)
     */
    return class PopupNotificationBadgeView extends Parent {

        afterRender() {
            this.wipeAllCollapsedPopupState();

            if (typeof Parent.prototype.afterRender === 'function') {
                Parent.prototype.afterRender.call(this);
            }
        }

        getCollapsedStorageKey(id) {
            return 'popupNotificationCollapsed-' + id;
        }

        /**
         * Espo Storage usa chiavi localStorage: espo-state-popupNotificationCollapsed-...
         * getStorage().clear() non è affidabile su tutte le chiavi: usiamo removeItem.
         */
        wipeAllCollapsedPopupState() {
            const markers = [
                'popupNotificationCollapsed-',
                'messageClosePopupNotificationId',
                'messageCollapsePopupNotificationId',
                'messageExpandPopupNotificationId',
            ];

            const toRemove = [];

            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);

                if (!key) {
                    continue;
                }

                for (let m = 0; m < markers.length; m++) {
                    if (key.indexOf(markers[m]) !== -1) {
                        toRemove.push(key);
                        break;
                    }
                }
            }

            toRemove.forEach(key => {
                try {
                    localStorage.removeItem(key);
                }
                catch (e) {
                    // ignore
                }
            });

            if (toRemove.length) {
                console.info('[popup] wiped collapsed/close keys:', toRemove.length);
            }
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
                const storageName = this.getCollapsedStorageKey(id);

                try {
                    this.getStorage().clear('state', storageName);
                }
                catch (e) {
                    // ignore
                }

                try {
                    localStorage.removeItem('espo-state-' + storageName);
                }
                catch (e2) {
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
