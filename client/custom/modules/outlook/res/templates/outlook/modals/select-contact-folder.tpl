{{#unless folderDataList.length}}
    {{translate 'No Data'}}
{{/unless}}
<ul class="list-group">
{{#each folderDataList}}
    <li class="list-group-item">
        <a role="button" data-id="{{id}}" data-name="{{name}}" data-action="select">{{name}}</a>
    </li>
{{/each}}
</ul>
