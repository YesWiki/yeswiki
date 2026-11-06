// Unit tests for javascripts/field-picker.js: what the "insert a field" menu writes into a template.

import { test } from 'node:test'
import assert from 'node:assert'

globalThis._t = (key) => key

const { twigInsertions, vueInsertions, withNecessaryField } =
  await import('../../javascripts/field-picker.js')

const EXTENDS = '{% extends "@core/entries/index-dynamic.twig" %}\n'

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

test('a list template only reads a short field as text, and asks for it to be loaded', () => {
  const [label, value, ...rest] = vueInsertions({
    key: 'bf_a',
    label: 'A & <b>',
    html: false,
  })
  assert.strictEqual(label.text, 'A &amp; &lt;b&gt;')
  assert.strictEqual(value.text, '<span v-text="entry.bf_a"></span>')
  assert.strictEqual(value.needs, 'bf_a')
  assert.deepStrictEqual(rest, [])
})

test('long text can be read as HTML', () => {
  const texts = vueInsertions({ key: 'bf_a', label: 'A', html: true }).map(
    ({ text }) => text,
  )
  assert.ok(texts.includes('<span v-html="entry.bf_a"></span>'))
})

test('a literal necessary_fields grows by one, once', () => {
  const source = `${EXTENDS}{% set necessary_fields = ['bf_a'] %}\n`
  const grown = withNecessaryField(source, 'bf_b')
  assert.ok(grown.includes("['bf_a', 'bf_b']"))
  assert.strictEqual(withNecessaryField(grown, 'bf_b'), grown)
})

test('a template with no necessary_fields gets one after its extends', () => {
  const grown = withNecessaryField(`${EXTENDS}<div></div>\n`, 'bf_a')
  assert.ok(grown.indexOf('necessary_fields') > grown.indexOf('extends'))
  assert.ok(grown.indexOf('necessary_fields') < grown.indexOf('<div>'))
})

test('a computed necessary_fields or a partial is left to the author', () => {
  const merged = `${EXTENDS}{% set necessary_fields = (x|default([]))|merge(['a']) %}\n`
  assert.strictEqual(withNecessaryField(merged, 'bf_a'), null)
  assert.strictEqual(withNecessaryField('<div></div>\n', 'bf_a'), null)
})
