/* global define, Espo */

define('custom:views/notification/badge', ['views/notification/badge'], function (BadgeModule) {

    const Parent = BadgeModule.default || BadgeModule;

    /**
     * - Coda popup (un display alla volta)
     * - Wipe collapsed SOLO una volta a sessione (sblocca storage stale)
     * - "Nascondi" persiste in storage; "Crea Opportunità" solo park temporaneo
     */
    return class QueuedNotificationBadgeView extends Parent {

        setup() {
            super.setup();

            this.popupDisplayQueue = [];
            this.popupDisplayActive = false;
            this.shownEntityKeys = {};
            this._parkedPopupIds = [];
        }

        afterRender() {
            this.wipeCollapsedOncePerSession();

            if (typeof Parent.prototype.afterRender === 'function') {
                Parent.prototype.afterRender.call(this);
            }
        }

        getPopupNotificationView(id) {
            return this.getView('popup-' + id);
        }

        getCollapsedStorageKey(id) {
            return 'popupNotificationCollapsed-' + id;
        }

        wipeCollapsedOncePerSession() {
            // bump v2: sblocca chi è rimasto senza popup dopo park persistente
            const flag = 'espoPopupCollapsedWipedSessionV2';

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

            this._parkedPopupIds = (this._parkedPopupIds || []).filter(x => x !== id);

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

        expandPopupNotification(id) {
            if (typeof Parent.prototype.expandPopupNotification === 'function') {
                Parent.prototype.expandPopupNotification.call(this, id);
            }
            else {
                const view = this.getPopupNotificationView(id);

                if (view && typeof view.makeExpanded === 'function') {
                    view.makeExpanded();
                }

                try {
                    this.getStorage().clear('state', this.getCollapsedStorageKey(id));
                }
                catch (e) {
                    // ignore
                }
            }

            if (this.$popupContainer && this.$popupContainer.length) {
                this.$popupContainer.removeClass('hidden');
            }
        }

        /**
         * Park TEMPORANEO (no localStorage): per Crea Opportunità.
         * I popup tornano con restoreParkedPopupNotifications().
         */
        parkAllPopupNotificationsTemporarily() {
            this._parkedPopupIds = [];

            const ids = (this.shownNotificationIds || []).slice();

            ids.forEach(id => {
                const view = this.getPopupNotificationView(id);

                if (!view || view.isCollapsed) {
                    return;
                }

                this._parkedPopupIds.push(id);

                this.modalBarProvider.get()?.addModalView(view, {
                    title: view.getTitle() ?? this.translate('Notification'),
                });

                if (typeof view.makeCollapsed === 'function') {
                    view.makeCollapsed();
                }
            });

            if (this.$popupContainer && this.$popupContainer.length) {
                this.$popupContainer.addClass('hidden');
            }
        }

        restoreParkedPopupNotifications(exceptId = null) {
            const ids = (this._parkedPopupIds || []).slice();
            this._parkedPopupIds = [];

            ids.forEach(id => {
                if (exceptId && id === exceptId) {
                    return;
                }

                if (!(this.shownNotificationIds || []).includes(id)) {
                    return;
                }

                this.expandPopupNotification(id);
            });

            const stillVisible = (this.shownNotificationIds || []).some(id => {
                const view = this.getPopupNotificationView(id);

                return view && !view.isCollapsed;
            });

            if (stillVisible && this.$popupContainer && this.$popupContainer.length) {
                this.$popupContainer.removeClass('hidden');
            }
        }

        /** @deprecated alias: non persistere — usa park temporaneo */
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

                    // Solo se Nascondi esplicito (storage), non per park temporaneo.
                    if (
                        data.id &&
                        this.getStorage().get('state', this.getCollapsedStorageKey(id)) &&
                        !notificationView.isCollapsed
                    ) {
                        this.collapsePopupNotification(id, true);
                    }
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
            // Non cancellare collapsed qui: altrimenti "Nascondi" riapre a ogni poll.
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
