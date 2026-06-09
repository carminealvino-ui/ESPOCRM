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
