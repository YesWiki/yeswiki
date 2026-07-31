// tableau.js — bazar "tableau" list template glue (ticket 16: vanilla JS on
// yw-datatable, replacing jQuery DataTables + its custom search plugin).
// External facet filters (.filter-checkbox in the filters sidebar) plug into
// yw-datatable via window.ywDatatableRowFilters + the yw-datatable-refresh event;
// footer sums and the results counter recompute on every yw-datatable-drawn.
const TableHelper = {
  tables: [],
  checkedFilters: {},
  findBazarListFiltersContainer(table) {
    const bazarList = table.closest('.bazar-list')
    if (!bazarList || !bazarList.parentElement) return null
    if (!bazarList.parentElement.classList.contains('results-col')) return null
    const columns = bazarList.parentElement.parentElement
    const filtersCol = columns ? columns.querySelector('.filters-col') : null
    return filtersCol ? filtersCol.querySelector('.filters') : null
  },
  updateCheckedFilters() {
    const res = {}
    this.tables.forEach((table) => {
      const filterContainer = this.findBazarListFiltersContainer(table)
      const tableFilters = {}
      if (filterContainer) {
        filterContainer.querySelectorAll('.filter-checkbox:checked').forEach((input) => {
          const name = input.getAttribute('name')
          const value = input.getAttribute('value')
          if (!tableFilters[name]) tableFilters[name] = []
          tableFilters[name].push(value)
        })
      }
      res[table.id] = tableFilters
    })
    this.checkedFilters = res
  },
  rowMatchesFilters(table, row) {
    const checked = this.checkedFilters[table.id] || {}
    return Object.keys(checked).every((name) => {
      if (checked[name].length === 0) return true
      const rowValue = row.getAttribute(`data-${name}`)
      if (!rowValue || rowValue.length === 0) return false
      const values = rowValue.split(',')
      return checked[name].some((wanted) => values.indexOf(wanted) > -1)
    })
  },
  updateNBResults(table, nbResults) {
    const filterContainer = this.findBazarListFiltersContainer(table)
    if (!filterContainer) return
    const nbResultInfoNode = filterContainer.querySelector('.nb-results')
    if (!nbResultInfoNode) return
    nbResultInfoNode.textContent = nbResults
    const single = filterContainer.querySelectorAll('.result-label')
    const plural = filterContainer.querySelectorAll('.results-label')
    single.forEach((elParam) => {
      const el = elParam
      el.style.display = nbResults > 1 ? 'none' : ''
    })
    plural.forEach((elParam) => {
      const el = elParam
      el.style.display = nbResults > 1 ? '' : 'none'
    })
  },
  sanitizeValue(val) {
    return Number.isNaN(Number(val)) ? 1 : Number(val)
  },
  updateFooter(table, matchedRows) {
    const headerCells = table.tHead ? Array.from(table.tHead.rows[0].cells) : []
    const footerRow = table.tFoot ? table.tFoot.rows[0] : null
    if (!footerRow) return
    headerCells.forEach((th, index) => {
      if (!th.classList.contains('sum-activated')) return
      let sum = 0
      matchedRows.forEach((row) => {
        const cell = row.cells[index]
        sum += this.sanitizeValue(cell ? cell.textContent.trim() : '')
      })
      const footerCell = footerRow.cells[index]
      if (footerCell) footerCell.textContent = sum
    })
  },
  refreshTables() {
    this.updateCheckedFilters()
    this.tables.forEach((table) => {
      table.dispatchEvent(new CustomEvent('yw-datatable-refresh'))
    })
  },
  init() {
    this.tables = Array.from(document.querySelectorAll('table.in-tableau-template'))
    if (this.tables.length === 0) return
    window.ywDatatableRowFilters = window.ywDatatableRowFilters || {}
    this.tables.forEach((table) => {
      window.ywDatatableRowFilters[table.id] = (row) => this.rowMatchesFilters(table, row)
      table.addEventListener('yw-datatable-drawn', (e) => {
        this.updateNBResults(table, e.detail.matchedRows.length)
        this.updateFooter(table, e.detail.matchedRows)
      })
    })
    document.addEventListener('click', (e) => {
      if (e.target.closest('.filter-checkbox')) {
        this.refreshTables()
      }
    })
    this.refreshTables()
  }
}

// ticket 14: TableHelper.init() re-scans the document, and marking each table keeps a
// second call from double-initialising one
ywInitEach('.yeswiki-tableau, table.tableau', () => {
  TableHelper.init()
})
