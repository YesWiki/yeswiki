/* Custom filtering function which will search data in column four between two values */
$.fn.dataTable.ext.search.push(
    function( settings, searchData, index, rowData, counter ) {
        let table = $(settings.nTable).DataTable();
        let row = table.rows(index);
        let node = row.nodes().to$().first();
        return TableHelper.checkDataForNode(TableHelper.getCheckedFilters(),node);
    }
);

const TableHelper = {
    tables: {},
    updateTables: function(){
        Object.keys(TableHelper.tables).forEach(key => {
            let table = TableHelper.tables[key];
            table.draw();
        });
    },
    sanitizeValue: function (val){
        return (isNaN(val)) ? 1 : Number(val) ;
    },
    updateFooter: function(index,footer){
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
        $('.table.prevent-auto-init.in-tableau-template').each(function(){
            
            var index = Object.keys(TableHelper.tables).length;
            let buttons = [];
            DATATABLE_OPTIONS.buttons.forEach(function(option){
                buttons.push({
                    ...option,
                    ...{
                        footer:true
                    }
                });
            });
            let table = $(this).DataTable({
                ...DATATABLE_OPTIONS,
                ...{
                        "footerCallback": function ( row, data, start, end, display ) {
                            TableHelper.updateFooter(index,row);
                        },
                        buttons: buttons,
                    }
                }
            );
            TableHelper.tables[index] = table;
            table.draw();
        });
    },
    init: function(){
        let helper = this;
        $('.filter-checkbox').on('click', function(){
            helper.updateTables();
        });
        this.initTables();
    },
    getCheckedFilters: function(){
        let inputs = $('.filter-checkbox:checked');
        if (inputs.length == 0){
            return {};
        } else {
            let res = {};
            for (let index = 0; index < inputs.length; index++) {
                let input = inputs[index];
                let name = $(input).attr('name');
                let value = $(input).attr('value');
                if (res.hasOwnProperty(name)){
                    res[name].push(value);
                } else {
                    res[name] = [value];
                }
            }
            return res;
        }
    },
    checkDataForNode: function(checkedFilters,node){
        if(Object.keys(checkedFilters).length == 0){
            return true;
        } else {
            for (const name in checkedFilters) {
                if (checkedFilters[name].length == 0){
                    return true;
                } else {
                    let nodeValue = $(node).attr('data-'+name);
                    if (typeof nodeValue === "undefined" || nodeValue.length == 0) {
                        return false;
                    } else {
                        let values = nodeValue.split(",");
                        
                        for (let index = 0; index < checkedFilters[name].length; index++) {
                            if (values.indexOf(checkedFilters[name][index]) > -1){
                                return true;
                            }
                        }
                        return false;
                    }
                }
            }
        }
    }
};
$(document).ready(function () {
    TableHelper.init();
});