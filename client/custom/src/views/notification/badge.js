/* global define, Espo */

define('custom:views/notification/badge', ['views/notification/badge'], function (BadgeModule) {

    const Parent = BadgeModule.default || BadgeModule;

    /**
     * - Polling grouped se metadata.event.useWebSocket=false
     * - Wipe collapsed SOLO una volta a sessione (sblocca storage stale),
     *   poi rispetta "Nascondi"/collapse senza riaprire a ogni poll
     */
    return class PopupNotificationBadgeView extends Parent {

        afterRender() {
            this.wipeCollapsedOncePerSession();

            if (typeof Parent.prototype.afterRender === 'function') {
                Parent.prototype.afterRender.call(this);
            }
        }

        getCollapsedStorageKey(id) {
            return 'popupNotificationCollapsed-' + id;
        }

        wipeCollapsedOncePerSession() {
            const flag = 'espoPopupCollapsedWipedSessionV5';

            try {
                if (sessionStorage.getItem(flag) === '1') {
                    return;
                }
            }
            catch (e) {
                // sessionStorage non disponibile: wipe comunque una volta
            }

            this.wipeAllCollapsedPopupState();

            try {
                sessionStorage.setItem(flag, '1');
            }
            catch (e2) {
                // ignore
            }
        }

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
                        let total = 0;

                        // Modalità emergenza: non fidarti di flag stale di "ignora popup".
                        try {
                            localStorage.removeItem('messageClosePopupNotificationId');
                        }
                        catch (e) {
                            // ignore
                        }

                        for (const type in result) {
                            const list = result[type] || [];
                            total += list.length;
                            list.forEach(item => {
                                if (item && typeof item === 'object') {
                                    item.xIgnored = false;
                                }

                                this.showPopupNotification(type, item, true);
                            });
                        }

                        if (total > 0) {
                            console.info('[popup] grouped items:', total);
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
