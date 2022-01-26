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
    updateTables: function(){
        $('.table').each(function(){
            let table = $(this).DataTable();
            table.draw();
        });
    },
    init: function(){
        let helper = this;
        $('.filter-checkbox').on('click', function(){
            helper.updateTables();
        });
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

TableHelper.init();