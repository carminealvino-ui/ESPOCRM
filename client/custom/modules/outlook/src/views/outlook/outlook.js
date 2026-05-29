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

define('outlook:views/outlook/outlook', ['views/external-account/oauth2', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'outlook:outlook/outlook',

        fields: {
            enabled: {
                type: 'bool',
            }
        },

        isConnected: false,

        activeProducts: [],

        events: {
            'click button[data-action="cancel"]': function () {
                this.getRouter().navigate('#ExternalAccount', {trigger: true});
            },
            'click button[data-action="save"]': function () {
                this.save();
            },
            'click [data-action="connect"]': function () {
                this.connect();
            },
            'click .disconnect-link > a': function () {
                this.disconnect();
                return false;
            },

            'change .enable-panel': function (e) {
                const panelName = $(e.currentTarget).attr('name').replace('Enabled', '');

                this.togglePanel(panelName);
            }
        },

        data: function () {
            return {
                integration: this.integration,
                helpText: this.helpText,
                isConnected: this.isConnected,
                fields: this.fieldList,
                panels: this.activeProducts,
            };
        },

        setup: function () {
            this.integration = this.options.integration;
            this.id = this.options.id;
            this.helpText = false;

            if (this.getLanguage().has(this.integration, 'help', 'ExternalAccount')) {
                this.helpText = this.translate(this.integration, 'help', 'ExternalAccount');
            }

            this.redirectUri = this.getConfig().get('siteUrl').replace(/\/$/, '') + '/oauth-callback.php';

            this.fieldList = [];
            this.dataFieldList = [];
            this.activeProducts = [];
            this.fields =  {
                enabled: {
                    type: 'bool'
                }
            };

            this.model = new Model();
            this.model.id = this.id;
            this.model.name = 'ExternalAccount';
            this.model.urlRoot = 'ExternalAccount';

            this.model.defs = {};

            var products = this.getMetadata().get('integrations.Outlook.products');

            this.wait(true);

            for (const key in products) {
                if (!products[key]) {
                    continue;
                }

                var productScope = key.charAt(0).toUpperCase() + key.slice(1);
                var isActive = this.getAcl().check(productScope);

                if (!isActive) {
                    continue;
                }

                this.activeProducts.push(key);

                var viewName = "outlook:views/outlook/panels/" +
                    Espo.Utils.camelCaseToHyphen(key.charAt(0).toUpperCase() + key.slice(1));

                this.createView(key, viewName, {
                    selector: `.panel-container[data-name="${key}"]`,
                    id: this.id,
                    model: this.model,
                }, (view) => {
                    this.fieldList.concat(view.fieldList);
                });

                // Added.
                this.listenTo(this.model, `change:${key}Enabled`, () => {
                    if (this.model.attributes[`${key}Enabled`]) {
                        this.showPanel(key);
                    } else {
                        this.hidePanel(key);
                    }
                });
            }

            // Commented.
            /*for (const i in this.activeProducts) {
              this.fields[this.activeProducts[i] + 'Enabled'] = {
                   type: 'bool',
                   default: false
               };
            }*/

            this.model.defs.fields = this.fields;
            this.model.populateDefaults();

            for (const i in this.fields) {
                this.createFieldView(this.fields[i].type, this.fields[i].view || null, i, false);
            }

            this.listenToOnce(this.model, 'sync', () => {
                Espo.Ajax.getRequest('ExternalAccount/action/getOAuth2Info?id=' + this.id)
                    .then(response => {
                        this.clientId = response.clientId;

                        if (response.isConnected) {
                            this.setConnected();
                        }

                        this.wait(false);
                    });
            });

            this.model.fetch();
        },

        afterRender: function () {
            if (!this.model.get('enabled')) {
                this.$el.find('.data-panel').addClass('hidden');
            }

            if (this.isConnected) {
                this.$el.find('.data-panel-connected').removeClass('hidden');
            } else {
                this.$el.find('.data-panel-connected').addClass('hidden');
            }

            for (var i in this.activeProducts) {
                if (!this.model.get(this.activeProducts[i] + 'Enabled')) {
                    this.hidePanel(this.activeProducts[i]);
                }
            }

            this.listenTo(this.model, 'change:enabled', () => {
                if (this.model.get('enabled')) {
                    this.$el.find('.data-panel').removeClass('hidden');
                } else {
                    this.$el.find('.data-panel').addClass('hidden');
                }
            });
        },

        createFieldView: function (type, view, name, readOnly, params) {
            var fieldView = view || this.getFieldManager().getViewName(type);

            this.createView(name, fieldView, {
                model: this.model,
                selector: '.field-' + name,
                defs: {
                    name: name,
                    params: params
                },
                mode: readOnly ? 'detail' : 'edit',
                readOnly: readOnly,
            });

            this.fieldList.push(name);
        },

        save: function () {
            this.fieldList.forEach(field => {
                const view = this.getView(field);

                if (view.element == null) {
                    this.model.unset(field);
                } else if (!view.readOnly) {
                    view.fetchToModel();
                }
            });

            var notValid = false;

            if (this.model.get('enabled')) {
                this.fieldList.forEach(field => {
                    notValid = this.getView(field).validate() || notValid;
                });
            }

            for (const key in this.activeProducts) {
                var product = this.activeProducts[key];

                if (this.model.get(product + 'Enabled')) {
                    try {
                        notValid |= this.getView(product).validate();
                    } catch (err) {}
                }
            }

            if (notValid) {
                this.notify('Not valid', 'error');

                return;
            }

            this.listenToOnce(this.model, 'sync', () => {
                this.notify('Saved', 'success');

                if (!this.model.get('enabled')) {
                    this.setNotConnected();
                }
            });

            this.model.unset("accessToken");
            this.model.unset("refreshToken");
            this.model.unset("tokenType");

            this.notify('Saving...');
            this.model.save();
        },

        popup: function (options, callback) {
            options.windowName = options.windowName || 'ConnectWithOAuth';
            options.windowOptions = options.windowOptions || 'location=0,status=0,width=800,height=400';

            options.callback = options.callback || function () {
                window.location.reload();
            };

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

            const parseUrl = (str) => {
                var data = {};

                str = str.slice(str.indexOf('?') + 1, str.length);

                str.split('&').forEach(part => {
                    var arr = part.split('=');
                    var name = decodeURI(arr[0]);

                    data[name] = decodeURI(arr[1] || '');
                });

                if (!data.error && !data.code) {
                    return null;
                }

                return data;
            }

            const popup = window.open(path, options.windowName, options.windowOptions);

            const interval = window.setInterval(() => {
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

        connect: function () {
            /** @type {Record} */
            const integrationParams = this.getHelper().getAppParam('outlookParams') || {};

            const tenant = integrationParams.tenant || 'common';
            const prompt = integrationParams.authorizationPrompt || 'consent';

            let endpoint = this.getMetadata().get(['integrations', 'Outlook', 'params', 'endpoint']);

            endpoint = endpoint.replace('{tenant}', tenant);

            this.popup({
                path: endpoint,
                params: {
                    client_id: this.clientId,
                    redirect_uri: this.redirectUri,
                    scope: this.getMetadata().get(`integrations.${this.integration}.params.scope`),
                    response_type: 'code',
                    access_type: 'offline',
                    prompt: prompt,
                }
            }, (res) => {
                if (res.error) {
                    console.error(res);
                    Espo.Ui.error('Error response, more details in console');

                    return;
                }

                if (res.code) {
                    this.$el.find('[data-action="connect"]').addClass('disabled');


                    Espo.Ajax.postRequest('ExternalAccount/action/authorizationCode', {
                        id: this.id,
                        code: res.code,
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
                    this.notify('Error occurred', 'error');
                }
            });
        },

        disconnect: function () {
            this.confirm(this.translate('disconnectConfirmation', 'messages', 'ExternalAccount'), () => {
                this.model.set("accessToken", null);
                this.model.set("refreshToken", null);
                this.model.set("tokenType", null);
                this.model.set("enabled", false);

                this.listenToOnce(this.model, 'sync', () => {
                    this.notify('Saved', 'success');
                    this.setNotConnected();
                });

                this.notify('Saving...');
                this.model.save();

            });
        },

        setConnected: function () {
            this.isConnected = true;
            this.$el.find('[data-action="connect"]').addClass('hidden');
            this.$el.find('.connected-label').removeClass('hidden');
            this.$el.find('.data-panel-connected').removeClass('hidden');
            this.$el.find('.disconnect-link').removeClass('hidden');

            var hasAnyPanel = false;

            for (const key in this.activeProducts) {
                var product = this.activeProducts[key];

                var view = this.getView(product) || false;

                if (view) {
                    view.setConnected();
                }

                hasAnyPanel |= !view.isBlocked || false;
            }

            if (!hasAnyPanel) {
                this.$el.find('.no-panels').removeClass('hidden');
            } else {
                this.$el.find('.no-panels').addClass('hidden');
            }
        },

        setNotConnected: function () {
            this.isConnected = false;
            this.$el.find('[data-action="connect"]').removeClass('hidden');
            this.$el.find('.connected-label').addClass('hidden');
            this.$el.find('.data-panel-connected').addClass('hidden');
            this.$el.find('.disconnect-link').addClass('hidden');

            for (const key in this.activeProducts) {
                var product = this.activeProducts[key];

                try {
                    this.getView(product).setNotConnected();
                } catch (err) {
                    // Handle error(s) here
                }
            }
        },

        hideField: function (field) {
             this.$el.find('.cell-' + field).addClass('hidden');
        },

        showField: function (field) {
             this.$el.find('.cell-' + field).removeClass('hidden');
        },

        hidePanel: function (panel) {
             this.$el.find('.panel-' + panel + ' .panel-body').addClass('hidden');
        },

        showPanel: function (panel) {
             this.$el.find('.panel-' + panel + ' .panel-body').removeClass('hidden');
        },

        togglePanel: function (panel) {
            if (this.$el.find('.panel-' + panel + ' .panel-body').hasClass('hidden')) {
                this.showPanel(panel);
            } else {
                this.hidePanel(panel);
            }
        },
    });
});
