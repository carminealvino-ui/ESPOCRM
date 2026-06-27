/* global define */

/**
 * NON usare in clientDefs.modalViews.detail (rompe apertura da calendario su Espo 9).
 */
define('custom:views/appuntamento/modals/detail', ['views/modals/detail'], function (DetailModalModule) {

    return DetailModalModule.default || DetailModalModule;
});
