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

define('outlook:views/outlook/fields/contact-folder', ['views/fields/link'], function (Dep) {

    return Dep.extend({

        autocompleteDisabled: true,

        events: {
            'click [data-action="selectLink"]': function (e) {
                e.stopPropagation();
                e.preventDefault();

                Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

                this.createView('modal', 'outlook:views/outlook/modals/select-contact-folder', {
                }, function (view) {
                    Espo.Ui.notify(false);
                    view.render();

                    this.listenToOnce(view, 'select', function (id, name){
                        view.close();
                        this.setFolder(id, name);
                    }, this);
                });
            } ,
            'click [data-action="clearLink"]' : function (e) {
                this.clearLink(e);
            },
        },

        setup: function () {
            this.nameName = this.name + 'Name';
            this.idName = this.name + 'Id';
        },

        setFolder: function (id, name) {
            this.$elementName.val(name);
            this.$elementId.val(id);
            this.trigger('change');
        },
    });
});
