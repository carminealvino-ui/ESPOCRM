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
                        dataInizioGenerazione: dateFrom,
                        dataFineGenerazione: dateTo,
                        generazioneProductBrandId: this.model.get('generazioneProductBrandId'),
                        generazioneProductBrandName: this.model.get('generazioneProductBrandName'),
                        generazioneStatus: this.model.get('generazioneStatus'),
                        generazioneArea: area,
                        generazioneCollaboratorsIds: this.model.get('generazioneCollaboratorsIds') || [],
                    })
                        .then(result => {
                            this.enableActionItem('generaDisponibilita');
                            this.showGenerationResult(result);
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

        showGenerationResult: function (result) {
            const message = result && result.message
                ? result.message
                : 'Disponibilità generate.';
            const created = Number(result && result.created || 0);

            if (created <= 0) {
                let hint = message + ' Nessuna nuova disponibilità creata.';

                if (result && result.daysBlocked > 0) {
                    hint += ' Verificare le Eccezioni orario lavorativo del calendario';
                    const exceptions = result.blockingExceptions || [];

                    if (exceptions.length) {
                        const first = exceptions[0];
                        hint += ' (es. ' + (first.name || 'non lavorativo') + ' ' + first.dateStart + '→' + first.dateEnd + ')';
                    }

                    hint += '.';
                } else if (result && result.daysNoSlots > 0) {
                    hint += ' Configurare le fasce orarie (timeRanges) nel calendario ricorrente.';
                } else if (result && result.daysWeekdayOff > 0) {
                    hint += ' Abilitare i giorni della settimana nel calendario ricorrente.';
                } else {
                    hint += ' Verificare date, eccezioni calendario e fasce orarie.';
                }

                Espo.Ui.warning(hint);

                return;
            }

            Espo.Ui.success(message);
        },
    });
});
