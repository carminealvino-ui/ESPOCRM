define('custom:views/call/record/edit', [
    'crm:views/call/record/edit',
    'custom:helpers/call-appuntamento-sync',
    'custom:helpers/call-esito-popup-defaults',
], function (CallEditModule, CallAppuntamentoSyncModule, CallEsitoDefaultsModule) {

    const Parent = CallEditModule.default || CallEditModule;
    const CallAppuntamentoSync = CallAppuntamentoSyncModule.default || CallAppuntamentoSyncModule;
    const CallEsitoDefaults = CallEsitoDefaultsModule.default || CallEsitoDefaultsModule;

    return Parent.extend({

        events: {
            'click [data-action="createAppuntamentoFromCall"]': function () {
                this.actionCreateAppuntamentoFromCall();
            },
        },

        setup: function () {
            Parent.prototype.setup.call(this);

            CallEsitoDefaults.applyDefaults(this.model);

            this.listenTo(this.model, 'change:esito', () => this.toggleCreateAppuntamentoButton());
            this.listenTo(this.model, 'change:status', () => this.toggleCreateAppuntamentoButton());

            CallEsitoDefaults.setupRinvioFieldListeners(this, this.model, this.getDateTime());
        },

        toggleCreateAppuntamentoButton: function () {
            const show = this.model.get('status') === 'Held'
                && this.model.get('esito') === CallAppuntamentoSync.ESITO_OPPORTUNITA_ACCETTATA;
            let $btn = this.$el.find('[data-action="createAppuntamentoFromCall"]');

            if (!$btn.length) {
                const $target = this.$el.find('.page-header .buttons-panel .buttons-group').first();

                if ($target.length) {
                    $target.append(
                        '<div class="btn-group call-create-appuntamento-group">'
                        + '<button type="button" class="btn btn-default btn-xs-wide" data-action="createAppuntamentoFromCall">'
                        + 'Crea Appuntamento</button></div>'
                    );
                    $btn = this.$el.find('[data-action="createAppuntamentoFromCall"]');
                }
            }

            this.$el.find('.call-create-appuntamento-group').toggle(!!show);
        },

        actionCreateAppuntamentoFromCall: function () {
            const attributes = CallAppuntamentoSync.buildAttributesFromCall(this.model);
            const leadId = CallAppuntamentoSync.resolveLeadId(this.model);

            const openModal = (attrs) => {
                this.createView('createAppuntamentoDialog', 'views/modals/edit', {
                    scope: 'Appuntamento',
                    attributes: attrs,
                }, view => {
                    view.render();
                });
            };

            if (!leadId) {
                openModal(attributes);

                return;
            }

            this.getModelFactory().create('Lead')
                .then(leadModel => {
                    leadModel.id = leadId;

                    return leadModel.fetch().then(() => {
                        openModal(CallAppuntamentoSync.enrichFromLead(attributes, leadModel));
                    });
                });
        },

        afterRender: function () {
            Parent.prototype.afterRender.call(this);

            CallEsitoDefaults.applyDefaults(this.model);
            CallEsitoDefaults.refreshRecordFields(this, ['testo', 'tipologia', 'description']);
            this.toggleCreateAppuntamentoButton();
        },
    });
});
