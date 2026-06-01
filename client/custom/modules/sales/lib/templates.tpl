_delimiter_of7l8n4odg
custom/modules/sales/res/templates/quote-item/fields/name/list-link.tpl
<a href="#{{model.entityType}}/view/{{model.id}}">{{value}}</a>

_delimiter_of7l8n4odg
custom/modules/sales/res/templates/quote-item/fields/name/edit.tpl
<div
    class=" {{#if hasSelectProductAndNoProduct}} input-group {{/if}} "
>
    {{#if productId}}
        <div data-role="product-item-name">
            <span data-role="inventory-quantity">{{{inventoryQuantity}}}</span>
            <a
                href="{{productUrl}}"
                class="text-default"
                data-scope="Product"
                data-id="{{productId}}"
                title="{{value}}"
                target="_blank"
            >{{value}}</a>
        </div>
    {{else}}
        <input
            type="text"
            class="main-element form-control"
            data-name="{{name}}"
            {{#if isProduct}} readonly="readonly" {{/if}}
            value="{{value}}"
            {{#if params.maxLength}} maxlength="{{params.maxLength}}" {{/if}}
            autocomplete="espo-{{name}}"
        >
    {{/if}}

    {{#if hasSelectProduct}}
        <span class=" {{#unless productId}} input-group-btn {{/unless}}">
            <button
                class="btn {{#if productId}} btn-text {{else}} btn-default {{/if}} {{#if productSelectDisabled}} disabled {{/if}} btn-icon"
                data-action="selectProduct"
                title="{{translate 'Select Product' scope='Quote'}}"
            ><span class="fas fa-angle-up"></span></button>
        </span>
    {{/if}}

</div>

_delimiter_of7l8n4odg
custom/modules/sales/res/templates/quote-item/fields/name/detail.tpl
{{#if isProduct}}
    <span data-role="inventory-quantity">{{{inventoryQuantity}}}</span>
    <a
        href="#Product/view/{{productId}}"
        {{#if viewOnClick}} class="text-default"{{/if}}
    >{{value}}</a>
{{else}}
    {{value}}
{{/if}}

_delimiter_of7l8n4odg
custom/modules/sales/res/templates/quote/record/item.tpl
{{#each listLayout}}
    <td
        {{#if width}}
            width="{{width}}%"
        {{else}}
            {{#if widthPx}} width="{{widthPx}}"{{/if}}
        {{/if}}
        style="
            {{#if align}} text-align: {{align}};{{/if}}
            {{#ifEqual ../mode 'edit'}}
                {{#unless isReadOnly}} overflow: visible; {{/unless}}
            {{/ifEqual}}
        "
    >
        {{#ifEqual name "name"}}
            <div class="field{{#ifEqual @root.mode 'edit'}} {{#if isReadOnly}} detail-field-container{{/if}}{{/ifEqual}}" data-name="item-name">
            {{{@root.nameField}}}
        </div>
        {{#if ../hasPeriod}}
            <div
                class="{{#ifNotEqual ../mode 'edit'}} small {{/ifNotEqual}} {{#unless ../showPeriod}} hidden {{/unless}}"
                data-name="item-period"
            >
                <div class="field" data-name="periodStartDate">{{{../periodStartDateField}}}</div>
                <div class="field" data-name="periodEndDate">{{{../periodEndDateField}}}</div>
            </div>
        {{/if}}
        {{#if ../hasDescription}}
        <div class="field small" data-name="item-description">
            {{{@root.descriptionField}}}
        </div>
        {{/if}}
        {{else}}
        <div
            class="field {{#ifEqual align 'right'}} pull-right {{/ifEqual}}
                {{#ifEqual @root.mode 'edit'}}{{#if isReadOnly}} detail-field-container {{/if}}{{/ifEqual}}
            "
            data-name="item-{{name}}"
        >
            {{{var key @root}}}
        </div>
        {{/ifEqual}}
    </td>
{{/each}}

{{#ifEqual mode 'edit'}}
<td>
    <div class="{{#ifEqual @root.mode 'edit'}} detail-field-container{{/ifEqual}}">
        {{#ifEqual @root.mode 'edit'}}
        <a
            role="button"
            tabindex="0"
            class="pull-right"
            data-action="removeItem"
            data-id="{{id}}"
            title="{{translate 'Remove'}}"
        ><span class="fas fa-times"></span></a>
        <span
            role="button"
            class="fas fa-grip fa-sm fa-rotate-90 drag-icon text-muted"
            title="{{translate 'Sort'}}"
        ></span>
        {{/ifEqual}}
    </div>
</td>
{{/ifEqual}}
{{#if showRowActions}}
<td class="cell" data-name="buttons">
    {{{rowActions}}}
</td>
{{/if}}

_delimiter_of7l8n4odg
custom/modules/sales/res/templates/quote/record/item-list.tpl
<!--suppress CssUnusedSymbol, CssOverwrittenProperties -->
<style>
    [data-name="itemList"] {
        table {
            tr.ui-sortable-helper {
                border-bottom: var(--1px) solid var(--default-border-color);
            }
        }

        .drag-icon {
            cursor: grab;

            position: relative;
            top: var(--minus-1px);
        }

        .drag-icon:active {
            cursor: grabbing;
        }

        div {
            &:has(> [data-role="product-item-name"]) {
                display: flex;
            }
        }

        [data-role="product-item-name"] {
            padding-top: var(--7px);
            padding-bottom: var(--7px);

            width: 100%;

            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        [data-name="item-period"] {
            &:has(input.form-control) {
                display: grid;
                grid-template-columns: 1fr 1fr;
                grid-gap: var(--3px);

                input.form-control {
                    border-top-right-radius: var(--border-radius);
                    border-bottom-right-radius: var(--border-radius);
                }

                .input-group-btn {
                    display: none;
                }
            }

            &:not(:has(input.form-control)) {
                display: flex;

                > div[data-name="periodStartDate"] {
                    margin-right: var(--5px);

                    &::after {
                        content: " - ";
                        display: inline-block;
                        user-select: none;
                        padding-left: var(--5px);
                        color: var(--text-muted-color);
                    }
                }
            }
        }

        .compact-form {
            font-size: var(--font-size-small);

            --padding-base-horizontal: var(--6px);
            --table-cell-less-padding: var(--2px);
        }

        > [data-mode="edit"]:not(.compact-form) {
            --table-cell-less-padding: var(--2px);
            --padding-base-horizontal: var(--8px);

            .field.detail-field-container {
                .numeric-text {
                    padding-left: var(--1px);
                    padding-right: var(--1px);
                }

                font-size: var(--13px);
                //line-height: var(--18px);
            }

            .field {
                input[data-name="listPrice"] {
                    font-size: var(--13px);
                    line-height: var(--18px);

                    color: var(--gray-soft);
                }

                input {
                    font-size: var(--13px);
                    line-height: var(--18px);
                }

                textarea {
                    font-size: var(--13px);
                }
            }
        }

        input.text-align-end {
            text-align: end;
        }

        table {
            thead {
                th {
                    font-size: var(--13px);
                }

                th[data-role="last-column"] {
                    width: var(--25px);
                }
            }
        }

        > [data-mode="edit"] {
            table {
                th[data-role="last-column"] {
                    width: var(--50px);
                }
            }

            &.compact-form {
                table {
                    th[data-role="last-column"] {
                        width: calc(var(--40px) + var(--5px));
                    }
                }
            }
        }

        .field {
            .btn.btn-icon[data-action="selectProduct"] {
                width: var(--24px);

                > .fas, .far {
                    font-size: var(--12px);
                }
            }

            .input-group {
                .input-group-btn {
                    .btn-icon {
                        width: var(--28px);

                        > .fas, .far {
                            font-size: var(--12px);
                        }
                    }
                }
            }

            [data-role="taxesField"]:has(> [data-role="taxRateField"]) {

                display: grid;
                grid-template-columns: 6fr 4fr;
                grid-gap: var(--3px);
            }
        }

        .compact-form {
            [data-role="taxCodeField"] {
                .input-group {
                    .input-group-btn {
                        .btn {
                            width: var(--20px);
                        }
                    }
                }
            }
        }

        [data-role="taxCodeField"] {
            .input-group {
                .input-group-btn {
                    .btn {
                        width: var(--24px);

                        > .fas, .far {
                            font-size: var(--12px);
                        }
                    }
                }
            }
        }
    }
</style>

{{#if itemDataList.length}}
<table class="table less-padding table-bottom-bordered">
    <thead>
        <tr>
            {{#each listLayout}}
            <th
                style="
                    {{#if width}}
                        width: {{width}}%;
                    {{else}}
                        {{#if widthPx}}
                            width: {{widthPx}}px;
                        {{/if}}
                    {{/if}}
                {{#if align}}
                    text-align: {{align}};
                {{/if}}
                "
            >
                <span>
                    {{#if customLabel}}{{customLabel}}{{else}}{{translate name category='fields' scope=@root.itemEntityType}}{{/if}}
                </span>
            </th>
            {{/each}}
            {{#ifEqual mode 'edit'}}
            <th data-role="last-column">
                &nbsp;
            </th>
            {{/ifEqual}}
            {{#if showRowActions}}
            <td style="width: var(--25px)">
               &nbsp;
            </td>
            {{/if}}
        </tr>
    </thead>

    <tbody class="item-list-internal-container">
    {{#each itemDataList}}
        <tr class="item-container item-container-{{id}}" data-id="{{id}}">
        {{{var key ../this}}}
        </tr>
    {{/each}}
    </tbody>
</table>
{{/if}}

_delimiter_of7l8n4odg
custom/modules/sales/res/templates/quote/record/panels/items.tpl
<div class="cell cell-itemList" data-name="itemList">
    <label class="field-label"></label>
    <div class="field field-itemList" data-name="itemList">{{{itemListField}}}</div>
</div>

_delimiter_of7l8n4odg
custom/modules/sales/res/templates/quote/fields/item-list/edit.tpl
<!--suppress CssUnusedSymbol -->
<style>
    [data-name="itemList"] {
        [data-role="total-cell-right"] {
            [data-role="total-sub-cell"] {
                display: grid;
                grid-template-columns: 6fr 4fr;
                grid-gap: var(--10px);

                align-items: center;

                > div:first-child {
                    text-align: end;

                    color: var(--text-muted-color);
                }

                > div:last-child {
                    text-align: end;

                    &[data-name="total-grandTotalAmount"] {
                        font-weight: 500;
                    }
                }

                min-height: var(--30px);
            }

            > div {
                input {
                    font-size: var(--13px);
                    height: var(--28px);
                    min-height: var(--28px);

                    padding-left: var(--8px);
                    padding-right: var(--8px);

                    &.numeric-text {
                        text-align: end;
                    }

                    margin-bottom: var(--2px);
                }
            }
        }

        .totals-row {
            margin-bottom: var(--2px);
        }
    }
</style>

<div
    class="item-list-container list no-side-margin no-focus-outline {{#if isCompactForm}} compact-form {{/if}} "
    tabindex="-1"
    data-mode="edit"
>{{{itemList}}}</div>
<div class="button-container {{#if isLoading}} hidden {{/if}} margin-top-2x">
    <div class="btn-group">
        <button
            class="btn btn-default btn-icon radius-right"
            data-action="addItem"
            title="{{translate 'Add Item' scope='Quote'}}"
        ><span class="fas fa-plus"></span></button>
        {{#if hasMenu}}
            <button
                type="button"
                class="btn btn-text btn-icon dropdown-toggle"
                data-toggle="dropdown"
            ><span class="fas fa-ellipsis-h"></span></button>
            <ul class="dropdown-menu">
                {{#if showAddProducts}}
                    <li>
                        <a
                            role="button"
                            data-action="addProducts"
                            class="action"
                        >{{translate 'Add Products' scope='Opportunity'}}</a>
                    </li>
                    {{#if hasDividerInMenu}}
                        <li class="divider"></li>
                    {{/if}}
                {{/if}}
                {{#if showApplyPriceBook}}
                    <li>
                        <a
                            role="button"
                            data-action="applyPriceBook"
                            class="action"
                        >{{translate 'Apply Price Book' scope='Quote'}}</a>
                    </li>
                {{/if}}
                {{#if showApplyTax}}
                    <li>
                        <a
                            role="button"
                            data-action="applyTax"
                            class="action"
                        >{{translate 'applyTaxProfile' category='texts' scope='Quote'}}</a>
                    </li>
                {{/if}}
            </ul>
        {{/if}}
    </div>
</div>

{{#if hasTotals}}
    <div class="row totals-row margin-top-2x">
        <div class="column col-sm-3 col-xs-4">
            {{#if hasCurrency}}
                <div class="cell form-group">
                    <label class="control-label">
                        {{translate 'currency' category='fields' scope=scope}}
                    </label>
                    <div class="field" data-name="total-currency">{{{currencyField}}}</div>
                    {{#if hasCurrencyRateField}}
                        <div class="cell form-group">
                            <div class="field" data-name="currencyRate">{{{currencyRateField}}}</div>
                        </div>
                    {{/if}}
                </div>
            {{/if}}
            {{#if hasShippingCostField}}
                <div class="cell form-group">
                    <label class="control-label">
                        {{translate 'shippingCost' category='fields' scope=scope}}
                    </label>
                    <div class="field" data-role="shippingCostField">{{{shippingCostField}}}</div>
                </div>
            {{/if}}
        </div>
        <div
            class="column col-sm-6 col-sm-offset-3 col-xs-8 {{#unless showFields}} hidden {{/unless}} "
        >
            {{#each totalLayout}}
                <div
                    class="cell"
                    data-role="total-cell-right"
                >
                    <div>
                        <div data-role="total-sub-cell">
                            <div>{{translate name category='fields' scope=../scope}}</div>
                            <div class="field" data-name="total-{{name}}">
                                {{{var key ../this}}}
                            </div>
                        </div>
                    </div>
                </div>
            {{/each}}
        </div>
    </div>
{{/if}}

_delimiter_of7l8n4odg
custom/modules/sales/res/templates/quote/fields/item-list/detail.tpl
<!--suppress CssUnusedSymbol -->
<style>
    [data-name="itemList"] {
        [data-role="total-cell-right"] {
            [data-role="total-sub-cell"] {
                display: grid;
                grid-template-columns: 6fr 4fr;
                grid-gap: var(--8px);

                align-items: center;

                > div:first-child {
                    text-align: end;

                    color: var(--text-muted-color);
                }

                > div:last-child {
                    text-align: end;

                    &[data-name="total-grandTotalAmount"] {
                        font-size: var(--14px);
                        font-weight: 500;
                    }
                }

                min-height: var(--30px);
            }
        }

        .totals-row {
            margin-bottom: var(--2px);
        }
    }
</style>

{{#if isEmpty}}
    {{#ifNotEqual mode 'edit'}}
        {{#if isSet}}
            <div class="form-group none-value">{{translate 'None'}}</div>
        {{else}}
            <div class="margin-bottom"><span class="loading-value"></span></div>
        {{/if}}
    {{/ifNotEqual}}
{{/if}}

<div
    class="item-list-container list no-side-margin {{#if showFields}} margin-bottom-2x {{/if}} {{#if isCompactForm}} compact-form {{/if}} "
    data-mode="detail"
>{{{itemList}}}</div>

{{#if hasTotals}}
    <div class="row{{#unless showFields}} hidden{{/unless}} totals-row margin-top-2x">
        <div class="column col-sm-3 col-xs-4">
            {{#if hasCurrencyRateField}}
                <div class="cell form-group">
                    <div class="field" data-role="currencyRate">{{{currencyRateField}}}</div>
                </div>
            {{/if}}
            {{#if hasShippingCostField}}
                <div class="cell form-group">
                    <label class="control-label">
                        {{translate 'shippingCost' category='fields' scope=scope}}
                    </label>
                    <div class="field" data-role="shippingCostField">{{{shippingCostField}}}</div>
                </div>
            {{/if}}
        </div>
        <div class="column col-sm-6 col-sm-offset-3 col-xs-8 {{#unless showFields}} hidden {{/unless}} ">
            {{#each totalLayout}}
                <div
                    class="cell"
                    data-role="total-cell-right"
                >
                    <div data-role="total-sub-cell">
                        <div>{{translate name category='fields' scope=../scope}}</div>
                        <div class="field" data-name="total-{{name}}">
                            {{{var key ../this}}}
                        </div>
                    </div>
                </div>
            {{/each}}
        </div>
    </div>
{{/if}}

_delimiter_of7l8n4odg
custom/modules/sales/res/templates/opportunity/fields/item-list/edit.tpl
<div
    class="item-list-container list no-side-margin no-focus-outline {{#if isCompactForm}} compact-form {{/if}} "
    tabindex="-1"
    data-mode="edit"
>{{{itemList}}}</div>
<div class="button-container margin-top-2x {{#if isLoading}} hidden{{/if}}">
    <div class="btn-group">
        <button
            class="btn btn-default btn-icon radius-right"
            data-action="addItem"
            title="{{translate 'Add Item' scope='Opportunity'}}"
        ><span class="fas fa-plus"></span></button>
        {{#if showAddProducts}}
            <button
                type="button"
                class="btn btn-text btn-icon dropdown-toggle"
                data-toggle="dropdown"
            ><span class="fas fa-ellipsis-h"></span></button>
            <ul class="dropdown-menu">
                {{#if showAddProducts}}
                    <li>
                        <a
                            role="button"
                            data-action="addProducts"
                            class="action"
                        >{{translate 'Add Products' scope='Opportunity'}}</a>
                    </li>
                {{/if}}
                {{#if showApplyPriceBook}}
                    <li>
                        <a
                            role="button"
                            data-action="applyPriceBook"
                            class="action"
                        >{{translate 'Apply Price Book' scope='Quote'}}</a>
                    </li>
                {{/if}}
            </ul>
        {{/if}}
    </div>
</div>
{{#if hasCurrency}}
    <div class="row">
        <div class="cell col-md-2 col-sm-3 col-xs-6 form-group">
            <label class="control-label">
                {{translate 'currency' category='fields' scope='Quote'}}
            </label>
            <div class="field" data-name="total-currency">{{{currencyField}}}</div>
        </div>
    </div>
{{/if}}


_delimiter_of7l8n4odg
custom/modules/sales/res/templates/opportunity/fields/item-list/detail.tpl
{{#if isEmpty}}
    {{#ifNotEqual mode 'edit'}}
        {{#if isSet}}
            <div class="form-group none-value">{{translate 'None'}}</div>
        {{else}}
            <div class="margin-bottom"><span class="loading-value"></span></div>
        {{/if}}
    {{/ifNotEqual}}
{{/if}}

<div
    class="item-list-container list no-side-margin margin-bottom {{#if isCompactForm}} compact-form {{/if}} "
    data-mode="detail"
>{{{itemList}}}</div>
