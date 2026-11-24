// Unit tests for javascripts/field-picker.js: what the "insert a field" menu writes into a template.

import { test } from 'node:test'
import assert from 'node:assert'

globalThis._t = (key) => key

const { twigInsertions, listInsertions } =
  await import('../../javascripts/field-picker.js')

test('an entry template gets the label, the rendered value and the stored one', () => {
  const texts = twigInsertions({ name: 'bf_a', key: 'bf_a', stored: true }).map(
    ({ text }) => text,
  )
  assert.deepStrictEqual(texts, [
    '{{ formFields.bf_a.label }}',
    '{{ html.bf_a|raw }}',
    '{{ entry.bf_a }}',
  ])
})

test('a linked entries field has no stored value to offer', () => {
  const texts = twigInsertions({
    name: 'x',
    key: 'listefichesx',
    stored: false,
  }).map(({ text }) => text)
  assert.deepStrictEqual(texts, [
    '{{ formFields.x.label }}',
    '{{ html.listefichesx|raw }}',
  ])
})

test('a list template gets the label, the field as the entry shows it, and the stored value', () => {
  const [label, rendered, raw, ...rest] = listInsertions({
    name: 'bf_a',
    key: 'bf_a',
    label: 'A & <b>',
    stored: true,
  })
  assert.strictEqual(label.text, 'A &amp; &lt;b&gt;')
  assert.strictEqual(rendered.text, "{{ entryField(item.entry, 'bf_a') }}")
  assert.strictEqual(raw.text, '{{ item.entry.bf_a }}')
  assert.deepStrictEqual(rest, [])
})

test('a linked entries field is offered rendered only', () => {
  const texts = listInsertions({
    name: 'x',
    key: 'listefichesx',
    label: 'X',
    stored: false,
  }).map(({ text }) => text)
  assert.deepStrictEqual(texts, ['X', "{{ entryField(item.entry, 'x') }}"])
})
