/* global define */

/**
 * Pass-through: non usarlo in modalViews.detail su Espo 9/10.
 */
define('custom:views/appuntamento/modals/detail', ['views/modals/detail'], function (DetailModalModule) {

    return DetailModalModule.default || DetailModalModule;
});
