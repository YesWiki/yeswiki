// Unit tests for javascripts/category-colour.js, the marker colours of end-users 30; the hashes match EntryDisplay::categoryColorFor().
import { test } from 'node:test'
import assert from 'node:assert'
import {
  categoryHash,
  categoryColour,
  colourForValues,
} from '../../javascripts/category-colour.js'

test('the hash is the one PHP computes', () => {
  assert.strictEqual(categoryHash('1'), 873244444)
  assert.strictEqual(categoryHash('val1'), 2227457591)
  assert.strictEqual(categoryHash('é'), 513665217)
  assert.strictEqual(categoryColour('sol'), 'var(--yw-category-2)')
})

test('five uncoloured values get five colours', () => {
  const colours = ['sol', 'air', 'feu', 'bois', 'pierre'].map((value) =>
    colourForValues([value]),
  )
  assert.strictEqual(new Set(colours).size, 5)
})

test('a mapping wins over the list colour, which wins over the palette', () => {
  const nodeColours = { eau: '#1e6fd9' }
  assert.strictEqual(colourForValues(['eau'], { nodeColours }), '#1e6fd9')
  assert.strictEqual(
    colourForValues(['eau'], { mapping: { eau: 'red' }, nodeColours }),
    'red',
  )
  assert.strictEqual(
    colourForValues(['sol'], { mapping: { eau: 'red' }, nodeColours }),
    categoryColour('sol'),
  )
})

test('no value gives a single stated colour or nothing, never undefined', () => {
  assert.strictEqual(colourForValues([]), null)
  assert.strictEqual(colourForValues([''], { mapping: null }), null)
  assert.strictEqual(colourForValues([], { mapping: 'green' }), 'green')
  assert.strictEqual(
    colourForValues(['sol'], { mapping: 'green' }),
    categoryColour('sol'),
  )
})
