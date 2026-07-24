/* global define, Espo */

define('custom:views/notification/badge', ['views/notification/badge'], function (BadgeModule) {

    const Parent = BadgeModule.default || BadgeModule;

    /**
     * Override minimo: solo polling grouped quando metadata.event.useWebSocket=false
     * (WebSocket globale ON ma daemon assente). Nessuna coda custom.
     */
    return class PopupNotificationBadgeView extends Parent {

        shouldPollGroupedPopupNotifications() {
            const eventMeta = (this.popupNotificationsData && this.popupNotificationsData.event) || {};

            if (eventMeta.useWebSocket === false) {
                return true;
            }

            return !this.useWebSocket;
        }

        checkGroupedPopupNotifications() {
            if (!this.checkBypass()) {
                Espo.Ajax.getRequest('PopupNotification/action/grouped')
                    .then(result => {
                        for (const type in result) {
                            const list = result[type] || [];

                            list.forEach(item => this.showPopupNotification(type, item));
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
