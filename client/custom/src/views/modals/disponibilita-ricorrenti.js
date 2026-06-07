/* global define, Espo, _ */

define('custom:views/modals/disponibilita-ricorrenti', ['views/modal'], function (Dep) {

    return Dep.extend({

        templateContent: [
            '<div class="margin-bottom">',
            '  <button type="button" class="btn btn-default" data-action="selectCalendar">',
            '    <span class="fas fa-calendar"></span> Seleziona calendario lavorativo',
            '  </button>',
            '  <span class="text-muted margin-left calendar-selected-name"></span>',
            '</div>',
            '<div class="calendar-users-info hidden alert alert-info margin-bottom"></div>',
            '<div class="record no-side-margin">{{{record}}}</div>',
        ].join(''),

        backdrop: true,

        className: 'dialog dialog-record',

        events: {
            'click [data-action="selectCalendar"]': function () {
                this.actionSelectCalendar();
            },
        },

        setup: function () {
            this.headerText = this.translate('Disponibilità Ricorrenti', 'labels', 'Disponibilita');
            this.calendarId = null;
            this.userCount = 0;

            this.buttonList = [
                {
                    name: 'generate',
                    label: 'Genera',
                    style: 'primary',
                    onClick: () => this.actionGenerate(),
                },
                {
                    name: 'cancel',
                    label: 'Annulla',
                    onClick: () => this.close(),
                },
            ];

            this.wait(
                this.getModelFactory().create('WorkingTimeCalendar')
                    .then((model) => {
                        this.calendarModel = model;
                    })
            );
        },

        actionSelectCalendar: function () {
            this.createView('selectCalendarDialog', 'views/modals/select-records', {
                scope: 'WorkingTimeCalendar',
                multiple: false,
            }, (view) => {
                view.render();

                this.listenToOnce(view, 'select', (models) => {
                    if (!models || !models.length) {
                        return;
                    }

                    this.loadCalendar(models.at(0).id, models.at(0).get('name'));
                });
            });
        },

        loadCalendar: function (calendarId, calendarName) {
            this.calendarId = calendarId;

            this.$el.find('.calendar-selected-name').text(calendarName || calendarId);

            Espo.Ajax.getRequest('WorkingTimeCalendar/' + calendarId, {
                select: [
                    'id',
                    'name',
                    'dataInizioGenerazione',
                    'dataFineGenerazione',
                    'generazioneAzienda',
                    'generazioneStatus',
                    'generazioneArea',
                    'generazioneCollaboratorsIds',
                    'usersIds',
                ].join(','),
            }).then((response) => {
                this.calendarModel.set(response, {silent: true});
                this.userCount = (response.usersIds || []).length;
                this.updateUserInfo();
                this.createRecordView();
            });
        },

        createRecordView: function () {
            if (this.recordView) {
                this.recordView.remove();
                this.recordView = null;
            }

            this.createView('record', 'views/record/edit-for-modal', {
                scope: 'WorkingTimeCalendar',
                model: this.calendarModel,
                layoutName: 'detailGenerazioneDisponibilita',
                detailLayout: 'detailGenerazioneDisponibilita',
                el: this.getSelector() + ' .record',
            }, (view) => {
                this.recordView = view;
                view.render();
            });
        },

        updateUserInfo: function () {
            const $info = this.$el.find('.calendar-users-info');

            let message = 'Utenti dal calendario (assegnati automaticamente): ' + this.userCount;

            if (!this.userCount) {
                message += ' — collegare almeno un utente al calendario lavorativo.';
                $info.removeClass('alert-info').addClass('alert-warning');
            } else {
                $info.removeClass('alert-warning').addClass('alert-info');
            }

            $info.text(message).removeClass('hidden');
        },

        actionGenerate: function () {
            if (!this.calendarId) {
                Espo.Ui.warning('Selezionare un calendario lavorativo.');

                return;
            }

            const dateFrom = this.calendarModel.get('dataInizioGenerazione');
            const dateTo = this.calendarModel.get('dataFineGenerazione');
            const area = this.calendarModel.get('generazioneArea') || [];

            if (!dateFrom || !dateTo) {
                Espo.Ui.warning('Compilare Data inizio e Data fine generazione.');

                return;
            }

            if (!area.length) {
                Espo.Ui.warning('Selezionare almeno un\'area di lavoro.');

                return;
            }

            if (!this.userCount) {
                Espo.Ui.warning('Nessun utente collegato al calendario selezionato.');

                return;
            }

            const payload = {
                calendarId: this.calendarId,
                dataInizioGenerazione: dateFrom,
                dataFineGenerazione: dateTo,
                generazioneAzienda: this.calendarModel.get('generazioneAzienda'),
                generazioneStatus: this.calendarModel.get('generazioneStatus'),
                generazioneArea: area,
                generazioneCollaboratorsIds: this.calendarModel.get('generazioneCollaboratorsIds') || [],
            };

            this.disableButton('generate');

            Espo.Ajax.postRequest('Disponibilita/action/generaDisponibilitaRicorrenti', payload)
                .then((result) => {
                    this.enableButton('generate');

                    Espo.Ui.success(result && result.message ? result.message : 'Disponibilità generate.');

                    this.trigger('after:generate');
                    this.close();

                    const listView = this.getParentView();

                    if (listView && listView.collection) {
                        listView.collection.fetch();
                    }
                })
                .catch((e) => {
                    this.enableButton('generate');
                    throw e;
                });
        },
    });
});
