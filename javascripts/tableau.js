const TableHelper = {
  findEntryListFilters(table) {
    const entryList = table.closest('.entry-list')
    if (!entryList || !entryList.parentElement) return null
    if (!entryList.parentElement.classList.contains('results-col')) return null
    const columns = entryList.parentElement.parentElement
    return columns ? columns.querySelector('.filters-col .filters') : null
  },
  updateNBResults(table, nbResults) {
    const filters = this.findEntryListFilters(table)
    const node = filters ? filters.querySelector('.nb-results') : null
    if (node) node.textContent = nbResults
  },
  cellText(row, index) {
    const cell = row.cells[index]
    return (cell ? cell.textContent.trim() : '').replace(',', '.')
  },
  sum(matchedRows, index) {
    const total = matchedRows.reduce((acc, row) => {
      const value = Number(this.cellText(row, index))
      return acc + (Number.isNaN(value) ? 1 : value)
    }, 0)
    return Number(total.toFixed(10))
  },
  average(matchedRows, index) {
    const values = matchedRows
      .map((row) => this.cellText(row, index))
      .filter((text) => text !== '' && !Number.isNaN(Number(text)))
      .map(Number)
    if (values.length === 0) return ''
    const total = values.reduce((acc, value) => acc + value, 0)
    return Math.round((total / values.length) * 100) / 100
  },
  fillFooterRow(table, rowName, headerClass, compute, matchedRows) {
    const footerRow = table.tFoot.querySelector(`[data-yw-footer="${rowName}"]`)
    if (!footerRow) return
    Array.from(table.tHead.rows[0].cells).forEach((th, index) => {
      const footerCell = footerRow.cells[index]
      if (th.classList.contains(headerClass) && footerCell) {
        footerCell.textContent = compute(matchedRows, index)
      }
    })
  },
  updateFooter(table, matchedRows) {
    if (!table.tHead || !table.tFoot) return
    const sum = this.sum.bind(this)
    const average = this.average.bind(this)
    this.fillFooterRow(table, 'sum', 'sum-activated', sum, matchedRows)
    this.fillFooterRow(
      table,
      'average',
      'average-activated',
      average,
      matchedRows,
    )
  },
  init(table) {
    table.addEventListener('yw-datatable-drawn', (e) => {
      this.updateNBResults(table, e.detail.matchedRows.length)
      this.updateFooter(table, e.detail.matchedRows)
    })
    table.dispatchEvent(new CustomEvent('yw-datatable-refresh'))
  },
}

ywInitEach('table.in-tableau-template', (table) => TableHelper.init(table))
