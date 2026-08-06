// DynTable.js — Vue-native data table for the bazar dynamic index (ticket 16:
// replaces jQuery DataTables; sorting/search happen in computed properties over
// the reactive rows, no DOM-driven table library). DataTables-style column defs
// are still the contract with BazarTable: {data, title, class, orderable,
// visible, width, render(value, type, row), footer}; render() is called with
// 'display' for cell html, 'sort' and 'filter' for the comparable/searchable
// values, exactly as before. No pagination and no visible search input, matching
// the legacy config (paging:false, search driven externally by BazarTable).
// Disclosed simplification: the copy/csv/print export buttons are gone.
export default {
  props: {
    columns: {
      type: Array,
      required: true,
    },
    externalSearch: {
      type: String,
      default: '',
    },
    extraOptions: { type: Object },
    forceDisplayTotal: {
      type: Boolean,
      default: false,
    },
    forceRefresh: {
      type: Boolean,
      default: false,
    },
    rows: {
      type: Object,
      required: true,
    },
    uuid: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      sortColIdx: null,
      sortDir: 'asc',
      orderInitialized: false,
    }
  },
  computed: {
    element() {
      return this.$el.parentNode
    },
    visibleColumns() {
      return this.columns
        .map((col, idx) => ({ col, idx }))
        .filter(({ col }) => col.visible !== false)
    },
    showFooter() {
      return (
        this.forceDisplayTotal ||
        this.columns.some((col) => col?.class?.match(/sum-activated/))
      )
    },
    formattedRows() {
      return Object.keys(this.rows).map((id) => {
        const rowData = { id, ...this.rows[id] }
        const cells = this.visibleColumns.map(({ col }) => {
          const raw =
            typeof col.data === 'string' ? (rowData[col.data] ?? '') : ''
          return {
            html: this.applyRender(col, raw, 'display', rowData),
            sortVal: this.plainValue(
              this.applyRender(col, raw, 'sort', rowData),
            ),
            searchVal: this.plainValue(
              this.applyRender(col, raw, 'filter', rowData),
            ),
          }
        })
        return { id, rowData, cells }
      })
    },
    matchedRows() {
      const needle = (this.externalSearch || '').trim().toLowerCase()
      if (!needle) return this.formattedRows
      return this.formattedRows.filter((row) =>
        row.cells.some((cell) => cell.searchVal.toLowerCase().includes(needle)),
      )
    },
    sortedRows() {
      if (this.sortColIdx === null) return this.matchedRows
      const position = this.visibleColumns.findIndex(
        ({ idx }) => idx === this.sortColIdx,
      )
      if (position === -1) return this.matchedRows
      const direction = this.sortDir === 'desc' ? -1 : 1
      return this.matchedRows.slice().sort((a, b) => {
        const av = a.cells[position].sortVal
        const bv = b.cells[position].sortVal
        const an = parseFloat(String(av).replace(',', '.'))
        const bn = parseFloat(String(bv).replace(',', '.'))
        const looksNumeric = /^-?[\d.,]+$/.test(av) && /^-?[\d.,]+$/.test(bv)
        const result =
          looksNumeric && !Number.isNaN(an) && !Number.isNaN(bn)
            ? an - bn
            : String(av).localeCompare(String(bv), undefined, {
                sensitivity: 'base',
              })
        return result * direction
      })
    },
    totalColPosition() {
      if (!this.showFooter) return -1
      let position = -1
      this.visibleColumns.some(({ col }, idx) => {
        const hasFooter = 'footer' in col && col.footer && col.footer.length > 0
        if (
          !hasFooter &&
          !col?.class?.match(/not-export-this-col/) &&
          !col?.class?.match(/sum-activated/)
        ) {
          position = idx
          return true
        }
        return false
      })
      return position
    },
    footerCells() {
      return this.visibleColumns.map(({ col }, position) => {
        if (col?.class?.match(/sum-activated/)) {
          let sum = 0
          this.matchedRows.forEach((row) => {
            sum += this.sanitizeValue(row.rowData[col.data])
          })
          return { kind: 'sum', content: String(sum) }
        }
        if ('footer' in col && col.footer && col.footer.length > 0) {
          return { kind: 'html', content: col.footer }
        }
        if (position === this.totalColPosition) {
          return { kind: 'total', content: '' }
        }
        return { kind: 'empty', content: '' }
      })
    },
  },
  methods: {
    applyRender(col, value, type, rowData) {
      const rendered =
        typeof col.render === 'function'
          ? col.render(value, type, rowData)
          : value
      return rendered === null || rendered === undefined ? '' : rendered
    },
    plainValue(val) {
      if (Object.prototype.toString.call(val) === '[object Object]') {
        return String(val.display || '')
      }
      return String(val)
    },
    sanitizeValue(val) {
      let sanitizedValue = val
      if (Object.prototype.toString.call(val) === '[object Object]') {
        // because if orthogonal data is defined, value is an object
        sanitizedValue = val.display || ''
      }
      return Number.isNaN(Number(sanitizedValue)) ? 1 : Number(sanitizedValue)
    },
    manageError(error) {
      if (wiki.isDebugEnabled) {
        console.error(error)
      }
      return null
    },
    headerSortState(colIdx) {
      if (this.sortColIdx !== colIdx) return ''
      return this.sortDir
    },
    toggleSort({ col, idx }) {
      if (col.orderable === false) return
      if (this.sortColIdx === idx) {
        this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc'
      } else {
        this.sortColIdx = idx
        this.sortDir = 'asc'
      }
    },
    applyInitialOrder() {
      if (this.orderInitialized) return
      const order = this.extraOptions && this.extraOptions.order
      if (Array.isArray(order) && Array.isArray(order[0])) {
        const [[column, direction]] = order
        this.sortColIdx = column
        this.sortDir = direction === 'desc' ? 'desc' : 'asc'
        this.orderInitialized = true
      }
    },
  },
  mounted() {
    this.element.addEventListener('dblclick', (e) => {
      e.preventDefault()
      e.stopPropagation()
    })
    this.applyInitialOrder()
  },
  watch: {
    extraOptions() {
      this.applyInitialOrder()
    },
  },
  template: `
    <div>
        <table :id="uuid" class="yw-table yw-table--sortable in-dyntable">
            <thead>
                <tr>
                    <th v-for="entry in visibleColumns"
                        :key="entry.idx"
                        :class="entry.col.class"
                        :style="entry.col.width ? { width: entry.col.width } : {}"
                        :data-yw-sort="entry.col.orderable === false
                          ? null : headerSortState(entry.idx)"
                        @click="toggleSort(entry)"
                        v-html="entry.col.title"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in sortedRows" :key="row.id">
                    <td v-for="(cell, position) in row.cells"
                        :key="position"
                        :class="visibleColumns[position].col.class"
                        v-html="cell.html"></td>
                </tr>
            </tbody>
            <tfoot v-if="showFooter">
                <tr>
                    <th v-for="(cell, position) in footerCells" :key="position">
                        <template v-if="cell.kind === 'total'">
                            <slot name="sumtranslate">Total</slot>
                        </template>
                        <span v-else-if="cell.kind === 'html'" v-html="cell.content"></span>
                        <template v-else>{{ cell.content }}</template>
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>
  `,
}
