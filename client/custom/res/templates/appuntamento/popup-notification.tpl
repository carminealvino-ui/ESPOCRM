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
<div class="cell esito-popup-record">
    <div class="record no-side-margin"></div>
</div>
<div class="cell margin-top-small esito-popup-actions">
    <button type="button" class="btn btn-primary btn-sm" data-action="saveEsito" data-role="save">
        {{translate 'Save'}}
    </button>
    <button type="button" class="btn btn-primary btn-sm hidden" data-action="createOpportunity" data-role="create-opportunity">
        Crea Opportunità
    </button>
</div>
