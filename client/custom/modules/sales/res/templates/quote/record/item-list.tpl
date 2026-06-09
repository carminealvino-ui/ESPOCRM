<!--suppress CssUnusedSymbol, CssOverwrittenProperties -->
<style>
    [data-name="itemList"] {
        table {
            tr.ui-sortable-helper {
                border-bottom: var(--1px) solid var(--default-border-color);
            }
        }

        .drag-icon {
            cursor: grab;

            position: relative;
            top: var(--minus-1px);
        }

        .drag-icon:active {
            cursor: grabbing;
        }

        div {
            &:has(> [data-role="product-item-name"]) {
                display: flex;
            }
        }

        [data-role="product-item-name"] {
            padding-top: var(--7px);
            padding-bottom: var(--7px);

            width: 100%;

            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        [data-name="item-period"] {
            &:has(input.form-control) {
                display: grid;
                grid-template-columns: 1fr 1fr;
                grid-gap: var(--3px);

                input.form-control {
                    border-top-right-radius: var(--border-radius);
                    border-bottom-right-radius: var(--border-radius);
                }

                .input-group-btn {
                    display: none;
                }
            }

            &:not(:has(input.form-control)) {
                display: flex;

                > div[data-name="periodStartDate"] {
                    margin-right: var(--5px);

                    &::after {
                        content: " - ";
                        display: inline-block;
                        user-select: none;
                        padding-left: var(--5px);
                        color: var(--text-muted-color);
                    }
                }
            }
        }

        .compact-form {
            font-size: var(--font-size-small);

            --padding-base-horizontal: var(--6px);
            --table-cell-less-padding: var(--2px);
        }

        > [data-mode="edit"]:not(.compact-form) {
            --table-cell-less-padding: var(--2px);
            --padding-base-horizontal: var(--8px);

            .field.detail-field-container {
                .numeric-text {
                    padding-left: var(--1px);
                    padding-right: var(--1px);
                }

                font-size: var(--13px);
                //line-height: var(--18px);
            }

            .field {
                input[data-name="listPrice"] {
                    font-size: var(--13px);
                    line-height: var(--18px);

                    color: var(--gray-soft);
                }

                input {
                    font-size: var(--13px);
                    line-height: var(--18px);
                }

                textarea {
                    font-size: var(--13px);
                }
            }
        }

        input.text-align-end {
            text-align: end;
        }

        table {
            thead {
                th {
                    font-size: var(--13px);
                }

                th[data-role="last-column"] {
                    width: var(--25px);
                }
            }
        }

        > [data-mode="edit"] {
            table {
                th[data-role="last-column"] {
                    width: var(--50px);
                }
            }

            &.compact-form {
                table {
                    th[data-role="last-column"] {
                        width: calc(var(--40px) + var(--5px));
                    }
                }
            }
        }

        .field {
            .btn.btn-icon[data-action="selectProduct"] {
                width: var(--24px);

                > .fas, .far {
                    font-size: var(--12px);
                }
            }

            .input-group {
                .input-group-btn {
                    .btn-icon {
                        width: var(--28px);

                        > .fas, .far {
                            font-size: var(--12px);
                        }
                    }
                }
            }

            [data-role="taxesField"]:has(> [data-role="taxRateField"]) {

                display: grid;
                grid-template-columns: 6fr 4fr;
                grid-gap: var(--3px);
            }
        }

        .compact-form {
            [data-role="taxCodeField"] {
                .input-group {
                    .input-group-btn {
                        .btn {
                            width: var(--20px);
                        }
                    }
                }
            }
        }

        [data-role="taxCodeField"] {
            .input-group {
                .input-group-btn {
                    .btn {
                        width: var(--24px);

                        > .fas, .far {
                            font-size: var(--12px);
                        }
                    }
                }
            }
        }
    }
</style>

{{#if itemDataList.length}}
<table class="table less-padding table-bottom-bordered">
    <thead>
        <tr>
            {{#each listLayout}}
            <th
                style="
                    {{#if width}}
                        width: {{width}}%;
                    {{else}}
                        {{#if widthPx}}
                            width: {{widthPx}}px;
                        {{/if}}
                    {{/if}}
                {{#if align}}
                    text-align: {{align}};
                {{/if}}
                "
            >
                <span>
                    {{#if customLabel}}{{customLabel}}{{else}}{{translate name category='fields' scope=@root.itemEntityType}}{{/if}}
                </span>
            </th>
            {{/each}}
            {{#ifEqual mode 'edit'}}
            <th data-role="last-column">
                &nbsp;
            </th>
            {{/ifEqual}}
            {{#if showRowActions}}
            <td style="width: var(--25px)">
               &nbsp;
            </td>
            {{/if}}
        </tr>
    </thead>

    <tbody class="item-list-internal-container">
    {{#each itemDataList}}
        <tr class="item-container item-container-{{id}}" data-id="{{id}}">
        {{{var key ../this}}}
        </tr>
    {{/each}}
    </tbody>
</table>
{{/if}}
