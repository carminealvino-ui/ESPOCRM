(function () {
    'use strict';

    /**
     * I popup raggruppati vengono mostrati in ordine di data (API già ordinata).
     * Espo core usa forEach async → ordine casuale a schermo.
     */
    var patchNotificationBadge = function (Badge) {
        if (!Badge || !Badge.prototype || Badge.prototype.__popupOrderPatched) {
            return;
        }

        Badge.prototype.__popupOrderPatched = true;

        var originalCheckGrouped = Badge.prototype.checkGroupedPopupNotifications;

        Badge.prototype.checkGroupedPopupNotifications = function () {
            if (!this.checkBypass()) {
                Espo.Ajax.getRequest('PopupNotification/action/grouped')
                    .then(function (result) {
                        var types = Object.keys(result || {});
                        var self = this;

                        var showType = function (typeIndex) {
                            if (typeIndex >= types.length) {
                                return Promise.resolve();
                            }

                            var type = types[typeIndex];
                            var list = result[type] || [];

                            var showItem = function (itemIndex) {
                                if (itemIndex >= list.length) {
                                    return showType(typeIndex + 1);
                                }

                                return Promise.resolve(
                                    self.showPopupNotification(type, list[itemIndex])
                                ).then(function () {
                                    return showItem(itemIndex + 1);
                                });
                            };

                            return showItem(0);
                        };

                        return showType(0);
                    }.bind(this));
            }

            if (this.useWebSocket) {
                return;
            }

            this.groupedTimeout = setTimeout(
                function () {
                    this.checkGroupedPopupNotifications();
                }.bind(this),
                this.groupedCheckInterval * 1000
            );
        };

        Badge.prototype.__popupOrderOriginalCheckGrouped = originalCheckGrouped;
    };

    var loadPatch = function () {
        if (!window.Espo || !Espo.loader) {
            return;
        }

        Espo.loader.require('views/notification/badge', function (module) {
            patchNotificationBadge(module.default || module);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadPatch);
    } else {
        loadPatch();
    }
})();
