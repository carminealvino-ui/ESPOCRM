<div class="rifissato-create-modal">
    <p class="rifissato-create-lead">
        Scegli <strong>data e ora</strong> del nuovo appuntamento pianificato.
        L'appuntamento originale resta in agenda con esito <strong>Rifissato</strong>.
    </p>
    {{#if originalDateLabel}}
    <div class="rifissato-create-origin">
        <span class="text-muted">Data precedente</span>
        <strong>{{originalDateLabel}}</strong>
    </div>
    {{/if}}
    <div class="rifissato-record record no-side-margin"></div>
</div>
