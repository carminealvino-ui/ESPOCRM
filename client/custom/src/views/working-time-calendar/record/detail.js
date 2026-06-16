/* global define, Espo */

define('custom:views/working-time-calendar/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.ensureGeneraDisponibilitaButtonRegistered();
        },

        ensureGeneraDisponibilitaButtonRegistered: function () {
            const found = (this.buttonList || []).some(item => item.name === 'generaDisponibilita');

            if (found || !this.addButton) {
                return;
            }

            this.addButton({
                name: 'generaDisponibilita',
                label: this.translate('Genera Disponibilità', 'labels', 'WorkingTimeCalendar'),
                style: 'primary',
                action: 'generaDisponibilita',
            });
        },

        resolveAssignedUserCount: function () {
            const usersIds = this.model.get('usersIds') || [];
            const collaboratorIds = this.model.get('generazioneCollaboratorsIds') || [];

            if (usersIds.length) {
                return usersIds.length;
            }

            return collaboratorIds.length;
        },

        actionGeneraDisponibilita: function () {
            const dateFrom = this.model.get('dataInizioGenerazione');
            const dateTo = this.model.get('dataFineGenerazione');
            const area = this.model.get('generazioneArea') || [];

            if (!dateFrom || !dateTo) {
                Espo.Ui.warning(
                    'Compilare Data inizio e Data fine generazione nel pannello «Generazione Disponibilità».'
                );

                return;
            }

            if (!area.length) {
                Espo.Ui.warning('Selezionare almeno un\'area di lavoro.');

                return;
            }

            const runGeneration = () => {
                if (!this.resolveAssignedUserCount()) {
                    Espo.Ui.warning(
                        'Selezionare almeno un collaboratore o collegare utenti al calendario lavorativo.'
                    );

                    return;
                }

                const message = 'Generare le disponibilità dal ' + dateFrom + ' al ' + dateTo + '?';

                this.confirm(message, () => {
                    this.disableActionItem('generaDisponibilita');

                    Espo.Ajax.postRequest('WorkingTimeCalendar/action/generaDisponibilita', {
                        id: this.model.id,
                    })
                        .then(result => {
                            this.enableActionItem('generaDisponibilita');
                            Espo.Ui.success(result && result.message ? result.message : 'Disponibilità generate.');
                        })
                        .catch(e => {
                            this.enableActionItem('generaDisponibilita');
                            throw e;
                        });
                });
            };

            if (this.model.hasChanged()) {
                this.confirm('Salvare le modifiche prima di generare le disponibilità?', () => {
                    this.model.save().then(() => runGeneration());
                });

                return;
            }

            runGeneration();
        },
    });
});
