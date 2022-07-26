import Panel from './components/Panel.js'
import EntryField from './components/EntryField.js'
import PopupEntryField from './components/PopupEntryField.js'
import SpinnerLoader from './components/SpinnerLoader.js'
import ModalEntry from './components/ModalEntry.js'
import BazarSearch from './components/BazarSearch.js'

const { createApp } = Vue

// create bazar app in window variable
window.bazarVueApp = createApp({
  components: { Panel, ModalEntry, SpinnerLoader, EntryField, PopupEntryField },
  mixins: [ BazarSearch ],
  data() {
    return {
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
      searchFormId: null, // wether to search for a particular form ID (only used when no form id is defined for the bazar list action)
      searchTimer: null // use ot debounce user input
    }
  },
  computed: {
    computedFilters() {
      let result = {}
      for(const filterId in this.filters) {
        let checkedValues = this.filters[filterId].list.filter(option => option.checked)
                                                        .map(option => option.value)
        if (checkedValues.length > 0) result[filterId] = checkedValues
      }
      return result
    },
    filteredEntriesCount() {
      return this.filteredEntries.length
    },
    pages() {
      if (this.pagination <= 0) return []
      let pagesCount = Math.ceil(this.filteredEntries.length / parseInt(this.pagination))
      let start = 0, end = pagesCount - 1
      let pages = [this.currentPage - 2, this.currentPage - 1, this.currentPage, this.currentPage + 1, this.currentPage + 2]
      pages = pages.filter(page => page >= start && page <= end)
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
    searchedEntries() { this.calculateFiltersCount() },
    mounted() {
      if (this.mounted){
        $(this.$el.parentNode).addClass('mounted')
      } else {
        $(this.$el.parentNode).removeClass('mounted')
      }
    }
  },
  methods: {
    calculateBaseEntries() {
      let result = this.entries
      if (this.searchFormId) {
        result = result.filter(entry => {
          // filter based on formId, when no form id is specified
          return entry.id_typeannonce == this.searchFormId
        })
      }
      if (this.search && this.search.length > 2) {
        result = this.searchEntries(result, this.search)
        if (result == undefined){
          result = this.entries
        }
      }
      this.searchedEntries = result
      this.filterEntries()
    },
    filterEntries() {
      // Handles filters
      let result = this.searchedEntries
      for(const filterId in this.computedFilters) {
        result = result.filter(entry => {
          if (!entry[filterId] || typeof entry[filterId] != "string") return false
          return entry[filterId].split(',').some(value => {
            return this.computedFilters[filterId].includes(value)
          })
        })
      }
      this.filteredEntries = result
      this.paginateEntries()
    },
    paginateEntries() {
      let result = this.filteredEntries
      if (this.pagination > 0) {
        let start = this.pagination * this.currentPage
        result = result.slice(start, start + this.pagination)
      }
      this.paginatedEntries = result
      this.formatEntries()
    },
    formatEntries() {
      this.paginatedEntries.forEach(entry => {
        entry.color = this.colorIconValueFor(entry, this.params.colorfield, this.params.color)
        entry.icon  = this.colorIconValueFor(entry, this.params.iconfield, this.params.icon)
      })
      this.entriesToDisplay = this.paginatedEntries
    },
    calculateFiltersCount() {
      for(let fieldName in this.filters) {
        for (let option of this.filters[fieldName].list) {
          option.nb = this.searchedEntries.filter(entry => {
            let entryValues = entry[fieldName]
            if (!entryValues || typeof entryValues != "string") return
            entryValues = entryValues.split(',')
            return entryValues.some(value => value == option.value)
          }).length
        }
      }
    },
    resetFilters() {
      for(let filterId in this.filters) {
        this.filters[filterId].list.forEach(option => option.checked = false)
      }
      this.search = ''
    },
    saveFiltersIntoHash() {
      if (!this.ready) return
      let hashes = []
      for(const filterId in this.computedFilters) {
        hashes.push(`${filterId}=${this.computedFilters[filterId].join(',')}`)
      }
      if (this.search) hashes.push(`q=${this.search}`)
      document.location.hash = hashes.length > 0 ? hashes.join('&') : null;
    },
    initFiltersFromHash(filters, hash) {
      hash = hash.substring(1) // remove #
      for(let combinaison of hash.split('&')) {
        let filterId = combinaison.split('=')[0]
        let filterValues = combinaison.split('=')[1]
        if (filterId == "q") {
          this.search = filterValues
        }
        else if (filterId && filterValues && filters[filterId]) {
          filterValues = filterValues.split(',')
          for(let filter of filters[filterId].list) {
            if (filterValues.includes(filter.value)) filter.checked = true
          }
        }
      }
      // init q from GET q also
      if (this.search.length == 0) {
        let params = document.location.search;
        params = params.substring(1) // remove ?
        for(let combinaison of params.split('&')) {
          let filterId = combinaison.split('=')[0]
          let filterValues = combinaison.split('=')[1]
          if (filterId == "q") {
            this.search = decodeURIComponent(filterValues);
          }
        }
      }
      return filters
    },
    getEntryRender(entry) {
      if (entry.html_render) return
      if (this.isExternalUrl(entry)){
        this.getExternalEntry(entry)
      } else {
        let fieldsToExclude = []
        if (this.params.template == 'list' && this.params.displayfields) {
          // In list template (collapsible panels with header and body), the rendered entry
          // is displayed in the body section and we don't want to show the fields
          // that are already displayed in the panel header
          fieldsToExclude = Object.values(this.params.displayfields)
        }
        let url = wiki.url(`?api/entries/html/${entry.id_fiche}`, {
          fields: 'html_output',
          excludeFields: fieldsToExclude
        })
        $.getJSON(url, function(data) {
          Vue.set(entry, 'html_render', (data[entry.id_fiche] && data[entry.id_fiche].html_output) ? data[entry.id_fiche].html_output : 'error')
        })
      }
    },
    fieldInfo(field) {
      return this.formFields[field] || {}
    },
    openEntry(entry) {
      if (this.params.entrydisplay == 'newtab')
        window.open(entry.url)
      else
        this.$root.openEntryModal(entry)
    },
    openEntryModal(entry) {
      this.$refs.modal.displayEntry(entry)
    },
    isExternalUrl(entry){
      if (!entry.url){
        return false;
      }
      return entry.url !== wiki.url(entry.id_fiche);
    },
    isInIframe(){
      return (window != window.parent);
    },
    getExternalEntry(entry){
      let url = entry.url+'/iframe';
      Vue.set(entry, 'html_render', `<iframe src="${url}" width="500px" height="600px" style="border:none;"></iframe>`)
    },
    colorIconValueFor(entry, field, mapping) {
      if (!entry[field] || typeof entry[field] != "string") return null
      let values = entry[field].split(',')
      // If some filters are checked, and the entry have multiple values, we display
      // the value associated with the checked filter
      // TODO BazarListDynamic check with users if this is expected behaviour
      if (this.computedFilters[field]) values = values.filter(val => this.computedFilters[field].includes(val))
      return mapping[values[0]]
    },
    urlImageResizedOnError(entry,fieldName,width,height,mode,token) {
      let node = event.target;
      $(node).removeAttr('onerror');
      if (entry[fieldName]){
        let fileName = entry[fieldName];
        if (!this.isExternalUrl(entry)){
          // currently not supporting api for external images (anti-csrf token not generated)
          if (this.tokenForImages === null){
            this.tokenForImages = token;
          }
          this.imagesToProcess.push({
            fileName: fileName,
            width: width,
            height: height,
            mode: mode,
            node: node
          });
          this.processNextImage();
        } else {
          let baseUrl = entry.url.slice(0,-entry.id_fiche.length).replace(/\?$/,"").replace(/\/$/,"");
          let previousUrl = $(node).prop('src');
          let newUrl = `${baseUrl}/files/${fileName}`;
          if (newUrl != previousUrl){
            $(`img[src="${previousUrl}"]`).each(function(){
              $(this).prop('src',newUrl);
            });
          }
        }
      }
    },
    urlImage(entry,fieldName,width,height,mode) {
      if (!entry[fieldName]){
        return null;
      }
      let baseUrl = (this.isExternalUrl(entry))
        ? entry.url.slice(0,-entry.id_fiche.length)
        : wiki.baseUrl;
      baseUrl = baseUrl.replace(/\?$/,"").replace(/\/$/,"");
      let fileName = entry[fieldName];
      let field = this.fieldInfo(fieldName);
      let regExp = new RegExp(`^(${entry.id_fiche}_${field.propertyname}_.*)_(\\d{14})_(\\d{14})\\.([^.]+)$`);
      
      if (regExp.test(fileName)){
        return `${baseUrl}/cache/${fileName.replace(regExp,`$1_${mode == "fit" ? "vignette" : "cropped"}_${width}_${height}_$2_$3.$4`)}`;
      }
      regExp = new RegExp(`^(${entry.id_fiche}_${field.propertyname}_.*)\\.([^.]+)$`);
      if (regExp.test(fileName)){
        return `${baseUrl}/cache/${fileName.replace(regExp,`$1_${mode == "fit" ? "vignette" : "cropped"}_${width}_${height}.$2`)}`;
      }
      // maybe from other entry
      regExp = new RegExp(`^([A-Za-z0-9-_]+_${field.propertyname}_.*)_(\\d{14})_(\\d{14})\\.([^.]+)$`);
      if (regExp.test(fileName)){
        return `${baseUrl}/cache/${fileName.replace(regExp,`$1_${mode == "fit" ? "vignette" : "cropped"}_${width}_${height}_$2_$3.$4`)}`;
      }
      // last possible format
      regExp = new RegExp(`^(.*)\\.([^.]+)$`);
      if (regExp.test(fileName)){
        return `${baseUrl}/cache/${fileName.replace(regExp,`$1_${mode == "fit" ? "vignette" : "cropped"}_${width}_${height}.$2`)}`;
      }
      return `${baseUrl}/files/${fileName}`;
    },
    processNextImage(){
      if (!this.processingImage && this.imagesToProcess.length > 0){
        this.processingImage = true;
        let newImageParams = this.imagesToProcess[0];
        this.imagesToProcess = this.imagesToProcess.slice(1);
        let bazarListDynamicRoot = this;
        $.ajax({
          url: wiki.url(`api/images/${newImageParams.fileName}/cache/${newImageParams.width}/${newImageParams.height}/${newImageParams.mode}`,{csrftoken:this.tokenForImages}),
          method: 'get',
          cache: false,
          success: function (data){
            let previousUrl = $(newImageParams.node).prop('src');
            if (data.cachefilename != previousUrl){
              $(`img[src="${previousUrl}"]`).each(function(){
                $(this).prop('src',data.cachefilename);
              });
            }
          },
          complete: function (e){
            if (e.responseJSON != undefined && e.responseJSON.newToken != undefined ){
              bazarListDynamicRoot.tokenForImages = e.responseJSON.newToken;
            }
            bazarListDynamicRoot.processingImage = false;
            bazarListDynamicRoot.processNextImage();
          }
        });
      }
    }
  },
  mounted() {
    $(this.$el.parentNode).on(
      "dblclick",
      function (e) {
        return false;
      }
    );
    let savedHash = document.location.hash // don't know how, but the hash get cleared after
    this.params = JSON.parse(this.$el.parentNode.dataset.params)
    this.pagination = parseInt(this.params.pagination)
    this.mounted = true
    // Retrieve data asynchronoulsy
    $.getJSON(wiki.url('?api/entries/bazarlist'), this.params, (data) => {
      // First display filters cause entries can be a bit long to load
      this.filters = this.initFiltersFromHash(data.filters || [], savedHash)

      // Auto adjust some params depending on entries count
      if (data.entries.length > 50 && !this.pagination) this.pagination = 20 // Auto paginate if large numbers
      if (data.entries.length > 1000) this.params.cluster = true // Activate cluster for map mode
      
      setTimeout(() => {
        // Transform forms info into a list of field mapping
        // { bf_titre: { type: 'text', ...}, bf_date: { type: 'listedatedeb', ... } }
        Object.values(data.forms).forEach(formFields => {
          Object.values(formFields).forEach(field => {
            this.formFields[field.id] = field
            Object.entries(this.params.displayfields).forEach( ([fieldId, mappedField]) => {
              if (mappedField == field.id) this.formFields[fieldId] = this.formFields[mappedField]
            })
          })
        })

        this.entries = data.entries.map(array => {
          let entry = { color: null, icon: null }
          // Transform array data into object using the fieldMapping
          for(let key in data.fieldMapping) {
            entry[data.fieldMapping[key]] = array[key]
          }
          Object.entries(this.params.displayfields).forEach( ([field, mappedField]) => {
            if (mappedField) entry[field] = entry[mappedField]
          })
          
          return entry
        })
        
        this.calculateBaseEntries()
        this.ready = true
      }, 0)
    })
  }
});
// allow usage of wiki in templates
window.bazarVueApp.config.globalProperties.wiki = wiki;
window.vue = Vue // add Vue globally to allow vue-leaflet to use it

// Wait for Dom to be loaded, so we can load some Vue component like BazarpMap in order
// to be used inside index-dynamic
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll(".bazar-list-dynamic-container").forEach(domElement =>{
    window.bazarVueApp.mount(domElement)
  });
})
