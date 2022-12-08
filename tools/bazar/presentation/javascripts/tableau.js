/* Custom filtering function which will search data in column four between two values */
$.fn.dataTable.ext.search.push(
  (settings, searchData, index, rowData, counter) => {
    const table = $(settings.nTable).DataTable()
    const row = table.rows(index)
    const node = row.nodes().to$().first()
    return TableHelper.checkDataForNode(table, node)
  }
)

const TableHelper = {
  tables: {},
  tablesByIds: {},
  checkedFilters: {},
  updateCheckedFilters() {
    this.checkedFilters = TableHelper.getCheckedFilters()
  },
  getBazarListeContainer(table) {
    const tableNode = table.tables().nodes().to$()
    return $(tableNode).closest('.bazar-list')
  },
  findBazarListFiltersContainer(table) {
    const bazarlistContainer = this.getBazarListeContainer(table)
    if (!$(bazarlistContainer).parent().hasClass('results-col')) {
      return { length: 0 }
    }
    return $(bazarlistContainer).parent().siblings('.filters-col').find('.filters')
  },
  updateNBResults(table) {
    const filterContainer = this.findBazarListFiltersContainer(table)
    if (filterContainer.length > 0) {
      const nbResults = table.rows({ search: 'applied' }).data().length
      const nbResultInfoNode = $(filterContainer).find('.nb-results')
      if (nbResultInfoNode.length > 0) {
        $(nbResultInfoNode).html(nbResults)
        if (nbResults > 1) {
          $(filterContainer).find('.result-label').hide()
          $(filterContainer).find('.results-label').show()
        } else {
          $(filterContainer).find('.result-label').show()
          $(filterContainer).find('.results-label').hide()
        }
      }
    }
  },
  updateTables() {
    TableHelper.updateCheckedFilters()
    Object.keys(TableHelper.tables).forEach((key) => {
      const table = TableHelper.tables[key]
      table.draw()
    })
  },
  sanitizeValue(val) {
    let sanitizedValue = val
    if (Object.prototype.toString.call(val) === '[object Object]') {
      // because if orthogonal data is defined, valu is an object
      sanitizedValue = val.display || ''
    }
    return (isNaN(sanitizedValue)) ? 1 : Number(sanitizedValue)
  },
  updateFooter(index) {
    const table = TableHelper.tables[index]
    if (typeof table !== 'undefined') {
      const activatedRows = []
      table.rows({ search: 'applied' }).every(function() {
        activatedRows.push(this.index())
      })
      const activatedCols = []
      table.columns('.sum-activated').every(function() {
        activatedCols.push(this.index())
      })
      activatedCols.forEach((indexCol) => {
        let sum = 0
        activatedRows.forEach((indexRow) => {
          const value = table.row(indexRow).data()[indexCol]
          sum += TableHelper.sanitizeValue(value)
        })
        $(table.columns(indexCol).footer()).html(sum)
      })
    }
  },
  initTables() {
    TableHelper.updateCheckedFilters()
    $('.table.prevent-auto-init.in-tableau-template').each(function() {
      const index = Object.keys(TableHelper.tables).length
      const buttons = []
      DATATABLE_OPTIONS.buttons.forEach((option) => {
        buttons.push({
          ...option,
          ...{ footer: true },
          ...{
            exportOptions: (
              option.extend != 'print'
                ? {
                  orthogonal: 'sort', // use sort data for export
                  columns(idx, data, node) {
                    return !$(node).hasClass('not-export-this-col')
                  }
                }
                : {
                  columns(idx, data, node) {
                    const isVisible = $(node).data('visible')
                    return !$(node).hasClass('not-export-this-col') && (
                      isVisible == undefined || isVisible != false
                    )
                  }
                })
          }
        })
      })
      const table = $(this).DataTable({
        ...DATATABLE_OPTIONS,
        ...{
          footerCallback(row, data, start, end, display) {
            TableHelper.updateFooter(index)
          },
          buttons
        }
      })
      TableHelper.tables[index] = table
      TableHelper.tablesByIds[$(table.table(0).node()).prop('id')] = index
      table.on('draw', () => {
        TableHelper.updateNBResults(table)
      })
      $(`#${$(table.table(0).node()).prop('id')}_wrapper`).on('dblclick', (e) => {
        e.preventDefault()
        return false
      })
    })
    TableHelper.updateTables()
  },
  init() {
    const helper = this
    $('.filter-checkbox').on('click', () => {
      helper.updateTables()
    })
    this.initTables()
  },
  getCheckedFilters() {
    const res = {}
    Object.keys(TableHelper.tables).forEach((key) => {
      const table = TableHelper.tables[key]
      const filterContainer = TableHelper.findBazarListFiltersContainer(table)
      if (filterContainer.length == 0) {
        res[key] = {}
      } else {
        const inputs = $(filterContainer).find('.filter-checkbox:checked')
        if (inputs.length == 0) {
          res[key] = {}
        } else {
          const tableRes = {}
          for (let index = 0; index < inputs.length; index++) {
            const input = inputs[index]
            const name = $(input).attr('name')
            const value = $(input).attr('value')
            if (tableRes.hasOwnProperty(name)) {
              tableRes[name].push(value)
            } else {
              tableRes[name] = [value]
            }
          }
          res[key] = tableRes
        }
      }
    })
    return res
  },
  checkDataForNode(table, node) {
    if (Object.keys(this.checkedFilters).length == 0) {
      return true
    }
    const tableId = $(table.table(0).node()).prop('id')
    if (tableId.length == 0 || !TableHelper.tablesByIds.hasOwnProperty(tableId)) {
      return true
    }
    const indexTable = TableHelper.tablesByIds[tableId]
    if (!TableHelper.checkedFilters.hasOwnProperty(indexTable)) {
      return true
    }
    const checkedFilters = TableHelper.checkedFilters[indexTable]
    if (Object.keys(checkedFilters).length == 0) {
      return true
    }
    for (const name in checkedFilters) {
      if (checkedFilters[name].length != 0) {
        const nodeValue = $(node).attr(`data-${name}`)
        if (typeof nodeValue === 'undefined' || nodeValue.length == 0) {
          return false
        }
        const values = nodeValue.split(',')

        let resultForThisname = false
        for (let index = 0; index < checkedFilters[name].length; index++) {
          if (!resultForThisname && values.indexOf(checkedFilters[name][index]) > -1) {
            resultForThisname = true
          }
        }
        if (!resultForThisname) {
          return false
        }
      }
    }
    return true
  }
}
$(document).ready(() => {
  TableHelper.init()
})
