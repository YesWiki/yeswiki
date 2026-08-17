import Panel from './shared-components/Panel.js'
import EntryField from './components/EntryField.js'
import PopupEntryField from './components/PopupEntryField.js'
import SpinnerLoader from './components/SpinnerLoader.js'
import ModalEntry from './components/ModalEntry.js'
import FilterNode from './components/FilterNode.js'
import BazarMap from './components/BazarMap.js'
import { initEntryMaps } from './fields/map-field-map-entry.js'
import { recursivelyCalculateRelations, deepGet } from './utils.js'
import { updateHash, parseSearchParams, serializeSearchParams } from './url.js'
import ImageMixin from './entries-index-dynamic/image-mixin.js'
import BazarSearch from './entries-index-dynamic/search-mixin.js'

const { createApp } = Vue

const load = (domElement) => {
  const elementDataset = { ...domElement.dataset }
  const initialParams = elementDataset.params
    ? JSON.parse(elementDataset.params)
    : {}

  const app = createApp({
    mixins: [BazarSearch, ImageMixin],
    components: {
      Panel,
      ModalEntry,
      SpinnerLoader,
      EntryField,
      PopupEntryField,
      FilterNode,
      BazarMap,
    },
    data() {
      return {
        mounted: false,
        ready: false,
        params: initialParams,
        elementDataset,

        filters: [],
        sortOptions: [],
        entries: [],
        formFields: {},
        searchedEntries: [],
        filteredEntries: [],
        paginatedEntries: [],
        entriesToDisplay: [],

        currentPage: 0,
        pagination: 10,

        search: '',
        currentSort: { field: '', order: null, label: '' },

        searchFormId: '',
        searchTimer: null,
      }
    },
    computed: {
      computedFilters() {
        const result = {}
        this.filters.forEach((filter) => {
          const checkedValues = filter.flattenNodes
            .filter((node) => node.checked)
            .map((node) => node.value)
          if (checkedValues.length > 0) result[filter.propName] = checkedValues
        })

        return result
      },
      filteredEntriesCount() {
        return this.filteredEntries.length
      },
      pages() {
        if (this.pagination <= 0) return []
        const pagesCount = Math.ceil(
          this.filteredEntries.length / parseInt(this.pagination, 10),
        )
        const start = 0
        const end = pagesCount - 1
        let pages = [
          this.currentPage - 2,
          this.currentPage - 1,
          this.currentPage,
          this.currentPage + 1,
          this.currentPage + 2,
        ]
        pages = pages.filter((page) => page >= start && page <= end)
        if (!pages.includes(start)) {
          if (!pages.includes(start + 1)) pages.unshift('divider')
          pages.unshift(0)
        }
        if (!pages.includes(end)) {
          if (!pages.includes(end - 1)) pages.push('divider')
          pages.push(end)
        }
        return pages
      },
    },
    watch: {
      filteredEntriesCount() {
        this.currentPage = 0
      },
      search() {
        if (this.ready) {
          clearTimeout(this.searchTimer)
          this.searchTimer = setTimeout(() => this.calculateBaseEntries(), 350)
          this.updateHash()
        }
      },
      searchFormId() {
        this.calculateBaseEntries()
      },
      computedFilters() {
        this.filterEntries()
        this.updateHash()
      },
      currentPage() {
        this.paginateEntries()
      },
      searchedEntries() {
        this.calculateFiltersCount()
      },
      currentSort() {
        this.sortEntries()
        this.updateHash()
      },
    },
    methods: {
      calculateBaseEntries() {
        let result = this.entries
        if (this.searchFormId) {
          result = result.filter(
            (entry) => String(entry.form_id) === String(this.searchFormId),
          )
        }

        let vSearch = this.params.keywords ?? ''
        if (this.search) vSearch += (vSearch !== '' ? '|' : '') + this.search

        vSearch = vSearch
          .split('|')
          .filter((pKeyword) => pKeyword.length >= wiki.minSearchKeywordLength)
          .join('|')

        if (vSearch && vSearch.length >= wiki.minSearchKeywordLength) {
          result = this.searchEntries(result, vSearch)
          if (result == null) {
            result = this.entries
          }
        }
        this.searchedEntries = result
        this.filterEntries()
      },
      filterEntries() {
        let result = this.searchedEntries
        Object.entries(this.computedFilters).forEach(([propName, filter]) => {
          result = result.filter((entry) => {
            if (!entry[propName] || typeof entry[propName] != 'string')
              return false
            return entry[propName]
              .split(',')
              .map((str) =>
                str
                  .normalize('NFD')
                  .replace(/[\u0300-\u036f]/g, '')
                  .toLowerCase()
                  .replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#039;'),
              )
              .some((value) =>
                filter
                  .map((str) =>
                    str
                      .normalize('NFD')
                      .replace(/[\u0300-\u036f]/g, '')
                      .toLowerCase(),
                  )
                  .includes(value),
              )
          })
        })
        this.filteredEntries = result
        this.paginateEntries()
      },
      sortEntries() {
        if (!this.currentSort.field) return

        const { field, order } = this.currentSort
        const collator = new Intl.Collator()

        this.filteredEntries.sort((a, b) => {
          const valueA = deepGet(a, field)
          const valueB = deepGet(b, field)

          if (typeof valueA === 'number' && typeof valueB === 'number') {
            return order === 'asc' ? valueA - valueB : valueB - valueA
          }

          return order === 'asc'
            ? collator.compare(
                String(valueA)
                  .normalize('NFD')
                  .replace(/[\u0300-\u036f]/g, '')
                  .toLowerCase(),
                String(valueB)
                  .normalize('NFD')
                  .replace(/[\u0300-\u036f]/g, '')
                  .toLowerCase(),
              )
            : collator.compare(
                String(valueB)
                  .normalize('NFD')
                  .replace(/[\u0300-\u036f]/g, '')
                  .toLowerCase(),
                String(valueA)
                  .normalize('NFD')
                  .replace(/[\u0300-\u036f]/g, '')
                  .toLowerCase(),
              )
        })

        this.paginateEntries()
      },
      paginateEntries() {
        let result = this.filteredEntries
        if (this.pagination > 0) {
          const start = this.pagination * this.currentPage
          result = result.slice(start, start + this.pagination)
        }
        this.paginatedEntries = result
        this.formatEntries()
      },
      formatEntries() {
        this.paginatedEntries.forEach((entry) => {
          entry.color = this.colorIconValueFor(
            entry,
            this.params.colorfield,
            this.params.color,
          )
          entry.icon = this.colorIconValueFor(
            entry,
            this.params.iconfield,
            this.params.icon,
          )
        })
        this.entriesToDisplay = this.paginatedEntries
      },
      calculateFiltersCount() {
        this.filters.forEach((filter) => {
          filter.flattenNodes.forEach((node) => {
            node.count = this.searchedEntries.filter((entry) => {
              let entryValues = entry[filter.propName]
              if (!entryValues || typeof entryValues != 'string') return
              entryValues = entryValues.split(',')
              return entryValues.some(
                (value) =>
                  value
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase()
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;') ===
                  node.value
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase(),
              )
            }).length
          })
        })
      },
      resetFilters() {
        this.filters.forEach((filter) => {
          filter.flattenNodes.forEach((node) => {
            node.checked = false
          })
        })
        this.search = ''
        if (this.sortOptions.length > 0) this.currentSort = this.sortOptions[0]
        this.updateHash()
      },
      initFromHash(pHash) {
        const vThis = this

        const vParams = parseSearchParams(pHash)
        let vChamp
        let vOrdre

        let vSearch = ''

        if (vParams.q !== undefined && vParams.q.trim() !== '') {
          vSearch = vParams.q
        }

        if (vParams.keywords !== undefined && vParams.keywords.trim() !== '') {
          vSearch = (vSearch !== '' ? `${vSearch}|` : '') + vParams.keywords
        }

        if (vSearch !== '') this.search = vSearch

        if (vParams.field !== undefined && vParams.field.trim() !== '') {
          vChamp = vParams.field
        }

        if (vParams.order !== undefined && vParams.order.trim() !== '') {
          vChamp = vParams.order
        }

        if (vParams.query !== undefined) {
          const vQueryEntries = Object.entries(vParams.query)

          if (vQueryEntries.length > 0) {
            vQueryEntries.forEach(([, pCondition]) => {
              const cFilter = vThis.filters.find(
                (pF) => pF.propName === pCondition.name,
              )

              if (cFilter) {
                cFilter.flattenNodes.forEach((pNode) => {
                  const cFilterValues = pCondition.values.map((pString) =>
                    pString
                      .normalize('NFD')
                      .replace(/[\u0300-\u036f]/g, '')
                      .toLowerCase()
                      .replace(/&/g, '&amp;')
                      .replace(/</g, '&lt;')
                      .replace(/>/g, '&gt;')
                      .replace(/"/g, '&quot;')
                      .replace(/'/g, '&#039;'),
                  )

                  if (
                    cFilterValues.includes(
                      pNode.value
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .toLowerCase(),
                    )
                  )
                    pNode.checked = true
                })
              }
            })
          }
        }

        const cSort = this.sortOptions.find(
          (s) =>
            s.field ===
              ((vChamp ?? typeof vThis.currentSort != 'undefined')
                ? vThis.currentSort.field
                : '') &&
            s.order ===
              ((vOrdre ?? typeof vThis.currentSort != 'undefined')
                ? vThis.currentSort.order
                : ''),
        )
        if (cSort) {
          this.currentSort = cSort
        }
      },
      updateHash() {
        if (!this.ready) return

        return updateHash(
          this.savedHash,
          this.search,
          this.currentSort.field,
          this.currentSort.order,
          this.computedFilters,
        )
      },
      getEntryRender(entry) {
        if (entry.html_render) return
        if (this.isExternalUrl(entry)) {
          this.getExternalEntry(entry)
        } else {
          let fieldsToExclude = []
          if (this.params.template === 'list' && this.params.displayfields) {
            fieldsToExclude = Object.values(this.params.displayfields)
          }
          const url = wiki.url(`?api/entries/html/${entry.tag}`, {
            ...{ isInIframe: this.params.isInIframe },
            ...{ fields: 'html_output' },
            ...(fieldsToExclude.length > 0
              ? { excludeFields: fieldsToExclude }
              : {}),
            ...(this.params.showmapinlistview
              ? { showmapinlistview: this.params.showmapinlistview }
              : {}),
          })
          this.setEntryFromUrl(entry, url).then((html) => {
            this.loadBazarListDynamicIfNeeded(html)
            initEntryMaps(this.$refs.entriesContainer)
          })
        }
      },
      async setEntryFromUrl(entry, url) {
        return await this.getJSON(url)
          .then((data) => {
            const html = data?.[entry.tag]?.html_output ?? 'error'
            entry.html_render = html
            return html
          })
          .catch(() => 'error')
      },
      async getJSON(url, options = {}) {
        return fetch(url, options)
          .then((response) => {
            if (!response.ok) {
              throw `response not ok ; code : ${response.status} (${response.statusText})`
            }
            return response.json()
          })
          .catch((error) => {
            if (wiki?.isDebugEnabled) {
              console.error(error)
            }
            return {}
          })
      },
      loadBazarListDynamicIfNeeded(html) {
        if (html.match(/<div class="entry-list-dynamic-container/)) {
          const unmounted = document.querySelectorAll(
            '.entry-list-dynamic-container:not(.mounted)',
          )
          unmounted.forEach((element) => {
            if (!('__vue__' in element) && !('__vue_app__' in element))
              load(element)
          })
        }
      },
      fieldInfo(field) {
        return this.formFields[field] || {}
      },
      openEntry(entry) {
        if (this.params.entrydisplay === 'newtab') window.open(entry.url)
        else this.$root.openEntryModal(entry)
      },
      openEntryModal(entry) {
        this.$refs.modal.displayEntry(entry)
      },
      isExternalUrl(entry) {
        if (!entry.url) {
          return false
        }
        return entry.url !== wiki.url(entry.tag)
      },
      isInIframe() {
        return window !== window.parent
      },
      getExternalEntry(entry) {
        const url = `${entry.url}/iframe`
        entry.html_render = `<iframe src="${url}" width="500px" height="600px" style="border:none;"></iframe>`
      },
      colorIconValueFor(entry, field, mapping) {
        if (!entry[field] || typeof entry[field] != 'string') return null
        let values = entry[field].split(',')
        if (this.computedFilters[field]) {
          values = values.filter((val) =>
            this.computedFilters[field].includes(val),
          )
        }
        return mapping[values[0]]
      },
    },
    mounted() {
      this.$el.addEventListener('dblclick', (e) => {
        e.preventDefault()
        e.stopPropagation()
      })
      this.savedHash = decodeURIComponent(document.location.hash.substring(1))

      this.pagination = parseInt(this.params.pagination, 10)
      this.mounted = true
      fetch(
        `${wiki.url('?api/entries/bazarlist')}&${serializeSearchParams(this.params)}`,
      )
        .then((response) => response.json())
        .then((data) => {
          const filters = data.filters || []
          filters.forEach((filter) => {
            filter.nodes.forEach((rootNode) =>
              recursivelyCalculateRelations(rootNode),
            )
            filter.flattenNodes = filter.nodes
              .map((rootNode) => [rootNode, ...rootNode.descendants])
              .flat()
            filter.flattenNodes.forEach((node) => {
              node.count = 0
              node.checked = false
            })
          })

          this.params.sortfields.forEach((field, index) => {
            const label = this.params.sortfieldstitles[index]
            this.sortOptions.push({ field: field.trim(), label, order: 'asc' })
            this.sortOptions.push({ field: field.trim(), label, order: 'desc' })
          })

          if (this.sortOptions.length > 0) {
            if (this.params.field) {
              const sort = this.sortOptions.find(
                (o) =>
                  o.field === this.params.field.trim() &&
                  o.order ===
                    ((
                      typeof this.params.order == 'boolean'
                        ? this.params.order
                        : this.params.order === '1' ||
                          this.params.order === 'true' ||
                          this.params.order === 'asc'
                    )
                      ? 'asc'
                      : 'desc'),
              )

              if (sort) {
                this.currentSort = sort
              } else {
                this.currentSort = this.sortOptions[0]
              }
            }
          }

          this.filters = filters

          this.initFromHash(this.savedHash)

          if (data.entries.length > 50 && !this.pagination) this.pagination = 20
          if (data.entries.length > 1000) this.params.cluster = true

          setTimeout(() => {
            Object.values(data.forms).forEach((formFields) => {
              Object.values(formFields).forEach((field) => {
                this.formFields[field.id] = field
                Object.entries(this.params.displayfields).forEach(
                  ([fieldId, mappedField]) => {
                    if (mappedField === field.id)
                      this.formFields[fieldId] = this.formFields[mappedField]
                  },
                )
              })
            })

            this.entries = data.entries.map((entryAsArray) => {
              const entry = { color: null, icon: null }
              Object.entries(data.fieldMapping).forEach(([key, mapping]) => {
                entry[mapping] = entryAsArray[key]
              })
              Object.entries(this.params.displayfields).forEach(
                ([field, mappedField]) => {
                  if (mappedField) entry[field] = entry[mappedField]
                },
              )

              this.filters.forEach((filter) => {
                const { propName } = filter
                if (entry[propName] && typeof entry[propName] == 'string') {
                  const entryValues = entry[propName].split(',')
                  entryValues.forEach((value) => {
                    const correspondingNode = filter.flattenNodes.find(
                      (node) => String(node.value) === value,
                    )
                    if (correspondingNode) {
                      correspondingNode.parents.forEach((parent) => {
                        if (!entryValues.includes(parent.value))
                          entryValues.push(parent.value)
                      })
                    }
                  })
                  entry[propName] = entryValues.join(',')
                }
              })

              return entry
            })

            this.calculateBaseEntries()
            this.ready = true
            this.updateHash()
            const event = new Event('entry-list-dynamic-ready')
            document.dispatchEvent(event)
          }, 0)
        })
    },
  })

  Object.entries(window._bazarDynamicComponents || {}).forEach(
    ([name, component]) => {
      app.component(name, component)
    },
  )

  // Expose YesWiki globals to all Vue templates in this app
  app.config.globalProperties.wiki = window.wiki
  app.config.globalProperties._t = window._t

  domElement.classList.add('mounted')

  app.mount(domElement)
}

ywInitEach('.entry-list-dynamic-container', load)
