// yw-datatable.js — vanilla sort/search/paginate table component (ticket 16, replaces
// jQuery DataTables). Auto-initializes any `<table data-yw-datatable>` already in the
// document plus any added later (MutationObserver), matching DataTables' old
// auto-init-any-qualifying-table convenience. No jQuery.
(function() {
  const DEFAULT_PAGE_SIZE = 10

  function cellText(cell) {
    return (cell.textContent || '').trim()
  }

  function rowMatches(row, needle) {
    if (!needle) return true
    return Array.from(row.cells).some((cell) => cellText(cell).toLowerCase().includes(needle))
  }

  function compareRows(a, b, colIndex, direction) {
    const av = cellText(a.cells[colIndex])
    const bv = cellText(b.cells[colIndex])
    const an = parseFloat(av.replace(',', '.'))
    const bn = parseFloat(bv.replace(',', '.'))
    const looksNumeric = /^-?[\d.,]+$/.test(av) && /^-?[\d.,]+$/.test(bv)
    const result = looksNumeric && !Number.isNaN(an) && !Number.isNaN(bn)
      ? an - bn
      : av.localeCompare(bv, undefined, { sensitivity: 'base' })
    return direction === 'desc' ? -result : result
  }

  function renderPagination(container, page, totalPages, onGoTo) {
    container.replaceChildren()
    if (totalPages <= 1) return

    function addItem(label, targetPage, opts = {}) {
      const li = document.createElement('li')
      const activeClass = opts.active ? ' yw-pagination__item--active' : ''
      const disabledClass = opts.disabled
        ? ' yw-pagination__item--disabled'
        : ''
      li.className = `yw-pagination__item${activeClass}${disabledClass}`
      const a = document.createElement('a')
      a.className = 'yw-pagination__link'
      a.href = '#'
      a.textContent = label
      if (opts.disabled || opts.active) {
        a.setAttribute('tabindex', '-1')
      } else {
        a.addEventListener('click', (e) => {
          e.preventDefault()
          onGoTo(targetPage)
        })
      }
      li.appendChild(a)
      container.appendChild(li)
    }

    addItem('«', page - 1, { disabled: page <= 1 })

    const windowSize = 2
    const start = Math.max(1, page - windowSize)
    const end = Math.min(totalPages, page + windowSize)

    if (start > 1) {
      addItem('1', 1)
      if (start > 2) addItem('…', 0, { disabled: true })
    }
    for (let p = start; p <= end; p += 1) {
      addItem(String(p), p, { active: p === page })
    }
    if (end < totalPages) {
      if (end < totalPages - 1) addItem('…', 0, { disabled: true })
      addItem(String(totalPages), totalPages)
    }

    addItem('»', page + 1, { disabled: page >= totalPages })
  }

  function initDataTable(table) {
    table.setAttribute('data-yw-datatable-ready', '')
    table.classList.add('yw-table')

    const tbody = table.tBodies[0]
    if (!tbody) return
    const allRows = Array.from(tbody.rows)
    const headerCells = table.tHead ? Array.from(table.tHead.rows[0].cells) : []

    const pageSize = parseInt(table.getAttribute('data-yw-page-size'), 10) || DEFAULT_PAGE_SIZE
    const noSearch = table.hasAttribute('data-yw-no-search')
    const noPaginate = table.hasAttribute('data-yw-no-paginate')

    let searchTerm = ''
    let sortCol = null
    let sortDir = 'asc'
    let page = 1

    // Optional initial sort, e.g. data-yw-datatable-sort="0,desc" (replaces
    // DataTables' data-order attribute)
    const initialSort = (table.getAttribute('data-yw-datatable-sort') || '').split(',')
    if (initialSort.length === 2) {
      const initialCol = parseInt(initialSort[0], 10)
      if (!Number.isNaN(initialCol)) {
        sortCol = initialCol
        sortDir = initialSort[1].trim() === 'desc' ? 'desc' : 'asc'
      }
    }

    if (!noSearch) {
      const toolbar = document.createElement('div')
      toolbar.className = 'yw-datatable__toolbar'
      const search = document.createElement('input')
      search.type = 'search'
      search.className = 'yw-input yw-datatable__search'
      search.placeholder = _t('DATATABLE_SEARCH_PLACEHOLDER') ?? 'Search...'
      toolbar.appendChild(search)
      table.parentNode.insertBefore(toolbar, table)
      search.addEventListener('input', () => {
        searchTerm = search.value.trim().toLowerCase()
        page = 1
        render()
      })
    }

    let paginationEl = null
    if (!noPaginate) {
      paginationEl = document.createElement('ul')
      paginationEl.className = 'yw-pagination yw-datatable__pagination'
      table.parentNode.insertBefore(paginationEl, table.nextSibling)
    }

    const emptyRow = document.createElement('tr')
    const emptyCell = document.createElement('td')
    emptyCell.className = 'yw-datatable__empty'
    emptyCell.colSpan = Math.max(
      1,
      headerCells.length || (allRows[0] ? allRows[0].cells.length : 1)
    )
    emptyCell.textContent = _t('DATATABLE_NO_RESULTS') ?? 'No matching results'
    emptyRow.appendChild(emptyCell)

    headerCells.forEach((th, index) => {
      if (th.hasAttribute('data-yw-no-sort')) return
      th.setAttribute('data-yw-sort', index === sortCol ? sortDir : '')
      th.addEventListener('click', () => {
        if (sortCol === index) {
          sortDir = sortDir === 'asc' ? 'desc' : 'asc'
        } else {
          sortCol = index
          sortDir = 'asc'
        }
        headerCells.forEach((otherTh, otherIndex) => {
          if (otherTh.hasAttribute('data-yw-sort')) {
            otherTh.setAttribute(
              'data-yw-sort',
              otherIndex === sortCol ? sortDir : ''
            )
          }
        })
        page = 1
        render()
      })
    })

    function render() {
      // optional external row filter, registered per table id (e.g. the bazar
      // tableau template's checkbox facet filters)
      const filters = window.ywDatatableRowFilters || {}
      const customFilter = table.id ? filters[table.id] : null
      let rows = allRows.filter(
        (row) => rowMatches(row, searchTerm) && (!customFilter || customFilter(row))
      )

      if (sortCol !== null) {
        rows = rows.slice().sort((a, b) => compareRows(a, b, sortCol, sortDir))
      }

      const totalPages = noPaginate
        ? 1
        : Math.max(1, Math.ceil(rows.length / pageSize))
      if (page > totalPages) page = totalPages

      const pageRows = noPaginate
        ? rows
        : rows.slice((page - 1) * pageSize, page * pageSize)

      allRows.forEach((row) => row.remove())
      emptyRow.remove()

      if (pageRows.length === 0) {
        tbody.appendChild(emptyRow)
      } else {
        pageRows.forEach((row) => tbody.appendChild(row))
      }

      if (paginationEl) {
        renderPagination(paginationEl, page, totalPages, (target) => {
          if (target >= 1 && target <= totalPages) {
            page = target
            render()
          }
        })
      }

      table.dispatchEvent(new CustomEvent('yw-datatable-drawn', {
        bubbles: true,
        detail: { matchedRows: rows }
      }))
    }

    // callers that change external filter state ask for a re-render this way
    table.addEventListener('yw-datatable-refresh', () => render())

    render()
  }

  function scan(root) {
    root
      .querySelectorAll(
        'table[data-yw-datatable]:not([data-yw-datatable-ready])'
      )
      .forEach(initDataTable)
  }

  document.addEventListener('DOMContentLoaded', () => scan(document))

  new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node.nodeType !== 1) return
        if (node.matches && node.matches('table[data-yw-datatable]')) {
          initDataTable(node)
        } else if (node.querySelectorAll) {
          scan(node)
        }
      })
    })
  }).observe(document.body, { childList: true, subtree: true })
}())
