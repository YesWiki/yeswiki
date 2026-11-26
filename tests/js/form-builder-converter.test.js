import { test } from 'node:test'
import assert from 'node:assert'

globalThis.window = globalThis.window || {}
globalThis.wiki = globalThis.wiki || { url: (path) => path }
globalThis._t = (key) => key

const { parseFields, serializeFields } =
  await import('../../javascripts/form-builder/converter.js')
const { default: registry, paletteEntries } =
  await import('../../javascripts/form-builder/registry.js')

test('parseFields and serializeFields preserve column width and new_row', () => {
  const input = JSON.stringify([
    {
      type: 'texte',
      name: 'bf_titre',
      label: 'Titre',
      width: '6',
      new_row: '1',
    },
    {
      type: 'texte',
      name: 'bf_soustitre',
      label: 'Sous-titre',
    },
  ])

  const parsed = parseFields(input, registry)
  assert.strictEqual(parsed.length, 2)
  assert.strictEqual(parsed[0].data.width, '6')
  assert.strictEqual(parsed[0].data.new_row, '1')
  assert.strictEqual(parsed[1].data.width, undefined)

  const roundTripped = JSON.parse(serializeFields(parsed, registry))
  assert.strictEqual(roundTripped[0].width, '6')
  assert.strictEqual(roundTripped[0].new_row, '1')
  assert.strictEqual(roundTripped[1].width, undefined)
})

test('parseFields and serializeFields round-trip independent columns markers', () => {
  const input = JSON.stringify([
    { type: 'columns', layout: '4-8' },
    { type: 'texte', name: 'bf_a', label: 'A' },
    { type: 'columnchange' },
    { type: 'textelong', name: 'bf_bio', label: 'Bio' },
    { type: 'columnsend' },
  ])

  const parsed = parseFields(input, registry)
  assert.deepStrictEqual(
    parsed.map((item) => item.type),
    ['columns', 'text', 'columnchange', 'textarea', 'columnsend'],
  )
  assert.strictEqual(parsed[0].data.layout, '4-8')

  const roundTripped = JSON.parse(serializeFields(parsed, registry))
  assert.deepStrictEqual(
    roundTripped.map((item) => item.type),
    ['columns', 'texte', 'columnchange', 'textelong', 'columnsend'],
  )
  assert.strictEqual(roundTripped[0].layout, '4-8')
})

test('palette exposes columns set without columnchange or columnsend clutter', () => {
  const paletteTypes = paletteEntries.map((entry) => entry.type)
  assert.ok(paletteTypes.includes('columns'))
  assert.ok(!paletteTypes.includes('columnchange'))
  assert.ok(!paletteTypes.includes('columnsend'))
})
