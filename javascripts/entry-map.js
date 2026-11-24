/* The server-rendered map of a list: Leaflet draws the points the server wrote beside it, and keeps its view when a facet or a search swaps them. */
import { drawGeometries } from './leaflet-draw.helper.js'
import { inkOn } from './colour-contrast.js'
import { legacyIconToSprite } from './yw-icon-map.js'

const views = new Map()
const maps = new Map()

const escapeHtml = (text) =>
  String(text ?? '').replace(
    /[&<>"']/g,
    (c) =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[
        c
      ],
  )

/** Adds the backgrounds and the overlays the webmaster listed, with a switcher when there is more than one. */
function addLayers(map, setup) {
  const baseLayers = {}
  if (setup.providers.length > 0) {
    setup.providers.forEach((name) => {
      baseLayers[name] = L.tileLayer.provider(name)
    })
  } else {
    baseLayers[setup.provider] = L.tileLayer.provider(
      setup.provider,
      setup.credentials || {},
    )
  }
  ;(baseLayers[setup.provider] || Object.values(baseLayers)[0]).addTo(map)

  const overlays = {}
  setup.layers.forEach((raw) => {
    let [label, type, options, url] = raw.split('|')
    if (!url) {
      url = options
      options = ''
    }
    const visible = /visiblebydefault/i.test(options || '')
    options = (options || '').replace(/visiblebydefault\s*;?/gi, '').trim()
    switch ((type || '').toLowerCase()) {
      case 'tiles':
        overlays[label] = L.tileLayer(url)
        break
      case 'geojson':
        overlays[label] = L.geoJson.ajax(url, {
          interactive: false,
          style(feature) {
            if (feature.geometry.type === 'Point') return undefined
            const props = feature.properties || {}
            options.split(';').forEach((option) => {
              const [key, value] = option.split(':')
              if (key && value)
                props[key.trim()] = value.trim().replaceAll("'", '')
            })
            const colour =
              props._umap_options?.color ?? wiki.cssVar('--yw-primary')
            return {
              fillColor: colour,
              fillOpacity: props._umap_options?.opacity ?? 0.3,
              color: colour,
              opacity: 1,
              weight: 3,
              ...props,
            }
          },
          pointToLayer: (feature, latlng) => L.circleMarker(latlng),
        })
        break
      default:
        console.warn(`entry map: unknown layer type "${type}"`)
        return
    }
    if (visible) overlays[label].addTo(map)
  })

  if (Object.keys(baseLayers).length > 1 || Object.keys(overlays).length > 0) {
    L.control.layers(baseLayers, overlays).addTo(map)
  }
}

/** The address an entry opens at, through the iframe handler when the list is itself in one. */
function entryUrl(point, setup, forModal) {
  return point.url + (forModal || setup.inIframe ? '/iframe' : '')
}

function markerIcon(point, setup) {
  const modal = setup.display === 'modal'
  const link = ['modal', 'direct', 'newtab'].includes(setup.display)
  const tagName = link ? 'a' : 'div'
  const pellet = point.color
    ? ` style="background-color: ${escapeHtml(point.color)}; color: ${escapeHtml(inkOn(point.color) || '')}"`
    : ''
  const attributes = [
    `class="bazar-entry${modal ? ' modalbox' : ''}"`,
    link
      ? `href="${escapeHtml(entryUrl(point, setup, modal))}" tabindex="-1"`
      : '',
    point.color ? `style="color: ${escapeHtml(point.color)}"` : '',
    modal ? 'data-size="modal-lg" data-iframe="1" data-header="false"' : '',
  ].join(' ')
  return L.divIcon({
    className: setup.marker.className,
    iconSize: setup.marker.iconSize,
    iconAnchor: setup.marker.iconAnchor,
    popupAnchor: setup.marker.popupAnchor,
    html: `<div class="entry-name"><span${pellet}>${escapeHtml(point.hover)}</span></div>
      <${tagName} ${attributes}>${point.icon || legacyIconToSprite('bullseye')}</${tagName}>`,
  })
}

/** The entry's HTML, with only the fields the popup was told to show. */
async function fetchEntry(container, setup, tag) {
  let url = container.dataset.fragmentUrl.replace(
    '__TAG__',
    encodeURIComponent(tag),
  )
  if (setup.display === 'popup' && setup.excludeFields) {
    url += `${url.includes('?') ? '&' : '?'}excludeFields=${encodeURIComponent(setup.excludeFields)}`
  }
  const response = await fetch(url, { headers: { Accept: 'text/html' } })
  return response.ok ? response.text() : ''
}

function closeSide(container) {
  const side = container.querySelector('.yw-entry-map__side')
  side.hidden = true
  side.querySelector('.yw-entry-map__entry').innerHTML = ''
  container
    .querySelectorAll('.bazar-marker.selected')
    .forEach((marker) => marker.classList.remove('selected'))
  maps.get(container.dataset.list)?.invalidateSize()
}

async function openEntry(container, setup, point, layer) {
  container
    .querySelectorAll('.bazar-marker.selected')
    .forEach((marker) => marker.classList.remove('selected'))
  layer.getElement?.()?.classList.add('selected')

  if (setup.display === 'modal') {
    const opener = document.createElement('a')
    opener.className = 'modalbox'
    opener.href = entryUrl(point, setup, true)
    Object.assign(opener.dataset, {
      iframe: '1',
      size: 'modal-lg',
      header: 'false',
    })
    container.append(opener)
    opener.click()
    opener.remove()
    return
  }
  if (setup.display === 'newtab') {
    window.open(point.url)
    return
  }
  if (setup.display === 'direct') {
    window.location = entryUrl(point, setup, false)
    return
  }
  const html = await fetchEntry(container, setup, point.tag)
  if (setup.display === 'popup') {
    const button = setup.entryButton
      ? `<a class="btn btn-primary btn-block btn-popup-map${setup.inIframe ? ' newtab' : ' modalbox'}" data-iframe="1" data-size="modal-lg" href="${escapeHtml(entryUrl(point, setup, !setup.inIframe))}">${escapeHtml(setup.labels.seeEntry)}</a>`
      : ''
    layer
      .bindPopup(`<div class="popup-entry-content">${html}</div>${button}`, {
        keepInView: true,
      })
      .openPopup()
    return
  }
  const side = container.querySelector('.yw-entry-map__side')
  const holder = side.querySelector('.yw-entry-map__entry')
  holder.innerHTML = html
  side.hidden = false
  window.htmx?.process(holder)
  maps.get(container.dataset.list)?.invalidateSize()
}

function addPoints(map, container, setup) {
  const markers = []
  setup.points.forEach((point) => {
    if (point.geometries) {
      const group = L.featureGroup().addTo(map)
      group.on('click', (event) => {
        L.DomEvent.stopPropagation(event)
        openEntry(container, setup, point, event.layer || group)
      })
      try {
        drawGeometries(group, point.geometries.features, '', point.tag)
      } catch (error) {
        console.error(`Error drawing geometry for ${point.tag}`, error)
      }
    }
    if (point.lat === undefined || point.lng === undefined) return

    const marker = L.marker([point.lat, point.lng], {
      riseOnHover: true,
      icon: markerIcon(point, setup),
    })
    marker.on('add', () =>
      marker.getElement()?.setAttribute('aria-label', point.title),
    )
    marker.on('keypress', (event) => {
      if (!['Enter', ' '].includes(event.originalEvent.key)) return
      event.originalEvent.preventDefault()
      marker.getElement()?.querySelector('.bazar-entry')?.click()
    })
    if (setup.display !== 'modal') {
      marker.on('click', (event) => {
        event.originalEvent?.preventDefault()
        openEntry(container, setup, point, marker)
      })
    }
    markers.push(marker)
  })

  if (setup.cluster && L.markerClusterGroup) {
    const cluster = L.markerClusterGroup()
    cluster.addLayers(markers)
    map.addLayer(cluster)
  } else {
    markers.forEach((marker) => marker.addTo(map))
  }
}

ywInitEach('.yw-entry-map', (container) => {
  const setup = JSON.parse(
    container.querySelector('.yw-entry-map__data').textContent,
  )
  const listId = container.dataset.list
  const previous = maps.get(listId)
  if (previous && !previous.getContainer().isConnected) previous.remove()

  const map = L.map(container.querySelector('.yw-entry-map__canvas'), {
    scrollWheelZoom: setup.scrollWheelZoom,
    zoomControl: setup.zoomControl,
    fullscreenControl: setup.fullscreen,
    fullscreenControlOptions: {
      title: setup.labels.fullscreen,
      titleCancel: setup.labels.normalView,
      forceSeparateButton: true,
    },
    maxZoom: 18,
  })
  maps.set(listId, map)
  const view = views.get(listId)
  map.setView(view ? view.center : setup.center, view ? view.zoom : setup.zoom)
  map.on('moveend', () =>
    views.set(listId, { center: map.getCenter(), zoom: map.getZoom() }),
  )
  map.on('click', () => closeSide(container))

  container.classList.toggle('small-width', container.offsetWidth < 800)
  container.classList.toggle('small-height', container.offsetHeight < 600)
  container
    .querySelector('.yw-entry-map__close')
    .addEventListener('click', () => closeSide(container))

  addLayers(map, setup)
  addPoints(map, container, setup)
})
