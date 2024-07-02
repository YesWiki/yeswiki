import Panel from '../../../../javascripts/shared-components/Panel.js'
import EntryField from './components/EntryField.js'
import PopupEntryField from './components/PopupEntryField.js'
import SpinnerLoader from './components/SpinnerLoader.js'
import ModalEntry from './components/ModalEntry.js'
import BazarSearch from './components/BazarSearch.js'
import FilterNode from './components/FilterNode.js'
import { initEntryMaps } from './fields/map-field-map-entry.js'
import { recursivelyCalculateRelations } from './utils.js'

Vue.component('FilterNode', FilterNode)

const load = (domElement) => {
  new Vue({
    el: domElement,
    components: { Panel, ModalEntry, SpinnerLoader, EntryField, PopupEntryField },
    mixins: [BazarSearch],
    data: {
      mounted: false, // when vue get initialized
      ready: false, // when ajax data have been retrieved
      params: {},

      filters: [],
      entries: [],
      formFields: {},
      searchedEntries: [],
      filteredEntries: [],
      paginatedEntries: [],
      entriesToDisplay: [],

      currentPage: 0,
      pagination: 10,
      tokenForImages: null,
      imagesToProcess: [],
      processingImage: false,
      search: '',
      // wether to search for a particular form ID (only used when no
      // form id is defined for the bazar list action)
      searchFormId: null,
      searchTimer: null // use ot debounce user input
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
        const pagesCount = Math.ceil(this.filteredEntries.length / parseInt(this.pagination, 10))
        const start = 0; const
          end = pagesCount - 1
        let pages = [
          this.currentPage - 2, this.currentPage - 1,
          this.currentPage,
          this.currentPage + 1, this.currentPage + 2
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
      }
    },
    watch: {
      filteredEntriesCount() { this.currentPage = 0 },
      search() {
        clearTimeout(this.searchTimer)
        this.searchTimer = setTimeout(() => this.calculateBaseEntries(), 350)
        this.saveFiltersIntoHash()
      },
      searchFormId() { this.calculateBaseEntries() },
      computedFilters() {
        this.filterEntries()
        this.saveFiltersIntoHash()
      },
      currentPage() { this.paginateEntries() },
      searchedEntries() { this.calculateFiltersCount() }
    },
    methods: {
      calculateBaseEntries() {
        let result = this.entries
        if (this.searchFormId) {
          // filter based on formId, when no form id is specified
          result = result.filter((entry) => entry.id_typeannonce == this.searchFormId)
        }
        if (this.search && this.search.length > 2) {
          result = this.searchEntries(result, this.search)
          if (result == undefined) {
            result = this.entries
          }
        }
        this.searchedEntries = result
        this.filterEntries()
      },
      filterEntries() {
        // Handles filters
        let result = this.searchedEntries
        Object.entries(this.computedFilters).forEach(([propName, filter]) => {
          result = result.filter((entry) => {
            if (!entry[propName] || typeof entry[propName] != 'string') return false
            return entry[propName].split(',').some((value) => filter.includes(value))
          })
        })
        this.filteredEntries = result
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
          entry.color = this.colorIconValueFor(entry, this.params.colorfield, this.params.color)
          entry.icon = this.colorIconValueFor(entry, this.params.iconfield, this.params.icon)
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
              return entryValues.some((value) => value == node.value)
            }).length
          })
        })
      },
      resetFilters() {
        this.filters.forEach((filter) => {
          filter.flattenNodes.forEach((node) => { node.checked = false })
        })
        this.search = ''
      },
      saveFiltersIntoHash() {
        if (!this.ready) return
        const hashes = []
        for (const filterId in this.computedFilters) {
          hashes.push(`${filterId}=${this.computedFilters[filterId].join(',')}`)
        }
        if (this.search) hashes.push(`q=${this.search}`)
        document.location.hash = hashes.length > 0 ? hashes.join('&') : null
      },
      initFiltersFromHash(filters, hash) {
        hash = hash.substring(1) // remove #
        hash.split('&').forEach((combinaison) => {
          const filterId = combinaison.split('=')[0]
          const filterValues = combinaison.split('=')[1]
          const filter = this.filters.find((f) => f.propName == fieldId)
          if (filterId == 'q') {
            this.search = filterValues
          } else if (filterId && filterValues && filter) {
            filter.flattenNodes.forEach((node) => {
              if (filterValues.includes(node.value)) node.checked = true
            })
          }
        })
        // init q from GET q also
        if (this.search.length == 0) {
          let params = document.location.search
          params = params.substring(1) // remove ?
          params.split('&').forEach((combinaison) => {
            const filterId = combinaison.split('=')[0]
            const filterValues = combinaison.split('=')[1]
            if (filterId == 'q') {
              this.search = decodeURIComponent(filterValues)
            }
          })
        }
        return filters
      },
      getEntryRender(entry) {
        if (entry.html_render) return
        if (this.isExternalUrl(entry)) {
          this.getExternalEntry(entry)
        } else {
          let fieldsToExclude = []
          if (this.params.template == 'list' && this.params.displayfields) {
            // In list template (collapsible panels with header and body), the rendered entry
            // is displayed in the body section and we don't want to show the fields
            // that are already displayed in the panel header
            fieldsToExclude = Object.values(this.params.displayfields)
          }
          const url = wiki.url(`?api/entries/html/${entry.id_fiche}`, {
            ...{ fields: 'html_output' },
            ...(fieldsToExclude.length > 0 ? { excludeFields: fieldsToExclude } : {}),
            ...(this.params.showmapinlistview ? { showmapinlistview: this.params.showmapinlistview } : {})
          })
          this.setEntryFromUrl(entry, url)
            .then((html) => {
              this.loadBazarListDynamicIfNeeded(html)
              initEntryMaps(this.$refs.entriesContainer)
            })
        }
      },
      async setEntryFromUrl(entry, url) {
        return await this.getJSON(url)
          .then((data) => {
            const html = data?.[entry.id_fiche]?.html_output ?? 'error'
            Vue.set(entry, 'html_render', html)
            return html
          }).catch(() => 'error')// in case of error do nothing
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
        if (html.match(/<div class="bazar-list-dynamic-container/)) {
          const unmounted = document.querySelectorAll('.bazar-list-dynamic-container:not(.mounted)')
          unmounted.forEach((element) => {
            if (!('__vue__' in element)) load(element)
          })
        }
      },
      fieldInfo(field) {
        return this.formFields[field] || {}
      },
      openEntry(entry) {
        if (this.params.entrydisplay == 'newtab') window.open(entry.url)
        else this.$root.openEntryModal(entry)
      },
      openEntryModal(entry) {
        this.$refs.modal.displayEntry(entry)
      },
      isExternalUrl(entry) {
        if (!entry.url) {
          return false
        }
        return entry.url !== wiki.url(entry.id_fiche)
      },
      isInIframe() {
        return (window != window.parent)
      },
      getExternalEntry(entry) {
        const url = `${entry.url}/iframe`
        Vue.set(
          entry,
          'html_render',
          `<iframe src="${url}" width="500px" height="600px" style="border:none;"></iframe>`
        )
      },
      colorIconValueFor(entry, field, mapping) {
        if (!entry[field] || typeof entry[field] != 'string') return null
        let values = entry[field].split(',')
        // If some filters are checked, and the entry have multiple values, we display
        // the value associated with the checked filter
        if (this.computedFilters[field]) {
          values = values.filter((val) => this.computedFilters[field].includes(val))
        }
        return mapping[values[0]]
      },
      urlImageResizedOnError(entry, fieldName, width, height, mode, token) {
        const node = event.target
        $(node).removeAttr('onerror')
        if (entry[fieldName]) {
          const fileName = entry[fieldName]
          if (!this.isExternalUrl(entry)) {
          // currently not supporting api for external images (anti-csrf token not generated)
            if (this.tokenForImages === null) {
              this.tokenForImages = token
            }
            this.imagesToProcess.push({
              fileName,
              width,
              height,
              mode,
              node
            })
            this.processNextImage()
          } else {
            const baseUrl = entry.url.slice(0, -entry.id_fiche.length).replace(/\?$/, '').replace(/\/$/, '')
            const previousUrl = $(node).prop('src')
            const newUrl = `${baseUrl}/files/${fileName}`
            if (newUrl != previousUrl) {
              $(`img[src="${previousUrl}"]`).each(function() {
                $(this).prop('src', newUrl)
              })
            }
          }
        }
      },
      urlImage(entry, fieldName, width, height, mode) {
        if (!entry[fieldName]) {
          return null
        }
        let baseUrl = (this.isExternalUrl(entry))
          ? entry.url.slice(0, -entry.id_fiche.length)
          : wiki.baseUrl
        baseUrl = baseUrl.replace(/\?$/, '').replace(/\/$/, '')
        const fileName = entry[fieldName]
        const field = this.fieldInfo(fieldName)
        let regExp = new RegExp(`^(${entry.id_fiche}_${field.propertyname}_.*)_(\\d{14})_(\\d{14})\\.([^.]+)$`)

        if (regExp.test(fileName)) {
          return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode == 'fit' ? 'vignette' : 'cropped'}_${width}_${height}_$2_$3.$4`)}`
        }
        regExp = new RegExp(`^(${entry.id_fiche}_${field.propertyname}_.*)\\.([^.]+)$`)
        if (regExp.test(fileName)) {
          return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode == 'fit' ? 'vignette' : 'cropped'}_${width}_${height}.$2`)}`
        }
        // maybe from other entry
        regExp = new RegExp(`^([A-Za-z0-9-_]+_${field.propertyname}_.*)_(\\d{14})_(\\d{14})\\.([^.]+)$`)
        if (regExp.test(fileName)) {
          return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode == 'fit' ? 'vignette' : 'cropped'}_${width}_${height}_$2_$3.$4`)}`
        }
        // last possible format
        regExp = new RegExp('^(.*)\\.([^.]+)$')
        if (regExp.test(fileName)) {
          return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode == 'fit' ? 'vignette' : 'cropped'}_${width}_${height}.$2`)}`
        }
        return `${baseUrl}/files/${fileName}`
      },
      processNextImage() {
        if (!this.processingImage && this.imagesToProcess.length > 0) {
          this.processingImage = true
          const newImageParams = this.imagesToProcess[0]
          this.imagesToProcess = this.imagesToProcess.slice(1)
          const bazarListDynamicRoot = this
          $.ajax({
            url: wiki.url(`?api/images/${newImageParams.fileName}/cache/${newImageParams.width}/${newImageParams.height}/${newImageParams.mode}`),
            method: 'post',
            data: { csrftoken: this.tokenForImages },
            cache: false,
            success(data) {
              const previousUrl = $(newImageParams.node).prop('src')
              const srcFileName = wiki.baseUrl.replace(/(\?)?$/, '') + data.cachefilename
              $(`img[src="${previousUrl}"]`).each(function() {
                $(this).prop('src', srcFileName)
                const next = $(this).next('div.area.visual-area[style]')
                if (next.length > 0) {
                  const backgoundImage = $(next).css('background-image')
                  if (backgoundImage != undefined && typeof backgoundImage == 'string' && backgoundImage.length > 0) {
                    $(next).css('background-image', '') // reset to force update
                    $(next).css('background-image', `url("${srcFileName}")`)
                  }
                }
              })
            },
            complete(e) {
              if (e.responseJSON != undefined && e.responseJSON.newToken != undefined) {
                bazarListDynamicRoot.tokenForImages = e.responseJSON.newToken
              }
              bazarListDynamicRoot.processingImage = false
              bazarListDynamicRoot.processNextImage()
            }
          })
        }
      }
    },
    mounted() {
      $(this.$el).on(
        'dblclick',
        (e) => false
      )
      const savedHash = document.location.hash // don't know how, but the hash get cleared after
      this.params = JSON.parse(this.$el.dataset.params)
      this.pagination = parseInt(this.params.pagination, 10)
      this.mounted = true
      // Retrieve data asynchronoulsy
      $.getJSON(wiki.url('?api/entries/bazarlist'), this.params, (data) => {
        // process the filters
        const filters = data.filters || []
        // Calculate the parents
        filters.forEach((filter) => {
          filter.nodes.forEach((rootNode) => recursivelyCalculateRelations(rootNode))
          filter.flattenNodes = filter.nodes
            .map((rootNode) => [rootNode, ...rootNode.descendants])
            .flat()
          // init some attributes for reactivity
          filter.flattenNodes.forEach((node) => {
            node.count = 0
            node.checked = false
          })
        })
        // First display filters cause entries can be a bit long to load
        this.filters = this.initFiltersFromHash(filters, savedHash)

        // Auto paginate if large numbers
        if (data.entries.length > 50 && !this.pagination) this.pagination = 20
        // Activate cluster for map mode
        if (data.entries.length > 1000) this.params.cluster = true

        setTimeout(() => {
          // Transform forms info into a list of field mapping
          // { bf_titre: { type: 'text', ...}, bf_date: { type: 'listedatedeb', ... } }
          Object.values(data.forms).forEach((formFields) => {
            Object.values(formFields).forEach((field) => {
              this.formFields[field.id] = field
              Object.entries(this.params.displayfields).forEach(([fieldId, mappedField]) => {
                if (mappedField == field.id) this.formFields[fieldId] = this.formFields[mappedField]
              })
            })
          })

          this.entries = data.entries.map((entryAsArray) => {
            const entry = { color: null, icon: null }
            // Transform entryAsArray data into object using the fieldMapping
            Object.entries(data.fieldMapping).forEach(([key, mapping]) => {
              entry[mapping] = entryAsArray[key]
            })
            Object.entries(this.params.displayfields).forEach(([field, mappedField]) => {
              if (mappedField) entry[field] = entry[mappedField]
            })

            // In case of Tree, if an entry have only one value down the tree then add all the parent :
            // filters for checkboxes: [{ value: "website", children: [ { value: "yeswiki" }] }]
            // entryA { checkboxes: "yeswiki" }
            // => entryA { checkboxes: "yeswiki,website" }
            this.filters.forEach((filter) => {
              const { propName } = filter
              if (entry[propName] && typeof entry[propName] == 'string') {
                const entryValues = entry[propName].split(',')
                entryValues.forEach((value) => {
                  const correspondingNode = filter.flattenNodes.find((node) => node.value == value)
                  if (correspondingNode) {
                    correspondingNode.parents.forEach((parent) => {
                      if (!entryValues.includes(parent.value)) entryValues.push(parent.value)
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
          const event = new Event('bazar-list-dynamic-ready')
          document.dispatchEvent(event)
        }, 0)
      })
    }
  })
}

// Wait for Dom to be loaded, so we can load some Vue component like BazarpMap in order
// to be used inside index-dynamic
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.bazar-list-dynamic-container').forEach(load)
})
