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

define('outlook:views/outlook/modals/select-contact-folder', 'views/modal', function (Dep) {

    return Dep.extend({

        template: 'outlook:outlook/modals/select-contact-folder',

        data: function () {
            return {
                folderDataList: this.folderDataList,
            };
        },

        events: {
            'click [data-action="select"]': function (e) {
                var id = $(e.currentTarget).data('id');
                var name = $(e.currentTarget).data('name');
                this.trigger('select', id, name);
            },
        },

        setup: function () {
            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Cancel',
                }
            ];

            this.wait(
                Espo.Ajax.postRequest('OutlookContacts/action/contactFolders').then(function (list) {
                    this.folderDataList = list;
                }.bind(this))
            );
        },
    });
});
