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

define('outlook:views/outlook/panels/outlook-contacts', ['view'], function (Dep) {

    return Dep.extend({

        template: 'outlook:outlook/panel',

        productName: 'outlookContacts',

        fieldList: [],

        isBlocked: false,

        fields: null,

        setupFields: function () {
            this.fields = {
                contactFolder: {
                    type: 'base',
                    view: 'outlook:views/outlook/fields/contact-folder',
                    required: false,
                },
            };
        },

        data: function () {
            return {
                integration: this.integration,
                helpText: this.helpText,
                isActive: this.model.get(this.productName + 'Enabled') || false,
                isBlocked: this.isBlocked,
                fields: this.fieldList,
                hasFields: this.fieldList.length > 0,
                name: this.productName,
            };
        },

        setup: function () {
            this.model = this.options.model;
            this.id = this.options.id;
            this.setupFields();
            this.model.defs.fields = $.extend(this.model.defs.fields, this.fields);
            this.model.populateDefaults();

            this.fieldList = [];

            for (const i in this.fields) {
                this.createFieldView(this.fields[i].type, this.fields[i].view || null, i, false);
            }

            // Added.
            this.createView('enabledField', 'views/fields/bool', {
                name: this.productName + 'Enabled',
                model: this.model,
                mode: 'edit',
                selector: '[data-field="enabled"]',
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


        afterRender: function () {
        },

        setConnected: function () {
        },

        setNotConnected: function () {

        },

        validate: function () {
        },

        hideField : function (field) {
             this.$el.find('.cell-' + field).addClass('hidden');
        },

        showField : function (field) {
             this.$el.find('.cell-' + field).removeClass('hidden');
        },
    });
});
