/* global define */

/**
 * Scheda Appuntamento: usa views/record/detail (non meeting → evita 404 Espo).
 */
define('custom:views/appuntamento/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({});
});
