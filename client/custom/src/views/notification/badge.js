/* global define, Espo, $ */

define('custom:views/notification/badge', ['views/notification/badge'], function (BadgeModule) {

    const Parent = BadgeModule.default || BadgeModule;

    /**
     * Estende il badge core senza la coda custom (che restava bloccata
     * se un popup veniva collassato / falliva prima del remove).
     * Garantisce compatibilità $popupContainer (legacy) vs popupNotificationsContainer (Espo recente)
     * e polling grouped anche se WebSocket globale è ON ma metadata.event.useWebSocket=false.
     */
    return class PopupNotificationBadgeView extends Parent {

        afterRender() {
            if (typeof Parent.prototype.afterRender === 'function') {
                Parent.prototype.afterRender.call(this);
            }

            this.ensurePopupContainerCompat();
        }

        /**
         * Espo recenti: popupNotificationsContainer (DOM).
         * Codice legacy / override: $popupContainer (jQuery).
         */
        ensurePopupContainerCompat() {
            if (!this.popupNotificationsContainer) {
                const el = document.getElementById('popup-notifications-container');

                if (el) {
                    this.popupNotificationsContainer = el;
                }
                else if (typeof this.preparePopupNotificationContainer === 'function') {
                    this.preparePopupNotificationContainer();
                }
            }

            if (
                (!this.$popupContainer || !this.$popupContainer.length) &&
                this.popupNotificationsContainer &&
                typeof $ === 'function'
            ) {
                this.$popupContainer = $(this.popupNotificationsContainer);
            }
        }

        showPopupNotification(name, data, isNotFirstCheck = false) {
            this.ensurePopupContainerCompat();

            return Parent.prototype.showPopupNotification.call(this, name, data, isNotFirstCheck);
        }

        /**
         * Il core ferma il polling grouped se WebSocket globale è ON.
         * Con metadata.event.useWebSocket=false (daemon assente) forziamo il polling.
         */
        shouldPollGroupedPopupNotifications() {
            const eventMeta = (this.popupNotificationsData && this.popupNotificationsData.event) || {};

            if (eventMeta.useWebSocket === false) {
                return true;
            }

            return !this.useWebSocket;
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

        checkGroupedPopupNotifications() {
            if (!this.checkBypass()) {
                Espo.Ajax.getRequest('PopupNotification/action/grouped')
                    .then(result => {
                        for (const type in result) {
                            const list = this.sortPopupItems(result[type] || []);

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
