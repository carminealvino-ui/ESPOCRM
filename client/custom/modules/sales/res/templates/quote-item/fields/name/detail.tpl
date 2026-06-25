{{#if isProduct}}
    <span data-role="inventory-quantity">{{{inventoryQuantity}}}</span>
    <a
        href="#Product/view/{{productId}}"
        {{#if viewOnClick}} class="text-default"{{/if}}
    >{{value}}</a>
{{else}}
    {{value}}
{{/if}}
