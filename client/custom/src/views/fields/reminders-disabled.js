/* global define */

/**
 * Promemoria (jsonArray) non supportato senza modulo CRM/Sales Pack.
 * Vista vuota per evitare 404 su views/fields/json-array.js in produzione.
 */
define('custom:views/fields/reminders-disabled', ['views/fields/base'], function (Dep) {

    return Dep.extend({

        detailTemplateContent: '',
        editTemplateContent: '',
        listTemplateContent: '',
    });
});
