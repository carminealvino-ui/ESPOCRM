/* global define */

/**
 * NON usare in clientDefs.modalViews.detail (rompe apertura da calendario su Espo 9).
 */
define('custom:views/appuntamento/modals/detail', ['crm:views/meeting/modals/detail'], function (MeetingDetailModalModule) {

    return MeetingDetailModalModule.default || MeetingDetailModalModule;
});
