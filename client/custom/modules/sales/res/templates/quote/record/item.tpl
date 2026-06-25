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
