/* global define */

/**
 * Calendario: alla creazione Appuntamento forza dateEnd = dateStart + 1h30
 * (lo slot click/drag del calendario è spesso 30m).
 * Apre il modal custom per assignedUsers + durata.
 */
define('custom:views/calendar/calendar', [
    'crm:views/calendar/calendar',
    'custom:helpers/appuntamento-duration',
], function (CalendarViewModule, AppuntamentoDurationHelper) {

    const Dep = CalendarViewModule.default || CalendarViewModule;
    const DEFAULT_DURATION_SECONDS = 5400;
    const EDIT_MODAL = 'custom:views/calendar/modals/edit';

    return Dep.extend({

        getContrastTextColor: function (color) {
            const hex = String(color || '').trim().match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
            let rgb;
            if (hex) {
                const value = hex[1].length === 3 ? hex[1].split('').map(c => c + c).join('') : hex[1];
                rgb = [0, 2, 4].map(i => parseInt(value.slice(i, i + 2), 16));
            } else {
                const match = String(color || '').match(/^rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)/i);
                if (!match) return '#303840';
                rgb = match.slice(1, 4).map(Number);
            }
            // Contrast is computed against the translucent fill over a light calendar.
            const alphaMatch = String(color || '').match(/^rgba\([^,]+,[^,]+,[^,]+,\s*([\d.]+)\s*\)$/i);
            if (alphaMatch) {
                const alpha = Math.min(1, Math.max(0, Number(alphaMatch[1])));
                rgb = rgb.map(value => value * alpha + 255 * (1 - alpha));
            }
            const linear = rgb.map(value => {
                const channel = value / 255;
                return channel <= 0.04045 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4);
            });
            const luminance = linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722;
            return (luminance + 0.05) / 0.05 >= 1.05 / (luminance + 0.05) ? '#303840' : '#ffffff';
        },

        applyEventTextContrast: function (info) {
            if (info.event.display === 'background' || info.event.display === 'inverse-background') return;
            // List and month-dot events have a light row, not the event's colored background.
            const isRow = info.el.classList.contains('fc-list-event') || info.el.classList.contains('fc-daygrid-dot-event');
            const background = isRow ? '#ffffff' :
                (info.event.backgroundColor || window.getComputedStyle(info.el).backgroundColor);
            const isInstallationReminder = /^Verifica installazione(?: del \d{2}\/\d{2}\/\d{4})?:/i.test(info.event.title || '');
            const isCompleted = info.event.extendedProps?.status === 'Completed';
            const color = !isRow && isInstallationReminder ?
                (isCompleted ? '#303840' : '#ffffff') : this.getContrastTextColor(background);
            [info.el, ...info.el.querySelectorAll('.fc-event-main, .fc-event-time, .fc-event-title, .fc-list-event-title, .fc-list-event-time, a')]
                .forEach(element => element.style.setProperty('color', color, 'important'));
        },

        fillColor: function (event) {
            Dep.prototype.fillColor.call(this, event);
            if (event.scope === 'Meeting') {
                event.meetingDefaultColor = event.meetingDefaultColor || event.color || '#9bd2df';
                event.color = event.meetingResolvedColor || '#ffdf00';
            }
        },

        convertToFcEvent: function (source) {
            const event = Dep.prototype.convertToFcEvent.call(this, source);
            if (source.scope === 'Meeting' && !source.isWorkingRange) {
                event.meetingDefaultColor = event.meetingDefaultColor || '#9bd2df';
                event.meetingResolvedColor = '#ffdf00';
                event.color = '#ffdf00';
                event.backgroundColor = '#ffdf00';
                event.borderColor = '#ffdf00';
                event.textColor = '#303840';
            }
            return event;
        },

        applyBusinessMeetingColors: function (events, colors) {
            events.forEach(event => {
                const id = event.recordId || String(event.id).replace(/^Meeting-/, '');
                if (event.scope !== 'Meeting' || !Object.prototype.hasOwnProperty.call(colors || {}, id)) return;
                const color = colors[id] || event.meetingDefaultColor || '#9bd2df';
                event.meetingResolvedColor = color;
                event.color = event.backgroundColor = event.borderColor = color;
                event.textColor = this.getContrastTextColor(color);
            });
        },

        fetchEvents: function (from, to, callback) {
            const deliver = callback;
            callback = events => {
                this.installWeeklyClick();
                events.forEach(event => {
                    if (event.backgroundColor || event.color) {
                        event.textColor = this.getContrastTextColor(event.backgroundColor || event.color);
                    }
                });
                deliver(events);
            };
            const showAvailability = this.enabledScopeList.includes('Disponibilita');
            if (this.teamIdList && this.teamIdList.length) return this.fetchWeeklyTeam(from, to, callback);
            const userId = this.options.userId || this.getUser().id;
            const start = moment.utc(from).tz('Europe/Rome').format('YYYY-MM-DD');
            const end = moment.utc(to).tz('Europe/Rome').format('YYYY-MM-DD');
            Dep.prototype.fetchEvents.call(this, from, to, events => {
                Espo.Ajax.getRequest('WorkingTimeCalendar/action/weekEvents', {userId: userId, from: start, to: end})
                    .then(result => {
                        this.applyBusinessMeetingColors(events, result.meetingColors);
                        if (!showAvailability) { callback(events); return; }
                        const dates = new Set(result.managedDates);
                        const dayOf = event => event.dateStartDate || moment(event.start).tz('Europe/Rome').format('YYYY-MM-DD');
                        const retained = events.filter(event => event.groupId !== 'nonWorking' &&
                            (!dates.has(dayOf(event)) || event.scope !== 'Disponibilita'));
                        retained.forEach(event => {
                            const canceled = this.getMetadata().get(['scopes', 'Appuntamento', 'canceledStatusList']) || ['Not Held', 'Ingestibile'];
                            if (event.scope !== 'Appuntamento' || event.allDay || canceled.includes(event.status)) return;
                            const recordId = String(event.id).replace(/^Appuntamento-/, '');
                            if (result.appointmentAvailability?.[recordId] === 'outside') {
                                event.weeklyStartOutside = true;
                                event.borderColor = '#ba5317';
                            }
                        });
                        const summaries = result.managedDates.filter(date => result.events.some(event => dayOf(event) === date)).map(date => {
                            const slots = result.events.filter(event => dayOf(event) === date);
                            const title = slots.length ? slots.map(event => moment(event.start).tz('Europe/Rome').format('HH:mm') + '–' + moment(event.end).tz('Europe/Rome').format('HH:mm')).join(', ') : 'Non disponibile';
                            const minutes = slots.reduce((sum, event) => sum + moment(event.end).diff(moment(event.start), 'minutes'), 0);
                            return {id: 'weekly-summary-' + date, start: date, allDay: true,
                                title: (result.brands?.[date] ? result.brands[date] + ' | ' : 'Disponibilità: ') + title + (minutes ? ' · ' + Math.floor(minutes / 60) + 'h ' + minutes % 60 + 'm' : ''),
                                editable: false, color: result.brandColors?.[date] || '#808080', weeklyAvailability: true, weeklyUserId: userId, weeklyDate: date};
                        });
                        this.installWeeklyClick();
                        callback(retained.concat(result.events, summaries));
                    }).catch(() => {
                        callback(events);
                        Espo.Ui.warning('Disponibilità settimanali non caricate: gli orari mostrati potrebbero non essere aggiornati.');
                    });
            });
        },

        fetchWeeklyTeam: function (from, to, callback) {
            Dep.prototype.fetchEvents.call(this, from, to, original => {
                Espo.Ajax.getRequest('WorkingTimeCalendar/action/teamWeekEvents', {
                    teamIds: this.teamIdList.join(','),
                    from: moment.utc(from).tz('Europe/Rome').format('YYYY-MM-DD'),
                    to: moment.utc(to).tz('Europe/Rome').format('YYYY-MM-DD')
                }).then(result => {
                    result.users.forEach(user => this.applyBusinessMeetingColors(original, user.meetingColors));
                    if (!this.enabledScopeList.includes('Disponibilita')) { callback(original); return; }
                    const datesByUser = new Map(result.users.map(user => [user.userId, new Set(user.managedDates)]));
                    const events = original.filter(event => {
                        if (event.groupId === 'nonWorking') return false;
                        if (event.scope !== 'Disponibilita') return true;
                        const date = event.dateStartDate || moment(event.start).tz('Europe/Rome').format('YYYY-MM-DD');
                        const ids = event.userIdList || [];
                        return !ids.length || ids.some(id => !datesByUser.get(id)?.has(date));
                    });
                    result.users.forEach(user => user.events.forEach(slot => events.push({...slot,
                        display: 'block', title: user.name + ' · ' + slot.title, color: slot.brandColor || '#808080',
                        userIdList: [user.userId], userNameMap: {[user.userId]: user.name}})));
                    this.installWeeklyClick();
                    callback(events);
                }).catch(() => { callback(original); Espo.Ui.warning('Disponibilità del team non aggiornate: caricamento non riuscito.'); });
            });
        },

        installWeeklyClick: function () {
            if (!this.calendar || this.weeklyClickCalendar === this.calendar) return;
            this.weeklyClickCalendar = this.calendar;
            const originalMount = this.calendar.getOption('eventDidMount');
            this.calendar.setOption('eventDidMount', info => {
                if (originalMount) originalMount(info);
                this.applyEventTextContrast(info);
                if (info.event.extendedProps.weeklyStartOutside) {
                    const time = info.el.querySelector('.fc-event-time');
                    if (time && !time.querySelector('[data-weekly-warning]')) {
                        const warning = document.createElement('span');
                        warning.dataset.weeklyWarning = 'true';
                        warning.textContent = ' ⚠';
                        warning.title = 'Inizio fuori disponibilità';
                        warning.setAttribute('aria-label', warning.title);
                        warning.setAttribute('role', 'img');
                        time.appendChild(warning);
                    }
                }
                if (info.event.extendedProps.weeklyAvailability && info.event.display === 'background') {
                    info.el.textContent = '';
                    info.el.style.opacity = '0.2';
                    info.el.setAttribute('aria-label', info.event.title);
                }
            });
            const originalClick = this.calendar.getOption('eventClick');
            this.calendar.setOption('eventClick', info => {
                if (info.event.extendedProps.weeklyAvailability) {
                    const p = info.event.extendedProps;
                    this.getRouter().navigate('#WorkingTimeCalendar?userId=' + encodeURIComponent(p.weeklyUserId || p.userId || this.options.userId || this.getUser().id) + '&week=' + encodeURIComponent(p.weeklyDate || moment(info.event.start).format('YYYY-MM-DD')), {trigger: true});
                    return;
                }
                if (originalClick) originalClick(info);
            });
        },

        addModel: function (model) {
            Dep.prototype.addModel.call(this, model);
            if (this.calendar) this.calendar.refetchEvents();
        },

        updateModel: function (model) {
            Dep.prototype.updateModel.call(this, model);
            if (this.calendar) this.calendar.refetchEvents();
        },

        createEvent: function (values) {
            if (!values || typeof values !== 'object') {
                values = {};
            }

            // Forza durata 1h30 prima di aprire il modal (Appuntamento / default scope)
            const scope = values.scope || (this.options && this.options.scope) || null;
            const isAppuntamento = !scope || scope === 'Appuntamento';

            if (
                isAppuntamento &&
                !values.allDay &&
                values.dateStart &&
                !values.dateStartDate
            ) {
                const dateEnd = AppuntamentoDurationHelper.addSecondsToSystemDateTime(
                    this.getDateTime(),
                    values.dateStart,
                    DEFAULT_DURATION_SECONDS
                );

                if (dateEnd) {
                    values = Object.assign({}, values, {dateEnd: dateEnd});
                }
            }

            // Preferisci modal custom; se il core non espone createEvent overrideabile
            // con dialogView, monkey-patch createView solo per questo dialog.
            const originalCreateView = this.createView.bind(this);

            this.createView = (name, viewName, options, callback) => {
                if (
                    name === 'quickEdit' ||
                    name === 'dialog' ||
                    (typeof viewName === 'string' && viewName.indexOf('calendar/modals/edit') !== -1)
                ) {
                    viewName = EDIT_MODAL;

                    if (options && options.attributes && isAppuntamento && values.dateEnd) {
                        options = Object.assign({}, options, {
                            attributes: Object.assign({}, options.attributes, {
                                dateEnd: values.dateEnd,
                            }),
                            dateEnd: values.dateEnd,
                        });
                    } else if (options && values.dateEnd) {
                        options = Object.assign({}, options, {dateEnd: values.dateEnd});
                    }
                }

                return originalCreateView(name, viewName, options, callback);
            };

            try {
                return Dep.prototype.createEvent.call(this, values);
            } finally {
                this.createView = originalCreateView;
            }
        },
    });
});
