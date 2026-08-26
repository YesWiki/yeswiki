;(function () {
  const DEFAULT_PAGE_SIZE = 10
  const DEFAULT_PAGE_SIZE_OPTIONS = [10, 25, 50, 100]

  function translate(key, fallback) {
    const translated = typeof _t === 'function' ? _t(key) : key
    return !translated || translated === key ? fallback : translated
  }

  function pageSizeOptions(table, pageSize) {
    const declared = (table.getAttribute('data-yw-page-size-options') || '')
      .split(',')
      .map((value) => parseInt(value, 10))
      .filter((value) => !Number.isNaN(value) && value > 0)
    const options = declared.length
      ? declared
      : DEFAULT_PAGE_SIZE_OPTIONS.slice()
    if (!options.includes(pageSize)) options.push(pageSize)
    return options.sort((a, b) => a - b)
  }

  // A cell that holds more than its value -- an avatar's initials beside a name -- says what
  // to sort and filter on, or textContent stands in as it always did.
  function cellText(cell) {
    const declared = cell.getAttribute('data-yw-sort')
    return (declared !== null ? declared : cell.textContent || '').trim()
  }

  function rowMatches(row, needle) {
    if (!needle) return true
    return Array.from(row.cells).some((cell) =>
      cellText(cell).toLowerCase().includes(needle),
    )
  }

  function compareRows(a, b, colIndex, direction) {
    const av = cellText(a.cells[colIndex])
    const bv = cellText(b.cells[colIndex])
    const an = parseFloat(av.replace(',', '.'))
    const bn = parseFloat(bv.replace(',', '.'))
    const looksNumeric = /^-?[\d.,]+$/.test(av) && /^-?[\d.,]+$/.test(bv)
    const result =
      looksNumeric && !Number.isNaN(an) && !Number.isNaN(bn)
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

    let pageSize =
      parseInt(table.getAttribute('data-yw-page-size'), 10) || DEFAULT_PAGE_SIZE
    const noSearch = table.hasAttribute('data-yw-no-search')
    const noPaginate = table.hasAttribute('data-yw-no-paginate')

    let searchTerm = ''
    let sortCol = null
    let sortDir = 'asc'
    let page = 1

    const initialSort = (
      table.getAttribute('data-yw-datatable-sort') || ''
    ).split(',')
    if (initialSort.length === 2) {
      const initialCol = parseInt(initialSort[0], 10)
      if (!Number.isNaN(initialCol)) {
        sortCol = initialCol
        sortDir = initialSort[1].trim() === 'desc' ? 'desc' : 'asc'
      }
    }

    const sizes = pageSizeOptions(table, pageSize)
    const showPageSize = !noPaginate && allRows.length > sizes[0]

    if (!noSearch || showPageSize) {
      const toolbar = document.createElement('div')
      toolbar.className = 'yw-datatable__toolbar'

      if (showPageSize) {
        const picker = document.createElement('label')
        picker.className = 'yw-datatable__page-size'
        picker.appendChild(
          document.createTextNode(
            translate('DATATABLE_PAGE_SIZE_LABEL', 'Show'),
          ),
        )
        const select = document.createElement('select')
        select.className = 'yw-input'
        sizes.forEach((size) => {
          const option = document.createElement('option')
          option.value = String(size)
          option.textContent = String(size)
          option.selected = size === pageSize
          select.appendChild(option)
        })
        select.addEventListener('change', () => {
          pageSize = parseInt(select.value, 10) || DEFAULT_PAGE_SIZE
          page = 1
          render()
        })
        picker.appendChild(select)
        toolbar.appendChild(picker)
      }

      if (!noSearch) {
        const search = document.createElement('input')
        search.type = 'search'
        search.className = 'yw-input yw-datatable__search'
        search.placeholder = translate(
          'DATATABLE_SEARCH_PLACEHOLDER',
          'Search…',
        )
        toolbar.appendChild(search)
        search.addEventListener('input', () => {
          searchTerm = search.value.trim().toLowerCase()
          page = 1
          render()
        })
      }

      table.parentNode.insertBefore(toolbar, table)
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
      headerCells.length || (allRows[0] ? allRows[0].cells.length : 1),
    )
    emptyCell.textContent = translate(
      'DATATABLE_NO_RESULTS',
      'No matching results',
    )
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
              otherIndex === sortCol ? sortDir : '',
            )
          }
        })
        page = 1
        render()
      })
    })

    function render() {
      const filters = window.ywDatatableRowFilters || {}
      const customFilter = table.id ? filters[table.id] : null
      let rows = allRows.filter(
        (row) =>
          rowMatches(row, searchTerm) && (!customFilter || customFilter(row)),
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

      table.dispatchEvent(
        new CustomEvent('yw-datatable-drawn', {
          bubbles: true,
          detail: { matchedRows: rows },
        }),
      )
    }

    table.addEventListener('yw-datatable-refresh', () => render())

    render()
  }

  function scan(root) {
    root
      .querySelectorAll(
        'table[data-yw-datatable]:not([data-yw-datatable-ready])',
      )
      .forEach(initDataTable)
  }

  ywInit((root) => {
    if (
      root.matches &&
      root.matches('table[data-yw-datatable]:not([data-yw-datatable-ready])')
    ) {
      initDataTable(root)
    }
    scan(root.querySelectorAll ? root : document)
  })
})()
