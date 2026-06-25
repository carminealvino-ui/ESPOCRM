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
