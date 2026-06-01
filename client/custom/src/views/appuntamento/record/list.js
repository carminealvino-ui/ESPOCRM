/* global define */

define('custom:views/appuntamento/record/list', ['views/record/list'], function (Dep) {

    const NO_TRUNCATE_FIELDS = [
        'dataAppuntamento',
        'name',
        'sottostato',
        'esito',
        'opportunita',
    ];

    const LIST_FIX_STYLE_ID = 'appuntamento-list-no-truncate-style';

    return Dep.extend({

        checkboxes: false,

        massActionList: [],

        checkAllResultMassActionList: [],

        rowActionsColumnWidth: 22,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.checkboxes = false;
            this.massActionList = [];
            this.checkAllResultMassActionList = [];
            this.massActionsDisabled = true;

            this.injectListFixStyles();

            this.listenTo(this.collection, 'sync', () => {
                this.applyNoTruncateColumns();
            });
        },

        injectListFixStyles: function () {
            if (document.getElementById(LIST_FIX_STYLE_ID)) {
                return;
            }

            const style = document.createElement('style');
            style.id = LIST_FIX_STYLE_ID;
            style.textContent = `
.list-appuntamento-fullwidth .list > table,
[data-entity-type="Appuntamento"] .list > table {
    width: 100% !important;
    table-layout: auto !important;
}
.list-appuntamento-fullwidth .list > table th[data-name="dataAppuntamento"],
.list-appuntamento-fullwidth .list > table td[data-name="dataAppuntamento"],
.list-appuntamento-fullwidth .list > table th[data-name="name"],
.list-appuntamento-fullwidth .list > table td[data-name="name"],
.list-appuntamento-fullwidth .list > table th[data-name="sottostato"],
.list-appuntamento-fullwidth .list > table td[data-name="sottostato"],
.list-appuntamento-fullwidth .list > table th[data-name="esito"],
.list-appuntamento-fullwidth .list > table td[data-name="esito"],
.list-appuntamento-fullwidth .list > table th[data-name="opportunita"],
.list-appuntamento-fullwidth .list > table td[data-name="opportunita"],
[data-entity-type="Appuntamento"] .list > table th[data-name="dataAppuntamento"],
[data-entity-type="Appuntamento"] .list > table td[data-name="dataAppuntamento"],
[data-entity-type="Appuntamento"] .list > table th[data-name="name"],
[data-entity-type="Appuntamento"] .list > table td[data-name="name"],
[data-entity-type="Appuntamento"] .list > table th[data-name="sottostato"],
[data-entity-type="Appuntamento"] .list > table td[data-name="sottostato"],
[data-entity-type="Appuntamento"] .list > table th[data-name="esito"],
[data-entity-type="Appuntamento"] .list > table td[data-name="esito"],
[data-entity-type="Appuntamento"] .list > table th[data-name="opportunita"],
[data-entity-type="Appuntamento"] .list > table td[data-name="opportunita"] {
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: unset !important;
    word-break: break-word !important;
    max-width: none !important;
}
.list-appuntamento-fullwidth .list > table td[data-name="name"] a,
.list-appuntamento-fullwidth .list > table td[data-name="name"] .cell-content,
.list-appuntamento-fullwidth .list > table td[data-name="sottostato"] .cell-content,
.list-appuntamento-fullwidth .list > table td[data-name="sottostato"] .label,
.list-appuntamento-fullwidth .list > table td[data-name="esito"] .cell-content,
.list-appuntamento-fullwidth .list > table td[data-name="opportunita"] a,
.list-appuntamento-fullwidth .list > table td[data-name="opportunita"] .cell-content,
.list-appuntamento-fullwidth .list > table td[data-name="opportunita"] .link-multiple,
[data-entity-type="Appuntamento"] .list > table td a,
[data-entity-type="Appuntamento"] .list > table td .cell-content,
[data-entity-type="Appuntamento"] .list > table td .label {
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: unset !important;
    max-width: none !important;
}
`;
            document.head.appendChild(style);
        },

        applyNoTruncateColumns: function () {
            const $table = this.$el.find('.list > table');

            if (!$table.length) {
                return;
            }

            NO_TRUNCATE_FIELDS.forEach((fieldName) => {
                $table.find(`th[data-name="${fieldName}"], td[data-name="${fieldName}"]`).each(function () {
                    const $cell = $(this);
                    const css = {
                        whiteSpace: 'normal',
                        overflow: 'visible',
                        textOverflow: 'clip',
                        wordBreak: 'break-word',
                        maxWidth: 'none',
                    };

                    $cell.css(css);
                    $cell.find('a, span, .cell-content, .label, .link-multiple-item').css(css);
                });
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.$el.addClass('list-appuntamento-fullwidth');
            this.$el.attr('data-entity-type', 'Appuntamento');

            this.$el.find('th.checkbox-cell, td.checkbox-cell').addClass('hidden');

            this.applyNoTruncateColumns();
        },
    });
});
