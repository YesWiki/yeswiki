import Waiter from '../Waiter.js'

const isVueJS3 = (typeof Vue.createApp == 'function')

export default {
  props: {
    columns: {
      type: Array,
      required: true
    },
    externalSearch: {
      type: String,
      default: ''
    },
    extraOptions: { type: Object },
    forceDisplayTotal: {
      type: Boolean,
      default: false
    },
    forceRefresh: {
      type: Boolean,
      default: false
    },
    rows: {
      type: Object,
      required: true
    },
    uuid: {
      type: String,
      required: true
    }
  },
  data() {
    return {
      dataTable: null,
      displayedRows: {},
      templatesForRendering: {}
    }
  },
  computed: {
    element() {
      return isVueJS3 ? this.$el.parentNode : this.$el
    },
    showFooter() {
      return this.forceDisplayTotal || this.columns.some((col) => col?.class?.match(/sum-activated/))
    }
  },
  methods: {
    addRows(dataTable, columns, rows) {
      const formattedDataList = []
      Object.keys(rows).forEach((id) => {
        if (id in this.displayedRows) {
          return
        }
        this.displayedRows[id] = rows[id]
        const formattedData = {}
        formattedData.id = id
        columns.forEach((col) => {
          if (!(typeof col.data === 'string')) {
            return
          }
          formattedData[col.data] = rows[id]?.[col.data] ?? ''
        })
        // extra cols
        Object.keys(rows[id]).forEach((k) => {
          if (!(k in formattedData)) {
            formattedData[k] = rows[id][k]
          }
        })
        formattedDataList.push(formattedData)
      })
      dataTable.rows.add(formattedDataList)
    },
    async getColumns() {
      return await Waiter.waitFor('columns', this).catch((error) => {
        this.manageError(error)
        return []
      })
    },
    getDatatableOptions() {
      const buttons = []
      DATATABLE_OPTIONS.buttons.forEach((option) => {
        buttons.push({
          ...option,
          ...{ footer: true },
          ...{
            exportOptions: {
              ...(
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
                      ) && !$(node).hasClass('not-printable')
                    }
                  }),
              ...{ format: { footer: (data, column) => this.dataTable.footer().to$().find(`> tr > th:nth-child(${column + 1})`).text() } }
            }
          }
        })
      })
      const options = { ...DATATABLE_OPTIONS }
      options.searching = true // allow search but ue dom option not display filter
      const dom = this.getTemplateFromSlot('dom', {})
      if (dom && dom.length > 0) {
        options.dom = dom
        // instead of default lfrtip , with f for filter, see help : https://datatables.net/reference/option/dom
        // and removing filter
      }
      options.footerCallback = () => {
        this.updateFooter()
      }
      options.buttons = buttons
      return options
    },
    async getDatatable() {
      if (this.dataTable === null) {
        // create dataTable
        const columns = await this.getColumns()
        this.dataTable = $(this.$refs.dataTable).DataTable({
          ...this.getDatatableOptions(),
          ...{
            columns,
            scrollX: true
          },
          ...{ ...this.extraOptions }
        })
        $(this.dataTable.table().node()).prop('id', this.getUuid())
        if (this.showFooter) {
          this.initFooter(columns)
        }
      }
      return this.dataTable
    },
    getTemplateFromSlot(name, params) {
      const key = `${name}-${JSON.stringify(params)}`
      if (!(key in this.templatesForRendering)) {
        if (name in this.$scopedSlots) {
          const slot = this.$scopedSlots[name]
          const constructor = Vue.extend({
            render(h) {
              return h('div', {}, slot(params))
            }
          })
          const instance = new constructor()
          instance.$mount()
          let outerHtml = ''
          for (let index = 0; index < instance.$el.childNodes.length; index++) {
            outerHtml += instance.$el.childNodes[index].outerHTML || instance.$el.childNodes[index].textContent
          }
          this.templatesForRendering[key] = outerHtml
        } else {
          this.templatesForRendering[key] = ''
        }
      }
      return this.templatesForRendering[key]
    },
    getUuid() {
      if (this.uuid === null) {
        return `${Date.now()}-${Math.round(Math.random() * 10000)}`
      }
      return this.uuid
    },
    initFooter(columns) {
      const footerNode = this.dataTable.footer().to$()
      if (footerNode[0] !== null) {
        const footer = $('<tr>')
        let displayTotal = columns.some((col) => col?.class?.match(/sum-activated/))
        columns.forEach((col) => {
          let newElem = $('<th>')
          if ('footer' in col && col.footer.length > 0) {
            const element = $(col.footer)
            const isTh = $(element).prop('tagName') === 'TH'
            newElem = isTh ? element : $('<th>').append(element)
          } else if (displayTotal && !col?.class?.match(/not-export-this-col/)) {
            displayTotal = false
            newElem = $('<th>').text(this.render('sumtranslate', {}, 'Total'))
          }
          footer.append(newElem)
        })
        footerNode.html(footer)
      }
    },
    manageError(error) {
      if (wiki.isDebugEnabled) {
        console.error(error)
      }
      return null
    },
    removeRows(dataTable, newIds) {
      const entryIdsToRemove = Object.keys(this.displayedRows).filter((id) => !newIds.includes(id))
      entryIdsToRemove.forEach((id) => {
        if (id in this.displayedRows) {
          this.$delete(this.displayedRows, id)
        }
      })
      dataTable.rows((idx, data, node) => data?.id === undefined || entryIdsToRemove.includes(data?.id)).remove()
    },
    render(name, replacement = {}, defaultContent = null, params = {}) {
      let output = this.getTemplateFromSlot(name, params)
      Object.entries(replacement).forEach(([anchor, replacement]) => {
        output = output.replace(anchor, replacement)
      })
      if (output.length === 0 && defaultContent && defaultContent.length > 0) {
        output = defaultContent
      }
      return output
    },
    sanitizeValue(val) {
      let sanitizedValue = val
      if (Object.prototype.toString.call(val) === '[object Object]') {
        // because if orthogonal data is defined, value is an object
        sanitizedValue = val.display || ''
      }
      return (isNaN(sanitizedValue)) ? 1 : Number(sanitizedValue)
    },
    async updateRows(newVal) {
      const newIds = Object.keys(newVal)
      const dataTable = await this.getDatatable()
      this.removeRows(dataTable, newIds)
      this.addRows(dataTable, this.columns, newVal)
      this.dataTable.draw()
    },
    updateFastSearch(newSearch) {
      if (this.dataTable !== null) {
        this.dataTable.search(newSearch).draw()
      }
    },
    updateFooter() {
      if (this.dataTable !== null) {
        const activatedRows = []
        this.dataTable.rows({ search: 'applied' }).every(function() {
          activatedRows.push(this.index())
        })
        this.dataTable.columns('.sum-activated').every((indexCol) => {
          const col = this.dataTable.column(indexCol)
          let sum = 0
          activatedRows.forEach((indexRow) => {
            const value = this.dataTable.row(indexRow).data()[col.dataSrc()]
            sum += this.sanitizeValue(Number(value))
          })
          this.dataTable.footer().to$().find(`> tr > th:nth-child(${indexCol + 1})`).html(sum)
        })
      }
    }
  },
  mounted() {
    $(this.element).on('dblclick', (e) => false)
  },
  watch: {
    rows: {
      deep: true,
      handler(newVal) {
        this.updateRows(newVal).catch(this.manageError)
      }
    },
    columns(newVal) {
      if (Array.isArray(newVal) && newVal.length > 0) {
        Waiter.resolve('columns')
      }
    },
    externalSearch(newSearch) {
      this.updateFastSearch(newSearch)
    },
    forceRefresh() {
      // whatever is the value toogle
      if (this.dataTable !== null) {
        this.removeRows(this.dataTable, [])
        this.addRows(this.dataTable, this.columns, this.rows)
        this.dataTable.draw()
      }
    }
  },
  template: `
    <div>
        <table ref="dataTable" class="table prevent-auto-init table-condensed display">
            <tfoot v-if="showFooter">
                <tr></tr>
            </tfoot>
        </table>
    </div>
  `
}
