/* global define */

define('custom:views/calendar/calendar', ['crm:views/calendar/calendar'], function (CalendarViewModule) {

    const CalendarView = CalendarViewModule.default || CalendarViewModule;
    const APPUNTAMENTO_SCOPE = 'Appuntamento';
    const DISPONIBILITA_SCOPE = 'Disponibilita';
    const DISPONIBILITA_INVERSE_GROUP = 'disponibilita-available';

    return class CustomCalendarView extends CalendarView {

        getDefaultDurationSeconds() {
            const fromMeta = this.getMetadata().get(
                ['entityDefs', APPUNTAMENTO_SCOPE, 'fields', 'duration', 'default']
            );

            if (fromMeta !== null && fromMeta !== undefined && fromMeta !== '') {
                return parseInt(fromMeta, 10);
            }

            return 5400;
        }

        getDefaultDateEnd(dateStart) {
            return this.getDateTime()
                .toMoment(dateStart)
                .add(this.getDefaultDurationSeconds(), 'seconds')
                .format(this.getDateTime().internalDateTimeFormat);
        }

        normalizeCreateEventValues(values) {
            if (!values || values.allDay || !values.dateStart) {
                return values;
            }

            return {
                ...values,
                dateEnd: this.getDefaultDateEnd(values.dateStart),
            };
        }

        getNonWorkingBackgroundColor() {
            return this.colors.bg || this.colors['bg'] || '#d3d3d3';
        }

        resolveDisponibilitaDayDate(o) {
            const candidates = [o.dateStartDate, o.datadisponibilita, o.dateStart];

            for (const value of candidates) {
                if (!value) {
                    continue;
                }

                const normalized = String(value).substring(0, 10);

                if (/^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
                    return normalized;
                }
            }

            return null;
        }

        parseTimesFromName(name) {
            if (!name) {
                return null;
            }

            const match = String(name).match(/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/);

            if (!match) {
                return null;
            }

            return {
                start: match[1],
                end: match[2],
            };
        }

        buildLocalMomentFromTime(dayDate, timeStr) {
            const parts = String(timeStr).split(':');
            const hour = parseInt(parts[0], 10) || 0;
            const minute = parseInt(parts[1], 10) || 0;
            const day = typeof this.dateToMoment === 'function' ?
                this.dateToMoment(dayDate) :
                this.getDateTime().toMoment(dayDate);

            return day.clone()
                .hour(hour)
                .minute(minute)
                .second(0)
                .millisecond(0);
        }

        resolveDisponibilitaTimes(o) {
            const dayDate = this.resolveDisponibilitaDayDate(o);

            if (!dayDate) {
                return null;
            }

            const fromName = this.parseTimesFromName(o.name);

            if (fromName) {
                const start = this.buildLocalMomentFromTime(dayDate, fromName.start);
                const end = this.buildLocalMomentFromTime(dayDate, fromName.end);

                if (end.isAfter(start)) {
                    return {start, end};
                }
            }

            if (!o.orarioInizio || !o.orarioFine) {
                return null;
            }

            const inizio = this.getDateTime().toMoment(o.orarioInizio);
            const fine = this.getDateTime().toMoment(o.orarioFine);

            if (!inizio.isValid() || !fine.isValid()) {
                return null;
            }

            const start = this.buildLocalMomentFromTime(dayDate, inizio.format('HH:mm'));
            const end = this.buildLocalMomentFromTime(dayDate, fine.format('HH:mm'));

            if (!end.isAfter(start)) {
                return null;
            }

            return {start, end};
        }

        buildDisponibilitaAvailabilityEvent(o) {
            const times = this.resolveDisponibilitaTimes(o);

            if (!times) {
                return null;
            }

            return {
                id: DISPONIBILITA_SCOPE + '-' + o.id + '-avail',
                recordId: o.id,
                scope: DISPONIBILITA_SCOPE,
                title: '',
                start: times.start.toISOString(true),
                end: times.end.toISOString(true),
                allDay: false,
                display: 'inverse-background',
                groupId: DISPONIBILITA_INVERSE_GROUP,
                color: this.getNonWorkingBackgroundColor(),
                editable: false,
            };
        }

        buildDisponibilitaEvents(o) {
            const events = [];
            const headerEvent = super.convertToFcEvent(o);

            const availabilityEvent = this.buildDisponibilitaAvailabilityEvent(o);

            if (availabilityEvent) {
                events.push(availabilityEvent);
            }

            events.push(headerEvent);

            return events;
        }

        convertToFcEvents(list) {
            const events = [];

            (list || []).forEach(o => {
                if (o.scope === DISPONIBILITA_SCOPE) {
                    try {
                        events.push(...this.buildDisponibilitaEvents(o));
                    } catch (error) {
                        console.error('[calendar] disponibilita render', error, o);
                        events.push(super.convertToFcEvent(o));
                    }

                    return;
                }

                events.push(super.convertToFcEvent(o));
            });

            return events;
        }

        async createView(name, viewName, options) {
            if (name === 'dialog' && viewName === 'crm:views/calendar/modals/edit') {
                viewName = 'custom:views/calendar/modals/edit';
            }

            return super.createView(name, viewName, options);
        }

        async createEvent(values) {
            return super.createEvent(this.normalizeCreateEventValues(values || {}));
        }
    };
});
