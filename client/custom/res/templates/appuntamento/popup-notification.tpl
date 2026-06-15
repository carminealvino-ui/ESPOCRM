{{#if collapseButton}}
<a role="button" tabindex="0" class="close-link" data-action="collapse">
    <span class="fas fa-minus"></span>
</a>
{{/if}}
<div class="cell header" data-name="header">{{header}}</div>
<div class="cell" data-name="record">
    <a href="#Appuntamento/view/{{notificationData.id}}" data-id="{{notificationData.id}}">
        {{notificationData.name}}
    </a>
</div>
<div class="cell" data-name="date">
    <span class="field" data-name="dateStart"></span>
</div>
<div class="cell esito-popup-fields">
    <div class="field" data-name="status"></div>
    <div class="field" data-name="sottostato"></div>
    <div class="field" data-name="esito"></div>
    <div class="field" data-name="noteEsito"></div>
</div>
<div class="cell margin-top-small">
    <button type="button" class="btn btn-primary btn-sm" data-action="saveEsito">
        {{translate 'Save'}}
    </button>
</div>
