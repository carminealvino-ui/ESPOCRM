{{#if collapseButton}}
<a role="button" tabindex="0" class="close-link" data-action="collapse">
    <span class="fas fa-minus"></span>
</a>
{{/if}}
<div class="cell header" data-name="header">{{header}}</div>
<div class="cell" data-name="record">
    <a href="#{{entityType}}/view/{{notificationData.id}}" data-id="{{notificationData.id}}">
        {{notificationData.name}}
    </a>
</div>
<div class="cell record no-side-margin esito-popup-fields">
    <div class="field" data-name="{{dateFieldName}}"></div>
    {{#each fieldNames}}
    <div class="field" data-name="{{this}}"></div>
    {{/each}}
</div>
<div class="cell margin-top-small">
    <button type="button" class="btn btn-primary btn-sm" data-action="saveEsito">
        {{translate 'Save'}}
    </button>
</div>
