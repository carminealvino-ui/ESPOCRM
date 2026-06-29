/* global define */

define('custom:views/calendar/calendar', ['crm:views/calendar/calendar'], function (CalendarViewModule) {

    const CalendarView = CalendarViewModule.default || CalendarViewModule;
    const APPUNTAMENTO_SCOPE = 'Appuntamento';
    const DISPONIBILITA_SCOPE = 'Disponibilita';

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

        buildDisponibilitaBackgroundEvent(o, headerEvent) {
            const brandColor = this.resolveDisponibilitaBrandColor(o);

            if (!o.orarioInizio || !o.orarioFine) {
                return null;
            }

            const start = this.getDateTime().toMoment(o.orarioInizio);
            const end = this.getDateTime().toMoment(o.orarioFine);

            if (!start.isValid() || !end.isValid() || !end.isAfter(start)) {
                return null;
            }

            const background = {...headerEvent};

            background.id = headerEvent.id + '-bg';
            background.title = '';
            background.start = start.toISOString(true);
            background.end = end.toISOString(true);
            background.allDay = false;
            background.display = 'background';
            background.groupId = 'disponibilita-working';

            if (brandColor) {
                background.color = this.shadeColor(brandColor, 0.82);
                background.originalColor = brandColor;
            }

            return background;
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

                    const backgroundEvent = this.buildDisponibilitaBackgroundEvent(o, headerEvent);

                    if (backgroundEvent) {
                        events.push(backgroundEvent);
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
