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
