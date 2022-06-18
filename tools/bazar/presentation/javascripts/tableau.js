/* Custom filtering function which will search data in column four between two values */
$.fn.dataTable.ext.search.push(
    function( settings, searchData, index, rowData, counter ) {
        let table = $(settings.nTable).DataTable();
        let row = table.rows(index);
        let node = row.nodes().to$().first();
        return TableHelper.checkDataForNode(table,node);
    }
);

const TableHelper = {
    tables: {},
    tablesByIds: {},
    checkedFilters: {},
    updateCheckedFilters: function(){
        this.checkedFilters = TableHelper.getCheckedFilters();
    },
    getBazarListeContainer: function (table){
        let tableNode = table.tables().nodes().to$();
        return $(tableNode).closest('.bazar-list');
    },
    findBazarListFiltersContainer: function (table){
        let bazarlistContainer = this.getBazarListeContainer(table);
        if (!$(bazarlistContainer).parent().hasClass('results-col')){
            return {length:0};
        } else {
            return $(bazarlistContainer).parent().siblings('.filters-col').find('.filters');
        }
    },
    updateNBResults: function (table){
        let filterContainer = this.findBazarListFiltersContainer(table);
        if (filterContainer.length > 0){
            let nbResults = table.rows({search:'applied'}).data().length;
            let nbResultInfoNode = $(filterContainer).find('.nb-results');
            if (nbResultInfoNode.length > 0){
                $(nbResultInfoNode).html(nbResults);
                if (nbResults > 1) {
                    $(filterContainer).find('.result-label').hide();
                    $(filterContainer).find('.results-label').show();
                } else {
                    $(filterContainer).find('.result-label').show();
                    $(filterContainer).find('.results-label').hide();
                }
            }
        }
    },
    updateTables: function(){
        TableHelper.updateCheckedFilters();
        Object.keys(TableHelper.tables).forEach(key => {
            let table = TableHelper.tables[key];
            table.draw();
        });
    },
    sanitizeValue: function (val){
        let sanitizedValue = val;
        if (Object.prototype.toString.call(val) === '[object Object]'){
            // because if orthogonal data is defined, valu is an object
            sanitizedValue = val.display || "";
        }
        return (isNaN(sanitizedValue)) ? 1 : Number(sanitizedValue) ;
    },
    updateFooter: function(index){
        let table = TableHelper.tables[index];
        if (typeof table !== "undefined"){
            let activatedRows = [];
            table.rows({search:'applied'}).every(function(){
                    activatedRows.push(this.index());
                });
            let activatedCols = [];
            table.columns( '.sum-activated' ).every( function () {
                activatedCols.push(this.index());
            });
            activatedCols.forEach(function(indexCol){
                var sum = 0;
                activatedRows.forEach(function(indexRow){
                    let value = table.row(indexRow).data()[indexCol];
                    sum = sum + TableHelper.sanitizeValue(value);
                });
                $(table.columns(indexCol).footer()).html(sum );
            });
        }
    },
    initTables: function (){
        TableHelper.updateCheckedFilters();
        $('.table.prevent-auto-init.in-tableau-template').each(function(){
            var index = Object.keys(TableHelper.tables).length;
            let buttons = [];
            DATATABLE_OPTIONS.buttons.forEach(function(option){
                buttons.push({
                    ...option,
                    ...{
                        footer:true,
                    },
                    ...{
                        exportOptions: (
                            option.extend != "print"
                            ? {
                                orthogonal: 'sort', // use sort data for export
                                columns: function(idx, data, node){
                                    return !$(node).hasClass('not-export-this-col');
                                },
                            }
                            : {
                                columns: function(idx, data, node){
                                let isVisible = $(node).data('visible');
                                return !$(node).hasClass('not-export-this-col') && (
                                    isVisible == undefined || isVisible != false
                                );
                            }
                        }),
                    }
                });
            });
            let table = $(this).DataTable({
                ...DATATABLE_OPTIONS,
                ...{
                        "footerCallback": function ( row, data, start, end, display ) {
                            TableHelper.updateFooter(index);
                        },
                        buttons: buttons,
                    }
                }
            );
            TableHelper.tables[index] = table;
            TableHelper.tablesByIds[$(table.table(0).node()).prop('id')] = index;
            table.on( 'draw', function () {
                TableHelper.updateNBResults(table);
            } );
            $(`#${$(table.table(0).node()).prop('id')}_wrapper`).on('dblclick',function(e){
                e.preventDefault();
                return false;
            });
        });
        TableHelper.updateTables();
    },
    init: function(){
        let helper = this;
        $('.filter-checkbox').on('click', function(){
            helper.updateTables();
        });
        this.initTables();
    },
    getCheckedFilters: function(){
        let res = {};
        Object.keys(TableHelper.tables).forEach(function(key){
            let table = TableHelper.tables[key];
            let filterContainer = TableHelper.findBazarListFiltersContainer(table);
            if (filterContainer.length == 0){
                res[key] = {};
            } else {
                let inputs = $(filterContainer).find('.filter-checkbox:checked');
                if (inputs.length == 0){
                    res[key] = {};
                } else {
                    let tableRes = {};
                    for (let index = 0; index < inputs.length; index++) {
                        let input = inputs[index];
                        let name = $(input).attr('name');
                        let value = $(input).attr('value');
                        if (tableRes.hasOwnProperty(name)){
                            tableRes[name].push(value);
                        } else {
                            tableRes[name] = [value];
                        }
                    }
                    res[key] = tableRes;
                }
            }
        });
        return res;
    },
    checkDataForNode: function(table,node){
        if(Object.keys(this.checkedFilters).length == 0){
            return true;
        } else {
            let tableId = $(table.table(0).node()).prop('id');
            if (tableId.length == 0 || !TableHelper.tablesByIds.hasOwnProperty(tableId)){
                return true;
            } else {
                let indexTable = TableHelper.tablesByIds[tableId];
                if (!TableHelper.checkedFilters.hasOwnProperty(indexTable)){
                    return true;
                } else {
                    let checkedFilters = TableHelper.checkedFilters[indexTable];
                    if(Object.keys(checkedFilters).length == 0){
                        return true;
                    } else {
                        for (const name in checkedFilters) {
                            if (checkedFilters[name].length != 0){
                                let nodeValue = $(node).attr('data-'+name);
                                if (typeof nodeValue === "undefined" || nodeValue.length == 0) {
                                    return false;
                                } else {
                                    let values = nodeValue.split(",");
                                    
                                    let resultForThisname = false;
                                    for (let index = 0; index < checkedFilters[name].length; index++) {
                                        if (!resultForThisname && values.indexOf(checkedFilters[name][index]) > -1){
                                            resultForThisname = true;
                                        }
                                    }
                                    if (!resultForThisname){
                                        return false;
                                    }
                                }
                            }
                        }
                        return true;
                    }
                }
            }
        }
    }
};
$(document).ready(function () {
    TableHelper.init();
});