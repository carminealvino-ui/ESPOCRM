/* global define */

/**
 * Pass-through: durata corretta in edit-small / campo duration (UTC).
 */
define('custom:views/calendar/modals/edit', ['crm:views/calendar/modals/edit'], function (CalendarEditModalModule) {

    return CalendarEditModalModule.default || CalendarEditModalModule;
});
