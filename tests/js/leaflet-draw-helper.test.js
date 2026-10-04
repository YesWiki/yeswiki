// Unit tests for javascripts/leaflet-draw.helper.js: geometries survive a load/save round trip.
// Leaflet is replaced by a stub that models only what the helper touches.

import { test, beforeEach } from 'node:test'
import assert from 'node:assert'
import {
  drawGeometries,
  drawnItemsToGeoJSON,
} from '../../javascripts/leaflet-draw.helper.js'

class Layer {
  constructor(options = {}) {
    this.options = options
  }

  on() {
    return this
  }

  bindPopup() {
    return this
  }

  toGeoJSON() {
    return this.feature
  }
}

class Circle extends Layer {
  constructor(latlng, options) {
    super(options)
    this.latlng = latlng
  }

  getLatLng() {
    return this.latlng
  }

  getRadius() {
    return this.options.radius
  }
}

class GeoJSONGroup extends Layer {
  constructor(feature) {
    super()
    this.feature = feature
  }

  toGeoJSON() {
    return { type: 'FeatureCollection', features: [this.feature] }
  }
}

function makeGroup() {
  const layers = []
  return {
    layers,
    addLayer(layer) {
      layers.push(layer)
      return this
    },
    eachLayer(fn) {
      layers.forEach(fn)
    },
  }
}

beforeEach(() => {
  globalThis.L = {
    Circle,
    latLng: (lat, lng) => ({ lat, lng }),
    circle: (latlng, options) => new Circle(latlng, options),
    marker: (latlng, options) => new Layer(options),
    Icon: { Default: { extend: () => class {} } },
    geoJSON(feature, options) {
      if (feature.geometry.type === 'Broken') throw new Error('bad geometry')
      const sub = new Layer()
      sub.feature = feature
      options.onEachFeature(feature, sub)
      return new GeoJSONGroup(feature)
    },
  }
})

const polygon = {
  type: 'Feature',
  properties: {},
  geometry: {
    type: 'Polygon',
    coordinates: [
      [
        [0, 0],
        [1, 0],
        [1, 1],
        [0, 0],
      ],
    ],
  },
}

const circle = {
  type: 'Feature',
  properties: { type: 'circle', radius: 120, className: 'zone' },
  geometry: { type: 'Point', coordinates: [2.5, 48.8] },
}

test('a polygon is added once, as its own layer', () => {
  const group = drawGeometries(makeGroup(), [polygon], '', 'Entry')
  assert.strictEqual(group.layers.length, 1)
  assert.strictEqual(group.layers[0].tag, 'Entry')
})

test('saving what was loaded gives back the same flat features', () => {
  const group = drawGeometries(makeGroup(), [polygon, circle])
  const saved = drawnItemsToGeoJSON(group)
  assert.strictEqual(saved.features.length, 2)
  assert.deepStrictEqual(saved.features[0], polygon)
  assert.deepStrictEqual(saved.features[1].properties, circle.properties)
  assert.deepStrictEqual(saved.features[1].geometry, circle.geometry)
})

test('a nested FeatureCollection is flattened on save', () => {
  const group = makeGroup()
  group.addLayer(new GeoJSONGroup(polygon))
  assert.deepStrictEqual(drawnItemsToGeoJSON(group).features, [polygon])
})

test('a resized circle keeps its new radius and type', () => {
  const group = drawGeometries(makeGroup(), [circle])
  group.layers[0].options.radius = 300
  group.layers[0].options.type = 'polygon'
  const props = drawnItemsToGeoJSON(group).features[0].properties
  assert.strictEqual(props.radius, 300)
  assert.strictEqual(props.type, 'circle')
})

test('a freshly drawn circle cannot override radius or type', () => {
  const group = makeGroup()
  group.addLayer(new Circle({ lat: 1, lng: 2 }, { radius: 50, type: 'x' }))
  const props = drawnItemsToGeoJSON(group).features[0].properties
  assert.strictEqual(props.type, 'circle')
  assert.strictEqual(props.radius, 50)
})

test('one broken geometry does not stop the others', () => {
  const broken = { ...polygon, geometry: { type: 'Broken' } }
  const errors = []
  const original = console.error
  console.error = (...args) => errors.push(args)
  try {
    const group = drawGeometries(makeGroup(), [broken, polygon, null, circle])
    assert.strictEqual(group.layers.length, 2)
  } finally {
    console.error = original
  }
  assert.strictEqual(errors.length, 2)
})

test('missing features leave the group untouched', () => {
  const group = makeGroup()
  assert.strictEqual(drawGeometries(group, undefined), group)
  assert.strictEqual(group.layers.length, 0)
})
