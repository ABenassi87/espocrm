
{{#if topBar}}
<div class="list-buttons-container clearfix">
    {{#if displayTotalCount}}
        <div class="text-muted total-count">
        {{translate 'Total'}}: <span class="total-count-span">{{totalCount}}</span>
        </div>
    {{/if}}

    {{#each buttonList}}
        {{button name scope=../../scope label=label style=style}}
    {{/each}}
</div>
{{/if}}

<div class="list-kanban" style="min-width: {{minTableWidthPx}}px">
    <table>
        <thead>
            <tr>
                {{#each groupDataList}}
                <th data-name="{{name}}" class="group-header">
                    <div>{{label}}</div>
                </th>
                {{/each}}
            </tr>
        </thead>
        {{#unless isEmptyList}}
        <tbody>
            <tr>
                {{#each groupDataList}}
                <td class="group-column" data-name="{{name}}">
                    <div>
                        <div class="group-column-list" data-name="{{name}}">
                            {{#each dataList}}
                            <div class="item" data-id="{{id}}">{{{var key ../../this}}}</div>
                            {{/each}}
                        </div>
                        <div class="show-more">
                            <a data-action="groupShowMore" data-name="{{name}}" title="{{translate 'Show more'}}" class="{{#unless hasShowMore}}hidden {{/unless}}btn btn-link btn-sm"><span class="glyphicon glyphicon-option-horizontal"></span></a>
                        </div>
                    </div>
                </td>
                {{/each}}
            </tr>
        </tbody>
        {{/unless}}
    </table>
</div>


{{#if isEmptyList}}
<div class="margin-top">
{{translate 'No Data'}}
</div>
{{/if}}
