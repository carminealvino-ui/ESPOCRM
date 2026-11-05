/* global define, Espo */
define('custom:handlers/appuntamento/held-opportunity', [
    'custom:views/opportunity/helpers/appuntamento-sync'
], function (AppuntamentoSync) {
    const active = new Map();

    function rootView(view) {
        let root = view;
        while (root.getParentView && root.getParentView()) root = root.getParentView();
        return root;
    }

    async function reviewIdentity(host, appointment) {
        const prospectId = appointment.prospectId ||
            (appointment.parentType === 'Prospect' ? appointment.parentId : null);
        if (!prospectId) {
            Espo.Ui.warning('Collegare il Prospect all’appuntamento prima di creare l’opportunità.');
            return false;
        }
        const modal = await host.createView('heldIdentity-' + appointment.id, 'views/modals/edit', {
            scope: 'Prospect', id: prospectId,
            layoutName: 'identityReview',
            headerText: 'Esito appuntamento: verifica nominativo e B2C/B2B',
            fullFormDisabled: true
        });
        return new Promise(resolve => {
            let saved = false;
            modal.once('after:save', () => { saved = true; resolve(true); });
            modal.once('remove', () => { if (!saved) resolve(false); });
            modal.render();
        });
    }

    async function openOpportunity(host, model) {
        const id = model.id;
        if (!id || active.has(id)) return;
        active.set(id, true);
        let opened = false;
        try {
            const result = await Espo.Ajax.getRequest('Opportunity', {
                maxSize: 2, select: 'id,name,appuntamentoId',
                where: [{type: 'equals', attribute: 'appuntamentoId', value: id}]
            });
            if (result.list && result.list.length) {
                // The appointment already has its linked opportunity; never propose another one.
                return;
            }
            if (!host.getAcl().checkScope('Opportunity', 'create')) {
                Espo.Ui.warning('Appuntamento salvato come Svolto. Non hai i permessi per creare l’opportunità collegata.');
                return;
            }
            let fresh = await Espo.Ajax.getRequest('Appuntamento/' + encodeURIComponent(id));
            if (fresh.status !== 'Held') return;
            if (!await reviewIdentity(host, {...fresh, id: id})) return;
            // Identity hooks may change names, salutation, phone and linked Lead.
            fresh = await Espo.Ajax.getRequest('Appuntamento/' + encodeURIComponent(id));
            if (fresh.status !== 'Held') return;
            const linked = await Espo.Ajax.getRequest('Opportunity', {
                maxSize: 1, select: 'id',
                where: [{type: 'equals', attribute: 'appuntamentoId', value: id}]
            });
            if (linked.list && linked.list.length) return;
            const attributes = AppuntamentoSync.buildAttributesFromAppuntamento({...fresh, id: id});
            const modal = await host.createView('heldOpportunity-' + id, 'views/modals/edit', {
                scope: 'Opportunity', attributes: attributes,
                focusForCreate: false, noFocus: true
            });
            modal.once('remove', () => active.delete(id));
            modal.once('after:save', () => {
                model.trigger('update-related:opportunita');
            });
            opened = true;
            modal.render();
        } catch (error) {
            Espo.Ui.error('Appuntamento salvato come Svolto, ma non è stato possibile aprire l’opportunità. Riapri e salva l’appuntamento per riprovare.');
        } finally {
            if (!opened) active.delete(id);
        }
    }

    async function openReschedule(host, model) {
        const id = model.id;
        const key = 'rifissato:' + id;
        if (!id || active.has(key)) return;
        active.set(key, true);
        let opened = false;
        try {
            const fresh = await Espo.Ajax.getRequest('Appuntamento/' + encodeURIComponent(id));
            if (fresh.status === 'Planned' || fresh.sottostato !== 'Rifissato') return;
            const replacements = await Espo.Ajax.getRequest('Appuntamento', {
                maxSize: 1, select: 'id',
                where: [{type: 'equals', attribute: 'rifissatoDaId', value: id}]
            });
            if (replacements.list && replacements.list.length) return;
            if (!host.getAcl().checkScope('Appuntamento', 'create')) {
                Espo.Ui.warning('Esito salvato. Non hai i permessi per creare il nuovo appuntamento.');
                return;
            }
            const modal = await host.createView('reschedule-' + id,
                'custom:views/appuntamento/modals/rifissato-create', {
                    sourceId: id, originalDateStart: fresh.dateStart
                });
            modal.once('remove', () => active.delete(key));
            modal.once('after:save', () => model.trigger('update-related:appuntamenti'));
            opened = true;
            modal.render();
        } catch (error) {
            Espo.Ui.error('Esito salvato, ma non è stato possibile aprire la scelta della nuova data. Riapri e salva l’appuntamento per riprovare.');
        } finally {
            if (!opened) active.delete(key);
        }
    }

    return class {
        constructor(view) { this.view = view; }
        process() {
            const view = this.view;
            if (!view.model && view.collection) {
                (view.collection.models || []).forEach(model => this.attach(view, model));
                view.listenTo(view.collection, 'add', model => this.attach(view, model));
                return;
            }
            this.attach(view, view.model);
        }
        attach(view, model) {
            if (!model || model.entityType !== 'Appuntamento') return;
            model._heldOpportunityHost = rootView(view);
            if (model._heldOpportunitySaveHook) return;
            model._heldOpportunitySaveHook = true;
            const save = model.save.bind(model);
            model.save = function (attributes, options) {
                // The reminder popup already opens its identity check and prefilled opportunity.
                const managed = !!model._appointmentOpportunityManaged;
                const request = save(attributes, options);
                if (!request || typeof request.then !== 'function') return request;
                return request.then(result => {
                    if (!managed && model.id && model.get('status') !== 'Planned' &&
                        model.get('sottostato') === 'Rifissato') {
                        setTimeout(() => openReschedule(model._heldOpportunityHost, model), 0);
                    }
                    if (!managed && model.get('status') === 'Held' && model.id) {
                        // Let edit-small/calendar finish closing before opening the next modal.
                        setTimeout(() => openOpportunity(model._heldOpportunityHost, model), 0);
                    }
                    return result;
                });
            };
        }
    };
});
