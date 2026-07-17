{{#if isProduct}}
    <span data-role="inventory-quantity">{{{inventoryQuantity}}}</span>
    <a
        href="#Product/view/{{productId}}"
        class=" {{#if viewOnClick}} text-default {{/if}} text-record"
    >{{value}}</a>
{{else}}
    {{value}}
{{/if}}
