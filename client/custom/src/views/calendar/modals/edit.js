/* global define */

/**
 * Backup modale (non usato se il patch handler e' attivo).
 * Mantenuto per deploy opzionale.
 */
define('custom:views/calendar/modals/edit', ['crm:views/calendar/modals/edit'], function (CalendarEditModalModule) {

    return CalendarEditModalModule.default || CalendarEditModalModule;
});
