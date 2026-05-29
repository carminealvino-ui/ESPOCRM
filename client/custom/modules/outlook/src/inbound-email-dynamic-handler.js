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

define('outlook:inbound-email-dynamic-handler', ['dynamic-handler'], function (Dep) {

    return Dep.extend({

        init: function () {
            this.control();

            this.recordView.listenTo(
                this.model, 'change', this.control.bind(this)
            );

            this.recordView.listenTo(this.recordView, 'after:set-edit-mode', this.control.bind(this));
            this.recordView.listenTo(this.recordView, 'after:set-detail-mode', this.control.bind(this));
        },

        control: function () {
            if (this.recordView.name === 'edit' || this.recordView.mode === 'edit') {
                this.recordView.hidePanel('outlook');
                return;
            }

            const host = this.model.get('host') || '';
            const smtpHost = this.model.get('smtpHost') || '';

            if (
                this.model.get('useImap') && host &&
                    (host.includes('office365.') || host.includes('.outlook.com')
                ) ||
                this.model.get('useSmtp') && smtpHost &&
                    (smtpHost.includes('office365.') || smtpHost.includes('.outlook.com'))
            ) {
                this.recordView.showPanel('outlook');
            } else {
                this.recordView.hidePanel('outlook');
            }
        },
    });
});
