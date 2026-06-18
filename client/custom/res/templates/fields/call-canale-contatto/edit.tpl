<div class="call-canale-contatto">
{{#each options}}
    <label class="radio-inline" style="margin-right: 16px;">
        <input
            type="radio"
            name="canaleContatto"
            data-name="canaleContatto"
            value="{{value}}"
        >
        {{label}}
    </label>
{{/each}}
</div>
