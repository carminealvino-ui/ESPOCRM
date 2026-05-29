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
