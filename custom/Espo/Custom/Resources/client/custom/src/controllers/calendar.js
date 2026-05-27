/* global define */

/**
 * Carica il patch durata 1h30 e delega al controller CRM standard.
 */
define('custom:controllers/calendar', [
    'custom:handlers/calendar-default-duration',
    'crm:controllers/calendar',
], function (_calendarDurationHandler, CalendarControllerModule) {

    return CalendarControllerModule.default || CalendarControllerModule;
});
