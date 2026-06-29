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

        resolveDisponibilitaBrandColor(o) {
            if (o.color) {
                return o.color;
            }

            return this.colors[DISPONIBILITA_SCOPE] || null;
        }

        resolveDisponibilitaDayDate(o) {
            const candidates = [o.dateStartDate, o.datadisponibilita];

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

        buildLocalMoment(dayDate, timeMoment) {
            const day = this.getDateTime().toMoment(dayDate);

            return day.clone()
                .hour(timeMoment.hour())
                .minute(timeMoment.minute())
                .second(0)
                .millisecond(0);
        }

        buildDisponibilitaInverseBackground(o, headerEvent) {
            const dayDate = this.resolveDisponibilitaDayDate(o);

            if (!dayDate || !o.orarioInizio || !o.orarioFine) {
                return null;
            }

            const inizio = this.getDateTime().toMoment(o.orarioInizio);
            const fine = this.getDateTime().toMoment(o.orarioFine);

            if (!inizio.isValid() || !fine.isValid()) {
                return null;
            }

            const start = this.buildLocalMoment(dayDate, inizio);
            const end = this.buildLocalMoment(dayDate, fine);

            if (!end.isAfter(start)) {
                return null;
            }

            return {
                ...headerEvent,
                id: headerEvent.id + '-inv',
                title: '',
                start: start.toISOString(true),
                end: end.toISOString(true),
                allDay: false,
                display: 'inverse-background',
                groupId: DISPONIBILITA_INVERSE_GROUP,
                color: this.colors.bg || this.colors['bg'],
                editable: false,
            };
        }

        convertToFcEvents(list) {
            const events = [];

            (list || []).forEach(o => {
                if (o.scope === DISPONIBILITA_SCOPE) {
                    const headerEvent = super.convertToFcEvent(o);
                    const brandColor = this.resolveDisponibilitaBrandColor(o);

                    if (brandColor) {
                        headerEvent.color = brandColor;
                        headerEvent.originalColor = brandColor;
                    }

                    events.push(headerEvent);

                    const inverseBackground = this.buildDisponibilitaInverseBackground(o, headerEvent);

                    if (inverseBackground) {
                        events.push(inverseBackground);
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
