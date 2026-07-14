/* global define */

/**
 * Pass-through: non patchare la view calendario (storicamente rompeva Espo).
 * La durata 1h30 corretta e' in edit-small + campo duration custom (UTC).
 */
define('custom:views/calendar/calendar', ['crm:views/calendar/calendar'], function (CalendarViewModule) {

    return CalendarViewModule.default || CalendarViewModule;
});
