import LeafletMarkerCluster from './LeafletMarkerCluster.js'
import SpinnerLoader from './SpinnerLoader.js'
import LMap from './LMap.js'
import { drawGeometries } from '../leaflet-draw.helper.js'

const BazarMapComponent = {
  props: ['params'],
  components: {
    'l-map': LMap,
    'l-marker-cluster': LeafletMarkerCluster,
    'spinner-loader': SpinnerLoader,
  },
  data() {
    return {
      selectedEntry: null,
      center: null,
      bounds: null,
      layers: {},
    }
  },
  computed: {
    entries() {
      const vMe = this
      return this.$root.entriesToDisplay.filter((entry) => {
        const cGeolocation = vMe.getGeolocation(entry)
        return (
          cGeolocation &&
          ((cGeolocation.latitude && cGeolocation.longitude) ||
            cGeolocation.geometries)
        )
      })
    },
    map() {
      return this.$refs.map ? this.$refs.map.mapObject : null
    },
    mapOptions() {
      return {
        scrollWheelZoom: this.params.zoom_molette,
        zoomControl: this.params.navigation,
        fullscreenControl: this.params.fullscreen,
        fullscreenControlOptions: {
          forceSeparateButton: true,
          title: _t('BAZ_FULLSCREEN'), // change the title of the button, default Full Screen
          titleCancel: _t('BAZ_BACK_TO_NORMAL_VIEW'), // change the title of the button when fullscreen is on, default Exit Full Screen
          // content: '<i class="fa fa-expand-alt"></i>', // change the content of the button, can be HTML, default null
          forceSeparateButton: true, // force seperate button to detach from zoom buttons, default false
        },
        maxZoom: 18,
      }
    },
  },
  methods: {
    getGeolocation(pEntry) {
      let lLatitude
      let lLongitude

      const lGeolocation = pEntry[this.params.geolocationfield]

      if (lGeolocation) {
        lLatitude = lGeolocation.latitude ?? null
        lLongitude = lGeolocation.longitude ?? null
      }

      if (
        lGeolocation &&
        ((lLatitude && lLongitude) || lGeolocation.geometries)
      )
        return lGeolocation
      return null
    },
    updateBounds() {
      if (!this.map) return
      this.bounds = this.map.getBounds()
    },
    createTileLayers() {
      if (!this.map) return
      const provideOptions = this.params.provider_credentials
        ? JSON.parse(this.params.provider_credentials)
        : {}

      const baseLayers = {}
      const providers = this.params.providers || []
      if (providers.length > 0) {
        providers.forEach((p) => {
          baseLayers[p] = L.tileLayer.provider(p)
        })
      } else {
        baseLayers[this.params.provider] = L.tileLayer.provider(
          this.params.provider,
          provideOptions,
        )
      }
      const defaultProvider =
        baseLayers[this.params.provider] || Object.values(baseLayers)[0]
      defaultProvider.addTo(this.map)

      const rawLayers = this.params.layers || []
      for (const rawLayer of rawLayers) {
        let [label, type, options, url] = rawLayer.split('|')
        if (!url) {
          url = options
          options = ''
        }
        const visibleByDefault = /visiblebydefault/i.test(options || '')
        options = (options || '').replace(/visiblebydefault\s*;?/gi, '').trim()
        switch (type.toLowerCase()) {
          case 'tiles':
            this.layers[label] = L.tileLayer(url)
            if (visibleByDefault) this.layers[label].addTo(this.map)
            break
          case 'geojson':
            this.layers[label] = L.geoJson.ajax(url, {
              style(feature, latlng) {
                if (feature.geometry.type == 'Point') return
                const props = feature.properties || {}
                options.split(';').forEach((o) => {
                  if (!o.trim()) return
                  const [key, value] = o.split(':')
                  if (key && value)
                    props[key.trim()] = value.trim().replaceAll("'", '')
                })
                return {
                  fillColor:
                    props._umap_options?.color ??
                    wiki.cssVar('--primary-color'),
                  fillOpacity: props._umap_options?.opacity ?? 0.3,
                  color:
                    props._umap_options?.color ??
                    wiki.cssVar('--primary-color'),
                  opacity: 1,
                  weight: 3,
                  ...props,
                }
              },
              pointToLayer(feature, latlng) {
                return L.circleMarker(latlng)
              },
              onEachFeature(feature, layer) {
                let str = ''
                for (const prop in feature.properties) {
                  const content =
                    prop.toLowerCase() == 'url'
                      ? `<a href="${feature.properties[prop]}" target="_blank">${feature.properties[prop]}</a>`
                      : feature.properties[prop]
                  str += `${prop}: ${content}<br/>`
                }
                layer.bindPopup(str)
              },
            })
            if (visibleByDefault) this.layers[label].addTo(this.map)
            break
          default:
            console.warn(`BazarMap: unknown layer type "${type}"`)
            break
        }
      }

      // Add layer control only if multiple layers are there
      if (
        Object.keys(baseLayers).length > 1 ||
        Object.keys(this.layers).length > 0
      ) {
        L.control.layers(baseLayers, this.layers).addTo(this.map)
      }
    },
    arraysEqual(a, b) {
      if (a === b) return true
      if (a == null || b == null) return false
      if (a.length !== b.length) return false

      a.sort()
      b.sort()
      for (let i = 0; i < a.length; ++i) {
        if (a[i] !== b[i]) return false
      }
      return true
    },
    createMarker(entry) {
      if (entry.marker) return entry.marker
      try {
        const cGeolocation = this.getGeolocation(entry)

        if (cGeolocation) {
          entry.marker = L.marker(
            [cGeolocation.latitude, cGeolocation.longitude],
            { riseOnHover: true },
          )
          const isLink =
            this.isModalDisplay() ||
            this.isDirectLinkDisplay() ||
            this.isNewTabDisplay()
          const tagName = isLink ? 'a' : 'div'
          const url = entry.url + (this.isModalDisplay() ? '/iframe' : '')
          const modalData = this.isModalDisplay()
            ? 'data-size="modal-lg" data-iframe="1" data-header="false"'
            : ''
          entry.marker.setIcon(
            L.divIcon({
              className: `bazar-marker ${this.params.smallmarker}`,
              iconSize: JSON.parse(this.params.iconSize),
              iconAnchor: JSON.parse(this.params.iconAnchor),
              popupAnchor: JSON.parse(this.params.popupAnchor),
              html:
                `
		          <div class="entry-name">
		            <span style="background-color: ${entry.color}">
		              ${entry.markerhover || entry.bf_titre}
		            </span>
		          </div>
		          <${tagName} class="bazar-entry ${this.isModalDisplay() ? 'modalbox' : ''}" ` +
                `${isLink ? `href="${url}"` : ''} style="color: ${entry.color}" ${modalData}>
		            <i class="${entry.icon || 'fa fa-bullseye'}"></i>
		          </${tagName}>`,
            }),
          )
          if (this.isDirectLinkDisplay()) {
            entry.marker.on('click', () => {
              event.preventDefault()
              window.location =
                entry.url + (this.$root.isInIframe() ? '/iframe' : '')
            })
          } else if (this.isNewTabDisplay()) {
            entry.marker.on('click', function () {
              event.preventDefault()
              window.open(entry.url)
              this.selectedEntry = entry
            })
          } else if (!isLink) {
            entry.marker.on('click', (ev) => {
              this.selectedEntry = entry
            })
          }
          return entry.marker
        }
      } catch (e) {
        entry.marker = null
        console.error(
          `Entry ${entry.id_fiche} has invalid geolocation`,
          entry,
          e,
        )
      }
    },
    isModalDisplay() {
      return (
        this.params.entrydisplay != undefined &&
        this.params.entrydisplay == 'modal'
      )
    },
    isNewTabDisplay() {
      return (
        this.params.entrydisplay != undefined &&
        this.params.entrydisplay == 'newtab'
      )
    },
    isDirectLinkDisplay() {
      return (
        this.params.entrydisplay != undefined &&
        this.params.entrydisplay == 'direct'
      )
    },
    openPopup(entry) {
      if (entry.marker == undefined) {
        return false
      }
      // Vue 3: use $slots instead of $scopedSlots
      const slots = this.$slots
      if (slots.popupentrywithhtmlrender != undefined) {
        if (entry.html_render == undefined) {
          let url = ''
          let excludeFields = ''
          if (
            this.params.popupselectedfields &&
            this.params.popupselectedfields.length > 0
          ) {
            const necessaryFieldsArray =
              this.params.popupselectedfields.split(',')
            const keys = Object.keys(this.$root.formFields)
            for (let index = 0; index < keys.length; index++) {
              const key = keys[index]
              if (
                [
                  'id_fiche',
                  'id_typeannonce',
                  'url',
                  'color',
                  'icon',
                  'visual',
                  'marker',
                ].indexOf(key) == -1 &&
                necessaryFieldsArray.indexOf(key) == -1
              ) {
                excludeFields =
                  excludeFields.length == 0 ? key : `${excludeFields},${key}`
              }
            }
          }
          if (this.$root.isExternalUrl(entry)) {
            url = entry.url.replace(
              new RegExp(`${entry.id_fiche}$`),
              `api/entries/html/${entry.id_fiche}`,
            )
            if (excludeFields.length > 0) {
              url = `${url + (url.match('?') ? '&' : '?')}excludeFields=${excludeFields}`
            }
          } else {
            url = wiki.url(`?api/entries/html/${entry.id_fiche}`, {
              ...{ fields: 'html_output' },
              ...(excludeFields.length > 0 ? { excludeFields } : {}),
            })
          }
          this.$root.setEntryFromUrl(entry, url).then(() => {
            // Triggers when the component is ready
            this.$nextTick(() => this.definePopupContent(entry))
          })
        } else {
          // Triggers when the component is ready
          this.$nextTick(() => this.definePopupContent(entry))
        }
      } else if (slots.popupentry != undefined) {
        // Triggers when the component is ready
        this.$nextTick(() => this.definePopupContent(entry))
      }
    },
    definePopupContent(entry) {
      const slots = this.$slots
      const renderedHtml =
        slots.popupentrywithhtml != undefined
          ? $(this.$el)
              .find('.popupentry-container.with-html-render > div')
              .first()
              .html()
          : $(this.$el).find('.popupentry-container > div').first().html()
      if (entry.marker.popup == undefined) {
        if (renderedHtml != undefined && renderedHtml.length != 0) {
          entry.marker
            .bindPopup(renderedHtml, { keepInView: true })
            .on('popupopen', () => {
              if (
                typeof this.$root?.loadBazarListDynamicIfNeeded === 'function'
              ) {
                this.$root.loadBazarListDynamicIfNeeded(renderedHtml)
              }
            })
            .openPopup()
        }
      } else {
        entry.marker.popup.openPopup()
      }
    },
    loadMapEntries(newEntries, oldEntries) {
      if (!this.map) return

      const newIds = newEntries.map((e) => e.id_fiche)

      // Remove geometries for entries no longer displayed
      if (oldEntries) {
        oldEntries.forEach((entry) => {
          if (!newIds.includes(entry.id_fiche)) {
            if (entry.geometryGroup) {
              entry.geometryGroup.remove()
              entry.geometryGroup = null
            }
          }
        })
      }

      const currentMarkers = []

      newEntries.forEach((entry) => {
        this.createMarker(entry)

        // Handle geometries - always added to map directly, not to cluster
        const lGeolocation = entry[this.params.geolocationfield]
        if (lGeolocation && lGeolocation.geometries && !entry.geometryGroup) {
          try {
            const geojsonGeometries = JSON.parse(lGeolocation.geometries)
            entry.geometryGroup = L.featureGroup().addTo(this.map)
            entry.geometryGroup.on('click', (e) => {
              L.DomEvent.stopPropagation(e)
              this.selectedEntry = entry
            })
            drawGeometries(
              entry.geometryGroup,
              geojsonGeometries.features,
              '',
              entry.id_fiche,
            )
          } catch (e) {
            console.error(`Error drawing geometry for ${entry.id_fiche}`, e)
          }
        }

        if (entry.marker) {
          currentMarkers.push(entry.marker)
        }
      })

      // Handle markers - cluster mode uses clearLayers/addLayers, non-cluster adds to map
      if (this.params.cluster && this.$refs.cluster) {
        this.$refs.cluster.clearLayers()
        this.$refs.cluster.addLayers(currentMarkers)
      } else {
        // For non-cluster mode, remove old markers not in new list
        if (oldEntries) {
          oldEntries.forEach((entry) => {
            if (!newIds.includes(entry.id_fiche) && entry.marker) {
              entry.marker.remove()
            }
          })
        }
        currentMarkers.forEach((marker) => {
          if (!marker._map) {
            marker.addTo(this.map)
          }
        })
      }
    },
    onMapReady() {
      this.updateBounds()
      this.createTileLayers()
      // Load initial entries now that map is ready
      this.$nextTick(() => {
        this.loadMapEntries(this.entries, null)
      })
    },
  },
  watch: {
    selectedEntry(newVal, oldVal) {
      if (oldVal && oldVal.marker && oldVal.marker._icon)
        oldVal.marker._icon.classList.remove('selected')
      if (this.selectedEntry) {
        if (this.params.entrydisplay == 'newtab') {
          this.$root.openEntry(this.selectedEntry)
        } else if (this.params.entrydisplay == 'sidebar') {
          this.$root.getEntryRender(this.selectedEntry)
        } else if (this.params.entrydisplay == 'popup') {
          this.openPopup(this.selectedEntry)
        }

        this.$nextTick(function () {
          if (this.selectedEntry.marker && this.selectedEntry.marker._icon) {
            this.selectedEntry.marker._icon.classList.add('selected')
          }
        })
      }
    },
    params: {
      handler() {
        this.center = [this.params.latitude, this.params.longitude]
      },
      immediate: true,
    },
    entries(newVal, oldVal) {
      if (!this.map) return

      const newIds = newVal.map((e) => e.id_fiche)
      const oldIds = oldVal ? oldVal.map((e) => e.id_fiche) : []

      // Only update if arrays differ
      if (!this.arraysEqual(newIds, oldIds)) {
        this.$nextTick(() => {
          this.loadMapEntries(newVal, oldVal)
        })
      }
    },
  },
  template:
    `
    <div class="bazar-map-container" :style="{height: params.height}"
        :class="{'small-width': $el ? $el.offsetWidth < 800 : true, 'small-height': $el ? $el.offsetHeight < 600 : true }">

      <l-map v-if="center" ref="map" :zoom="params.zoom" :center="center"
             :options="mapOptions"
             @update:center="updateBounds()" @ready="onMapReady()"
             @click="selectedEntry = null">
        <l-marker-cluster ref="cluster" ></l-marker-cluster>
      </l-map>


      <!-- SideNav to display entry -->
      <div v-if="selectedEntry && this.params.entrydisplay == 'sidebar'" class="entry-container">
        <div class="btn-close" @click="selectedEntry = null"><i class="fa fa-times"></i></div>
        <div v-html="selectedEntry.html_render"></div>
      </div>` +
    // popup content
    `<div v-if="selectedEntry && this.params.entrydisplay == 'popup'" class="popupentry-container with-html-render">
        <slot name="popupentrywithhtmlrender" v-bind="{entry:selectedEntry}"></slot>
      </div>
      <div v-if="selectedEntry && this.params.entrydisplay == 'popup'" class="popupentry-container">
        <slot name="popupentry" v-bind="{entry:selectedEntry}"></slot>
      </div>

      <spinner-loader v-if="this.$root.isLoading || !this.$root.ready" class="overlay super-overlay"></spinner-loader>
    </div>
  `,
}

// Register component globally for Vue 3 compatibility
// This will be done by the app that imports it
export default BazarMapComponent
