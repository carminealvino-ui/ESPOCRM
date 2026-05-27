/* global define */

define('custom:views/calendar/calendar', ['crm:views/calendar/calendar'], function (CalendarViewModule) {

    const CalendarView = CalendarViewModule.default || CalendarViewModule;

    return class CustomCalendarView extends CalendarView {

        async createEvent(values) {
            const originalCreateView = this.createView.bind(this);

            this.createView = (name, viewName, options) => {
                if (name === 'dialog' && viewName === 'crm:views/calendar/modals/edit') {
                    viewName = 'custom:views/calendar/modals/edit';
                }

                return originalCreateView(name, viewName, options);
            };

            try {
                return await super.createEvent(values);
            } finally {
                this.createView = originalCreateView;
            }
        }
    };
});
