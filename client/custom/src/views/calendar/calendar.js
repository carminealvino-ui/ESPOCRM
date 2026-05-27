/* global define */

/**
 * Non usare come calendarView: rompe il caricamento su Espo 9.
 * Il fix durata e' in custom:handlers/calendar-default-duration.
 */
define('custom:views/calendar/calendar', ['crm:views/calendar/calendar'], function (CalendarViewModule) {

    return CalendarViewModule.default || CalendarViewModule;
});
