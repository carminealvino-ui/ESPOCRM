/* global define, Espo */

define('custom:views/modals/disponibilita-ricorrenti', ['views/modal'], function (Dep) {

    return Dep.extend({

        templateContent: [
            '<div class="margin-bottom">',
            '  <button type="button" class="btn btn-default" data-action="selectCalendar">',
            '    <span class="fas fa-calendar"></span> Seleziona calendario lavorativo',
            '  </button>',
            '  <button type="button" class="btn btn-link" data-action="createCalendar">',
            '    <span class="fas fa-plus fa-sm"></span> Crea calendario',
            '  </button>',
            '  <span class="text-muted margin-left calendar-selected-name"></span>',
            '</div>',
            '<div class="calendar-users-info hidden alert alert-info margin-bottom"></div>',
            '<div class="record no-side-margin">{{{record}}}</div>',
        ].join(''),

        backdrop: true,

        className: 'dialog dialog-record dialog-record-wide',

        events: {
            'click [data-action="selectCalendar"]': function () {
                this.actionSelectCalendar();
            },
            'click [data-action="createCalendar"]': function () {
                this.actionCreateCalendar();
            },
        },

        setup: function () {
            this.headerText = this.translate('Disponibilità Ricorrenti', 'labels', 'Disponibilita');
            this.calendarId = null;
            this.userCount = 0;
            this.userSource = 'none';

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

            this.once('after:render', () => {
                this.showFullCalendarForm();
                this.refreshAssignedUserCount();
            });
        },

        showFullCalendarForm: function () {
            this.createRecordView();
        },

        actionCreateCalendar: function () {
            this.calendarId = null;
            this.userCount = 0;
            this.userSource = 'none';
            this.$el.find('.calendar-selected-name').text('Nuovo calendario');

            this.getModelFactory().create('WorkingTimeCalendar')
                .then((model) => {
                    this.calendarModel = model;
                    this.refreshAssignedUserCount();
                    this.createRecordView();
                });
        },

        actionSelectCalendar: function () {
            this.createView('selectCalendarDialog', 'views/modals/select-records', {
                scope: 'WorkingTimeCalendar',
                multiple: false,
                createButton: false,
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

            this.getModelFactory().create('WorkingTimeCalendar')
                .then((model) => {
                    model.id = calendarId;

                    return model.fetch({
                        select: [
                            'id',
                            'name',
                            'description',
                            'timeZone',
                            'timeRanges',
                            'weekday0',
                            'weekday1',
                            'weekday2',
                            'weekday3',
                            'weekday4',
                            'weekday5',
                            'weekday6',
                            'weekday0TimeRanges',
                            'weekday1TimeRanges',
                            'weekday2TimeRanges',
                            'weekday3TimeRanges',
                            'weekday4TimeRanges',
                            'weekday5TimeRanges',
                            'weekday6TimeRanges',
                            'dataInizioGenerazione',
                            'dataFineGenerazione',
                            'generazioneProductBrandId',
                            'generazioneProductBrandName',
                            'generazioneStatus',
                            'generazioneArea',
                            'generazioneCollaboratorsIds',
                            'generazioneCollaboratorsNames',
                            'usersIds',
                            'usersNames',
                        ].join(','),
                    }).then(() => {
                        this.calendarModel = model;
                        this.calendarId = model.id;
                        this.refreshAssignedUserCount();
                        this.createRecordView();
                    });
                });
        },

        resolveAssignedUserCount: function () {
            const usersIds = this.calendarModel.get('usersIds') || [];
            const collaboratorIds = this.calendarModel.get('generazioneCollaboratorsIds') || [];

            if (usersIds.length) {
                return {
                    count: usersIds.length,
                    source: 'calendar',
                };
            }

            if (collaboratorIds.length) {
                return {
                    count: collaboratorIds.length,
                    source: 'collaborators',
                };
            }

            return {
                count: 0,
                source: 'none',
            };
        },

        refreshAssignedUserCount: function () {
            const info = this.resolveAssignedUserCount();

            this.userCount = info.count;
            this.userSource = info.source;
            this.updateUserInfo();
        },

        createRecordView: function () {
            if (this.hasView('record')) {
                this.clearView('record');
            }

            this.createView('record', 'views/record/edit', {
                scope: 'WorkingTimeCalendar',
                model: this.calendarModel,
                type: 'edit',
                layoutName: 'edit',
                sideDisabled: true,
                bottomDisabled: true,
                buttonsDisabled: true,
                isWide: true,
                el: this.getSelector() + ' .record',
            }, (view) => {
                view.render();

                this.stopListening(this.calendarModel, 'change:generazioneCollaboratorsIds');
                this.listenTo(this.calendarModel, 'change:generazioneCollaboratorsIds', () => {
                    this.refreshAssignedUserCount();
                });
            });
        },

        updateUserInfo: function () {
            const $info = this.$el.find('.calendar-users-info');
            let message = 'Utenti assegnati alle disponibilità: ' + this.userCount;

            if (this.userSource === 'calendar') {
                message += ' (dal calendario lavorativo)';
                $info.removeClass('alert-warning').addClass('alert-info');
            } else if (this.userSource === 'collaborators') {
                message += ' (dai collaboratori selezionati)';
                $info.removeClass('alert-warning').addClass('alert-info');
            } else {
                message += ' — selezionare almeno un collaboratore o collegare utenti al calendario.';
                $info.removeClass('alert-info').addClass('alert-warning');
            }

            $info.text(message).removeClass('hidden');
        },

        actionGenerate: function () {
            const dateFrom = this.calendarModel.get('dataInizioGenerazione');
            const dateTo = this.calendarModel.get('dataFineGenerazione');
            const area = this.calendarModel.get('generazioneArea') || [];

            if (!dateFrom || !dateTo) {
                Espo.Ui.warning('Compilare Data inizio e Data fine generazione nel pannello «Generazione Disponibilità».');

                return;
            }

            if (!area.length) {
                Espo.Ui.warning('Selezionare almeno un\'area di lavoro.');

                return;
            }

            this.disableButton('generate');

            const runGenerate = () => {
                if (!this.userCount) {
                    this.enableButton('generate');
                    Espo.Ui.warning('Selezionare almeno un collaboratore o collegare utenti al calendario lavorativo.');

                    return;
                }

                const payload = {
                    calendarId: this.calendarId,
                    dataInizioGenerazione: dateFrom,
                    dataFineGenerazione: dateTo,
                    generazioneProductBrandId: this.calendarModel.get('generazioneProductBrandId'),
                    generazioneProductBrandName: this.calendarModel.get('generazioneProductBrandName'),
                    generazioneStatus: this.calendarModel.get('generazioneStatus'),
                    generazioneArea: area,
                    generazioneCollaboratorsIds: this.calendarModel.get('generazioneCollaboratorsIds') || [],
                };

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
            };

            if (this.calendarModel.hasChanged() || !this.calendarId) {
                this.calendarModel.save()
                    .then(() => {
                        this.calendarId = this.calendarModel.id;

                        return Espo.Ajax.getRequest('WorkingTimeCalendar/' + this.calendarId, {
                            select: 'usersIds',
                        });
                    })
                    .then((response) => {
                        this.calendarModel.set({
                            usersIds: response.usersIds || [],
                        }, {silent: true});
                        this.refreshAssignedUserCount();
                        runGenerate();
                    })
                    .catch((e) => {
                        this.enableButton('generate');
                        throw e;
                    });

                return;
            }

            this.refreshAssignedUserCount();
            runGenerate();
        },
    });
});
