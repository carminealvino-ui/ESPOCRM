/* global define, Espo */

define('custom:views/working-time-calendar/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            if (Dep.prototype.setup) {
                Dep.prototype.setup.apply(this, arguments);
            }

            this.ensureGeneraDisponibilitaButtonRegistered();
        },

        ensureGeneraDisponibilitaButtonRegistered: function () {
            var found = false;

            (this.buttonList || []).forEach(function (item) {
                if (item.name === 'generaDisponibilita') {
                    found = true;
                }
            });

            if (found || !this.addButton) {
                return;
            }

            this.addButton({
                name: 'generaDisponibilita',
                label: 'Genera Disponibilità',
                style: 'primary',
                action: 'generaDisponibilita'
            });
        },

        actionGeneraDisponibilita: function () {
            var self = this;
            var dateFrom = this.model.get('dataInizioGenerazione');
            var dateTo = this.model.get('dataFineGenerazione');
            var users = this.model.get('generazioneAssignedUsersIds') || [];
            var area = this.model.get('generazioneArea') || [];

            if (!dateFrom || !dateTo) {
                Espo.Ui.warning('Compilare Data inizio e Data fine generazione nel pannello "Generazione Disponibilità".');

                return;
            }

            if (!users.length) {
                Espo.Ui.warning('Selezionare almeno un utente nel campo Utenti assegnati.');

                return;
            }

            if (!area.length) {
                Espo.Ui.warning('Selezionare almeno un\'area di lavoro.');

                return;
            }

            var runGeneration = function () {
                var message = 'Generare le disponibilità dal ' + dateFrom + ' al ' + dateTo + '?';

                self.confirm(message, function () {
                    self.disableActionItem('generaDisponibilita');

                    Espo.Ajax.postRequest(
                        'WorkingTimeCalendar/action/generaDisponibilita',
                        {
                            id: self.model.id
                        }
                    ).then(function (result) {
                        self.enableActionItem('generaDisponibilita');

                        if (result && result.message) {
                            Espo.Ui.success(result.message);
                        } else {
                            Espo.Ui.success('Disponibilità generate.');
                        }
                    }).catch(function (e) {
                        self.enableActionItem('generaDisponibilita');
                        throw e;
                    });
                });
            };

            if (this.model.hasChanged()) {
                this.confirm('Salvare le modifiche prima di generare le disponibilità?', function () {
                    self.model.save().then(function () {
                        runGeneration();
                    });
                });

                return;
            }

            runGeneration();
        }
    });
});
