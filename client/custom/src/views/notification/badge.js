/* global define, Espo */

define('custom:views/notification/badge', ['views/notification/badge'], function (BadgeModule) {

    const Parent = BadgeModule.default || BadgeModule;

    /**
     * Coda popup + Nascondi (collapse persistente) + park TEMPORANEO container
     * per Crea Opportunità (solo CSS, niente localStorage).
     */
    return class QueuedNotificationBadgeView extends Parent {

        setup() {
            // Wipe PRIMA di super.setup(): altrimenti il parent legge closed/collapsed
            // da localStorage e i popup restano invisibili per tutta la sessione.
            this.wipeCollapsedOncePerSession();

            super.setup();

            this.popupDisplayQueue = [];
            this.popupDisplayActive = false;
            this.shownEntityKeys = {};
            this._popupsParkedForOpportunity = false;

            // Forza riapertura dopo deploy che avevano "chiuso" tutto in storage.
            this.closedNotificationIds = [];
        }

        getPopupNotificationView(id) {
            return this.getView('popup-' + id);
        }

        getCollapsedStorageKey(id) {
            return 'popupNotificationCollapsed-' + id;
        }

        wipeCollapsedOncePerSession() {
            // v4: wipe prima di super.setup + closedNotificationIds azzerati
            const flag = 'espoPopupCollapsedWipedSessionV4';

            try {
                if (sessionStorage.getItem(flag) === '1') {
                    return;
                }
            }
            catch (e) {
                // ignore
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
                'espo-state-popupNotificationCollapsed-',
                'messageClosePopupNotificationId',
                'messageCollapsePopupNotificationId',
                'messageExpandPopupNotificationId',
            ];

            const toRemove = [];

            try {
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
            }
            catch (e) {
                return;
            }

            toRemove.forEach(key => {
                try {
                    localStorage.removeItem(key);
                }
                catch (e2) {
                    // ignore
                }
            });

            if (toRemove.length) {
                console.info('[popup] wiped stale collapsed/close keys:', toRemove.length);
            }
        }

        markPopupRemoved(id) {
            const index = this.shownNotificationIds.indexOf(id);

            if (index > -1) {
                this.shownNotificationIds.splice(index, 1);
            }

            const entityKey = this.getEntityKeyFromPopupId(id);

            if (entityKey) {
                delete this.shownEntityKeys[entityKey];
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

        /**
         * Solo nasconde il container: i popup restano vivi e ricompaiono al restore.
         * NON scrive localStorage (altrimenti spariscono al refresh).
         */
        parkAllPopupNotificationsTemporarily() {
            this._popupsParkedForOpportunity = true;

            if (this.$popupContainer && this.$popupContainer.length) {
                this.$popupContainer.addClass('hidden');
                this.$popupContainer.attr('data-parked-for-opportunity', '1');
            }
        }

        restoreParkedPopupNotifications() {
            this._popupsParkedForOpportunity = false;

            if (this.$popupContainer && this.$popupContainer.length) {
                this.$popupContainer.removeAttr('data-parked-for-opportunity');

                if ((this.shownNotificationIds || []).length > 0) {
                    this.$popupContainer.removeClass('hidden');
                }
            }
        }

        /** @deprecated */
        collapseAllPopupNotifications() {
            this.parkAllPopupNotificationsTemporarily();
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

        buildEntityKey(data) {
            const notificationData = data.data || {};
            const entityType = notificationData.entityType || '';
            const entityId = notificationData.id || '';

            if (!entityType || !entityId) {
                return null;
            }

            return entityType + ':' + entityId;
        }

        buildStablePopupId(name, data) {
            const notificationId = data.id || null;

            if (notificationId) {
                return name + '_' + notificationId;
            }

            const notificationData = data.data || {};
            const entityType = notificationData.entityType || '';
            const entityId = notificationData.id || '';

            if (entityType && entityId) {
                return name + '__' + entityType + '__' + entityId;
            }

            return name + '_anon_' + this.lastId++;
        }

        getEntityKeyFromPopupId(id) {
            const parts = (id || '').split('__');

            if (parts.length !== 3) {
                return null;
            }

            return parts[1] + ':' + parts[2];
        }

        buildPopupQueueKey(name, data) {
            return this.buildStablePopupId(name, data);
        }

        enqueuePopupNotification(name, data, isNotFirstCheck = false) {
            const id = this.buildStablePopupId(name, data);
            const entityKey = this.buildEntityKey(data);
            const notificationId = data.id || null;

            if (this.shownNotificationIds.includes(id)) {
                const notificationView = this.getPopupNotificationView(id);

                if (notificationView) {
                    notificationView.trigger('update-data', data.data);
                }

                return;
            }

            if (entityKey && this.shownEntityKeys[entityKey]) {
                return;
            }

            if (notificationId && this.closedNotificationIds.includes(notificationId)) {
                return;
            }

            if (this.closedNotificationIds.includes(id)) {
                return;
            }

            const key = this.buildPopupQueueKey(name, data);
            const existsInQueue = this.popupDisplayQueue.some(item => item.key === key);

            if (existsInQueue) {
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

            const id = this.buildStablePopupId(name, data);
            const entityKey = this.buildEntityKey(data);

            this.shownNotificationIds.push(id);

            if (entityKey) {
                this.shownEntityKeys[entityKey] = true;
            }

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

            // Se siamo in park temporaneo (Crea Opportunità), non ri-mostrare lo stack.
            if (!this._popupsParkedForOpportunity) {
                this.$popupContainer.removeClass('hidden');
            }

            this.listenTo(view, 'remove', () => {
                this.markPopupRemoved(id);

                localStorage.setItem('messageClosePopupNotificationId', id);
                // Non rilanciare la coda qui: già sbloccata dopo render.
                // Se era l'ultimo popup attivo, processPopupDisplayQueue è no-op.
                this.onPopupDisplayFinished();
            });

            await view.render();

            // Non auto-collassare da storage: dopo i deploy rotti nascondeva tutto.
            // Nascondi resta manuale (collapsePopupNotification scrive storage + UI).

            // CRITICO: sblocca la coda subito, altrimenti resta un solo popup
            // e se quello è nascosto sembra che siano "spariti".
            this.onPopupDisplayFinished();
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
