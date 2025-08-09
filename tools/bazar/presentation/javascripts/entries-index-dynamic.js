import Panel from '../../../../javascripts/shared-components/Panel.js'
import EntryField from './components/EntryField.js'
import PopupEntryField from './components/PopupEntryField.js'
import SpinnerLoader from './components/SpinnerLoader.js'
import ModalEntry from './components/ModalEntry.js'
import FilterNode from './components/FilterNode.js'
import { initEntryMaps } from './fields/map-field-map-entry.js'
import { recursivelyCalculateRelations, deepGet } from './utils.js'
import ImageMixin from './entries-index-dynamic/image-mixin.js'
import BazarSearch from './entries-index-dynamic/search-mixin.js'

Vue.component('FilterNode', FilterNode)

const load = (domElement) => {
  new Vue({
    el: domElement,
    mixins: [BazarSearch, ImageMixin],
    components: {
      Panel,
      ModalEntry,
      SpinnerLoader,
      EntryField,
      PopupEntryField
    },
    data: {
      mounted: false, // when vue get initialized
      ready: false, // when ajax data have been retrieved
      params: {},

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
      currentSort: { field: '', order : null, label: '' },

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
        const pagesCount = Math.ceil(
          this.filteredEntries.length / parseInt(this.pagination, 10)
        )
        const start = 0
        const end = pagesCount - 1
        let pages = [
          this.currentPage - 2,
          this.currentPage - 1,
          this.currentPage,
          this.currentPage + 1,
          this.currentPage + 2
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
      filteredEntriesCount() {
        this.currentPage = 0
      },
      search() {
        clearTimeout(this.searchTimer)
        this.searchTimer = setTimeout(() => this.calculateBaseEntries(), 350)
        this.updateHash()
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
      }
    },
    methods: {
      calculateBaseEntries() {
        let result = this.entries
        if (this.searchFormId) {
          // filter based on formId, when no form id is specified
          result = result.filter(
            (entry) => entry.id_typeannonce == this.searchFormId
          )
        }
        
        var vSearch = this.params["keywords"]??"";

        if (this.search && this.search.length > 2) vSearch += (vSearch!=""?"|":"") + this.search;
        
        if (vSearch && vSearch.length > 2) {
          result = this.searchEntries(result, vSearch)
          if (result == undefined) {
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
            if (!entry[propName] || typeof entry[propName] != 'string') return false
            return entry[propName]
              .split(',')
              .map (str => 
						str
						.replace(/&/g, '&amp;')
						.replace(/</g, '&lt;')
						.replace(/>/g, '&gt;')
						.replace(/"/g, '&quot;')
						.replace(/'/g, '&#039;'))
              .some(function (value) 
              {
              	return filter.includes(value)
              })
              /*
              var filterValues = hashValue.split(',').map (str => 
						str
						.replace(/&/g, '&amp;')
						.replace(/</g, '&lt;')
						.replace(/>/g, '&gt;')
						.replace(/"/g, '&quot;')
						.replace(/'/g, '&#039;'))*/
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
            return order == "asc" ? valueA - valueB : valueB - valueA
          }

			// Case and accent insensitive sort
	
          return order == "asc"
            ? collator.compare(String(valueA).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(), String(valueB).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase())
            : collator.compare(String(valueB).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(), String(valueA).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase())
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
            this.params.color
          )
          entry.icon = this.colorIconValueFor(
            entry,
            this.params.iconfield,
            this.params.icon
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
              return entryValues.some(function (value)
              {
	              // Handle values with special chars like "Figuier goutte d'or" since PHP BazarListService.php store it by calling htmlspecialchars first
	              
              	if (typeof (value) == "string") 
              	{
              		return (value
            	  	.replace(/&/g, '&amp;')
				    .replace(/</g, '&lt;')
				    .replace(/>/g, '&gt;')
				    .replace(/"/g, '&quot;')
				    .replace(/'/g, '&#039;') == node.value);
              	}
              	else              	
	               return (value == node.value);
              });
            }).length
          })
        })
      },
	  parseCondition (pValue)
      {
	    // Extraire nom, opérateur et valeurs
	    const regex = /([^=!<>]*)([=!<>]+)([^=!<>]*)/;
		const matches = pValue.match(regex);

		if (!matches) return null;

		let vName = matches[1].trim();
		let vOperator = matches[2].trim();
		let rawValues = matches[3].trim();

		// Convertir l'opérateur "=" en "=="
		if (vOperator === "=") vOperator = "==";

		// Transformer la liste en tableau avec valeurs uniques
		const vUniqueValues = Array.from(
		    new Set(
		        rawValues.split(',').map(v => v.trim()).filter(v => v !== "")
		    )
		);

	    // Retourner la structure
	    
	    var vResult =
	    {
	        name : vName,
	        operator : vOperator,
	        values: vUniqueValues
      	};
	    
	    return vResult;

	  },
      parseSearchParams (pParams) // Return params as a structured object
      {
			var vParams = new URLSearchParams(pParams);
			
			var vParseds = {};
			
			for (const cKey of vParams.keys())
		  	{
				var vValue = vParams.get (cKey);
				
		  		if ((cKey == 'q')||(cKey == 'keywords')) // keywords supports for clarity (q parameter is confusing with query parameter)
				{
					vParseds ["keywords"] = vValue; // privilegiate use of "keywords"
				}
				else if ((cKey == 'champ') || (cKey == 'ordre'))
				{
					vParseds [cKey] = vValue;
				}
				else if (cKey == 'query')
				{			
					vParseds [cKey] = parseCondition (decodeURIComponent (vValue));
				}													   
			    else	      	
			    {
				    vParseds [cKey] = vValue;//.replace ("+", " ")
				}								
			}
			
			return vParseds;			 
      },
      mergeSearchParams (pParams1, pParams2) // string or structured params objet
      {
	      	var vMerged = {};
      
      		var vParamsObject1 =	typeof (pParams1) == "string"?this.parseSearchParams(pParams1):pParams1;
      		var vParamsObject2 =	typeof (pParams2) == "string"?this.parseSearchParams(pParams2):pParams2;

			if (vParamsObject1.query && vParamsObject2.query)
			{				
				vMerged.query = { ...vParamsObject1.query, ...vParamsObject2.query };
			}
			else
			if (vParamsObject1.query)
			{
				vMerged.query = vParamsObject1.query; 
			}
			else
			if (vParamsObject2.query)
			{
				vMerged.query = vParamsObject2.query; 
			}			
			
			if (vMerged.query) 
				vMerged.query = encodeURIComponent
				(
					Object
					.entries (vMerged.query)
					.map ( ([ pName, pOperator, pValue ]) => pName + pOperator + pValue )
					.join ("|")
				);

			vParamsObject1.query = undefined;
			vParamsObject2.query = undefined;
      
			$.extend (true, vMerged, vParamsObject1, vParamsObject2);

			return vMerged;
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
      initFilters (pFilters, pHash)
      {
        var vHash = decodeURIComponent(pHash.substring(1)) // remove # and avoid confusion between hash parameter and search parameter when using & symbol in the hash

		var vParams = this.parseSearchParams (vHash); // Return hash as a structured object
		var vChamp;
		var vOrdre;

		if (vParams["q"] !== undefined && vParams["q"].trim () !== "")
		{
            this.search = vParams["q"];
		}
		
		if (vParams["keyword"] !== undefined && vParams["keywords"].trim () !== "")
		{
            this.search = vParams["keywords"];
		}
		
		if (vParams["champ"] !== undefined && vParams["champ"].trim () !== "")
		{
            vChamp = vParams["champ"];
		}
		
		if (vParams["ordre"] !== undefined && vParams["ordre"].trim () !== "")
		{
            vChamp = vParams["ordre"];
		}
		
		if (vParams["query"] !== undefined)
		{		
			var vQueryEntries = Object.entries (vParams["query"]);
			
			if (vQueryEntries.length > 0)
			{
				vQueryEntries.forEach (function ( [ pKey, pValue] )
				{
					const cFilter = pFilters.find((pF) => pF.propName == pKey);

					if (cFilter)
					{
						cFilter.flattenNodes.forEach((pNode) =>
				        {
					    	// Handle values with special chars 
					    	// like ' in "Figuier goutte d'or" since PHP BazarListService.php store it by calling htmlspecialchars first
					    	// ie : Figuier goutte d&#039;or
					    
							const cFilterValues = pValue
							.split(',')
							.map (pString => 
									pString
									.toLowerCase ()
									.replace(/&/g, '&amp;')
									.replace(/</g, '&lt;')
									.replace(/>/g, '&gt;')
									.replace(/"/g, '&quot;')
									.replace(/'/g, '&#039;'));
							
							if (cFilterValues.includes(pNode.value.toLowerCase ())) pNode.checked = true
					    })
					}				
				})
			}
		}	
					
		var vMe = this;
					
	    const cSort = this.sortOptions.find((s) => s.field == (vChamp??(typeof (vMe.currentSort)!="undefined")?vMe.currentSort.field:"") && s.order == (vOrdre??(typeof (vMe.currentSort)!="undefined")?vMe.currentSort.order:""));

        if (cSort)
        {
        	this.currentSort = cSort;
		}	  
        
        return pFilters;
      },            
      updateHash ()
      {
		if (!this.ready) return
		
		var cCurrentHash = decodeURIComponent (new URL(document.URL).hash.slice(1));		
		
		var vQuery = {};
		var vCurrentParams = {};
		var vMergedParams;
		
		if (this.search.trim() != "") vCurrentParams.keywords = this.search;
		if (this.currentSort.field != "") vCurrentParams.champ = this.currentSort.field;
		if (this.currentSort.order) vCurrentParams.ordre = this.currentSort.order;
		
		var bHasFilter = false;
		
		for (const cFilterId in this.computedFilters)
		{
			bHasFilter = true;
		
			vQuery [cFilterId] = this.computedFilters[cFilterId]
								.map (	pString =>
										pString
										.replace(/&amp;/g, '&')
										.replace(/&lt;/g, '<')
										.replace(/&gt;/g, '>')
										.replace(/&quot;/g, '"')
										.replace(/&#039;/g, "'")
								)
								.join (",");			
		}
			
		if (bHasFilter) vCurrentParams.query = vQuery;		
		
		vMergedParams = Object
			.entries (this.mergeSearchParams (cCurrentHash, vCurrentParams))
			.map ( ([ pName, pOperator, pValue ]) => pName + pOperator + pValue )			
			.join ("&");
		
		history.pushState({}, '', '#' + encodeURIComponent (vMergedParams)); // Avoid confusion between hash parameter and search parameter when using & symbol in the hash

		this.updateExportLinks(vMergedParams) // Export 
      },      
      updateExportLinks(pSearchParams)
      {            	
	    document.querySelectorAll('.export-links > a').forEach((link) => {
        
			var vOldHREF = link.getAttribute('oldhref'); // Get the original href
 	
			if (!vOldHREF) // if it didn't exist yet, remember what it is.
			{
				vOldHREF = link.getAttribute('href')
				link.setAttribute('oldhref', vOldHREF);
			}

			var vNewHREF;

			if (vOldHREF.trim() === '')
			{
				console.error('Invalid URL provided.')
			}
			else
			{	        	
				var vNewURL = new URL (vOldHREF);

			 	var vHandler = vNewURL.searchParams.keys().next();			 	
				var vHandlerValue = vHandler.value;
				
				if (vHandler) vNewURL.searchParams.delete (vHandlerValue)
				else vHandlerValue = "";

				if (vNewURL.searchParams.has ("query"))
				{
					vNewURL.searchParams.set ("query", decodeURIComponent (vNewURL.searchParams.get("query")));
				}

				link.setAttribute ("href",
					vNewURL.origin + 
					vNewURL.pathname + 
					"?" + vHandlerValue + 
					"&" + (	
								Object.entries
								(
									this									
									.mergeSearchParams (vNewURL.searchParams.toString(), pSearchParams)
								)
								.map ( ([ pName, pOperator, pValue ]) => pName + pOperator + pValue )	
								.join("&")
						  ) +
					vNewURL.hash)	        	
			}
		})
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
            ...(fieldsToExclude.length > 0
              ? { excludeFields: fieldsToExclude }
              : {}),
            ...(this.params.showmapinlistview
              ? { showmapinlistview: this.params.showmapinlistview }
              : {})
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
            const html = data?.[entry.id_fiche]?.html_output ?? 'error'
            Vue.set(entry, 'html_render', html)
            return html
          })
          .catch(() => 'error') // in case of error do nothing
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
          const unmounted = document.querySelectorAll(
            '.bazar-list-dynamic-container:not(.mounted)'
          )
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
        return window != window.parent
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
      }
    },
    mounted() {
      $(this.$el).on('dblclick', (e) => false)
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

        this.params.sortfields.forEach((field, index) => {
          const label = this.params.sortfieldstitles[index]
          this.sortOptions.push({ field : field.trim(), label, order : "asc" })
          this.sortOptions.push({ field : field.trim(), label, order : "desc" })
        })

        if (this.sortOptions.length > 0) {
          // params "champ" is used to choose default sort (backend sort). If present
          // we do not overwride this backend sort by the front end dynamic sort
          if (this.params.champ) {
            const sort = this.sortOptions
			.find((o) =>	o.field === this.params.champ.trim() && 
						   	o.order === ((typeof (this.params.ordre)=="boolean"?this.params.ordre:(this.params.ordre=="1"||this.params.ordre=="true"||this.params.ordre=="asc"))?"asc":"desc"))

            if (sort) { this.currentSort = sort }
            else { this.currentSort = this.sortOptions[0] }
        }}


        // First display filters cause entries can be a bit long to load
        this.filters = this.initFilters(filters, savedHash)

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
              Object.entries(this.params.displayfields).forEach(
                ([fieldId, mappedField]) => {
                  if (mappedField == field.id) this.formFields[fieldId] = this.formFields[mappedField]
                }
              )
            })
          })

          this.entries = data.entries.map((entryAsArray) => {
            const entry = { color: null, icon: null }
            // Transform entryAsArray data into object using the fieldMapping
            Object.entries(data.fieldMapping).forEach(([key, mapping]) => {
              entry[mapping] = entryAsArray[key]
            })
            Object.entries(this.params.displayfields).forEach(
              ([field, mappedField]) => {
                if (mappedField) entry[field] = entry[mappedField]
              }
            )

            // In case of Tree, if an entry have only one value down the tree then add all the parent :
            // filters for checkboxes: [{ value: "website", children: [ { value: "yeswiki" }] }]
            // entryA { checkboxes: "yeswiki" }
            // => entryA { checkboxes: "yeswiki,website" }
            this.filters.forEach((filter) => {
              const { propName } = filter
              if (entry[propName] && typeof entry[propName] == 'string') {
                const entryValues = entry[propName].split(',')
                entryValues.forEach((value) => {
                  const correspondingNode = filter.flattenNodes.find(
                    (node) => node.value == value
                  )
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
          this.updateHash()
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
