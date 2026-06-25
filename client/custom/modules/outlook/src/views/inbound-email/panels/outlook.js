/***********************************************************************************
 * The contents of this file are subject to the Extension License Agreement
 * ("Agreement") which can be viewed at
 * https://www.espocrm.com/extension-license-agreement/.
 * By copying, installing downloading, or using this file, You have unconditionally
 * agreed to the terms and conditions of the Agreement, and You may not use this
 * file except in compliance with the Agreement. Under the terms of the Agreement,
 * You shall not license, sublicense, sell, resell, rent, lease, lend, distribute,
 * redistribute, market, publish, commercialize, or otherwise transfer rights or
 * usage to the software or any modified version or derivative work of the software
 * created by or for you.
 *
 * Copyright (C) 2015-2026 EspoCRM, Inc.
 *
 * License ID: 77350457a8d35522431c4daeee1dd4ad
 ************************************************************************************/

define('outlook:views/inbound-email/panels/outlook', 'view', function (Dep) {

    return Dep.extend({

        template: 'outlook:inbound-email/panels/outlook',

        data: function () {
            return {};
        },

        events: {
            'click [data-action="connect"]': 'actionConnect',
            'click [data-action="disconnect"]': 'actionDisconnect',
        },

        setup: function () {
            this.isLoaded = false;

            this.id = this.model.id;

            Espo.Ajax.postRequest('OutlookMail/action/ping', {
                id: this.id,
                entityType: this.model.entityType,
            }).then(
                function (response) {
                    this.clientId = response.clientId;
                    this.redirectUri = response.redirectUri;
                    if (response.isConnected) {
                        this.setConnected();
                    } else {
                        this.setNotConnected();
                    }
                }.bind(this)
            );
        },

        setConnected: function () {
            this.isLoaded = true;
            this.isConnected = true;

            this.reRender();
        },

        setNotConnected: function () {
            this.isLoaded = true;
            this.isConnected = false;

            this.reRender();
        },

        actionConnect: function () {
            /** @type {Record} */
            const integrationParams = this.getHelper().getAppParam('outlookParams') || {};

            const tenant = integrationParams.tenant || 'common';
            const prompt = integrationParams.authorizationPrompt || 'consent';
            const mailScope = integrationParams.mailScope;
            const mailGraphApiScope = integrationParams.mailGraphApiScope;

            let scope = mailScope;

            if (mailGraphApiScope && !this.model.attributes.useImap && this.model.attributes.useSmtp) {
                scope = mailGraphApiScope;
            }

            let endpoint = this.getMetadata().get(['integrations', 'Outlook', 'params', 'endpoint']);

            endpoint = endpoint.replace('{tenant}', tenant);

            this.popup({
                path: endpoint,
                params: {
                    client_id: this.clientId,
                    redirect_uri: this.redirectUri,
                    scope: scope,
                    response_type: 'code',
                    access_type: 'offline',
                    prompt: prompt,
                }
            }, function (res) {
                if (res.error) {
                    console.error(res);
                    Espo.Ui.error('Error response, more details in console');

                    return;
                }
                if (res.code) {
                    this.$el.find('[data-action="connect"]').addClass('disabled');

                    Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

                    Espo.Ajax.postRequest('OutlookMail/action/connect', {
                        id: this.id,
                        code: res.code,
                        entityType: this.model.entityType,
                    })
                    .then(response => {
                        this.notify(false);

                        if (response === true) {
                            this.setConnected();
                        } else {
                            this.setNotConnected();
                        }

                        this.$el.find('[data-action="connect"]').removeClass('disabled');
                    })
                    .catch(() => {
                        this.$el.find('[data-action="connect"]').removeClass('disabled');
                    });

                } else {
                    Espo.Ui.error('Error occurred, bad response');
                }
            });
        },

        actionDisconnect: function () {
            this.confirm(this.translate('disconnectConfirmation', 'messages', 'ExternalAccount'), () => {
                this.$el.find('[data-action="disconnect"]').addClass('disabled');
                this.$el.find('[data-action="connect"]').addClass('disabled');

                Espo.Ajax
                    .postRequest('OutlookMail/action/disconnect', {
                        id: this.id,
                        entityType: this.model.entityType,
                    })
                    .then(() => {
                        this.setNotConnected();

                        this.$el.find('[data-action="disconnect"]').removeClass('disabled');
                        this.$el.find('[data-action="connect"]').removeClass('disabled');
                    })
                    .catch(() => {
                        this.$el.find('[data-action="disconnect"]').removeClass('disabled');
                        this.$el.find('[data-action="connect"]').removeClass('disabled');
                    });
            });
        },

        popup: function (options, callback) {
            options.windowName = options.windowName || 'ConnectWithOAuth';
            options.windowOptions = options.windowOptions || 'location=0,status=0,width=800,height=600';
            options.callback = options.callback || function(){ window.location.reload(); };

            var self = this;

            var path = options.path;

            var arr = [];
            var params = (options.params || {});
            for (var name in params) {
                if (params[name]) {
                    arr.push(name + '=' + encodeURI(params[name]));
                }
            }
            path += '?' + arr.join('&');

            var parseUrl = function (str) {
                var data = {};

                str = str.slice(str.indexOf('?') + 1, str.length);

                str.split('&').forEach(function (part) {
                    var arr = part.split('=');
                    var name = decodeURI(arr[0]);
                    data[name] = decodeURI(arr[1] || '');
                }, this);

                if (!data.error && !data.code) {
                    return null;
                }

                return data;
            }

            var popup = window.open(path, options.windowName, options.windowOptions);
            var interval = window.setInterval(function () {
                if (popup.closed) {
                    window.clearInterval(interval);
                } else {
                    var res = parseUrl(popup.location.href.toString());
                    if (res) {
                        callback.call(self, res);
                        popup.close();
                        window.clearInterval(interval);
                    }
                }
            }, 500);
        },

    });
});
