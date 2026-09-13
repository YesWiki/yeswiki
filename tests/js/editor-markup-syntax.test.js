// Unit tests for javascripts/editor-markup-syntax.js -- the one list both editor toolbars are
// built from (the source editor's template fills its menu from it, the wysiwyg one its Vditor
// toolbar), so an entry that only one of them can carry out is the bug these exist to catch.

import { test } from 'node:test'
import assert from 'node:assert'

globalThis._t = (key) => key
globalThis.navigator ??= { platform: 'Linux x86_64' }

const { FORMAT_MENU, INLINE_TOOLS, wysiwygMarkupItems } =
  await import('../../javascripts/editor-markup-syntax.js')

const entries = [
  ...FORMAT_MENU.map((group) => group.entries),
  ...INLINE_TOOLS,
].flat()

test('every entry says what it writes, in both editors', () => {
  for (const entry of entries) {
    assert.ok(entry.label(), `${entry.name} has a label`)
    assert.ok(
      entry.callout || (entry.wiki && entry.wiki.length === 2),
      `${entry.name} tells the source editor what to write`,
    )
    assert.deepStrictEqual(
      Object.keys(entry.wysiwyg).length,
      1,
      `${entry.name} tells the wysiwyg editor one way to write it`,
    )
  }
})

test('a shortcut is written the way both editors read it', async () => {
  const { matchesHotkey, hotkeyTip } =
    await import('../../javascripts/editor-markup-syntax.js')
  const press = (key, code, modifiers = {}) => ({
    key,
    code,
    ctrlKey: false,
    altKey: false,
    shiftKey: false,
    ...modifiers,
  })

  assert.ok(matchesHotkey(press('b', 'KeyB', { ctrlKey: true }), '⌘B'))
  assert.ok(
    !matchesHotkey(press('b', 'KeyB', { ctrlKey: true, shiftKey: true }), '⌘B'),
  )
  assert.ok(
    matchesHotkey(press('3', 'Digit3', { ctrlKey: true, altKey: true }), '⌥⌘3'),
  )
  assert.ok(
    matchesHotkey(press('"', 'Digit3', { ctrlKey: true, altKey: true }), '⌥⌘3'),
    'a layout that prints something else on that key still matches',
  )
  assert.deepStrictEqual(hotkeyTip('⌥⌘3'), 'Alt+Ctrl+3')
  assert.deepStrictEqual(hotkeyTip('⇧⌘H'), 'Ctrl+Shift+H')
})

test('no two entries answer to the same name', () => {
  const names = entries.map((entry) => entry.name)
  assert.deepStrictEqual(names.length, new Set(names).size)
})

test('the wysiwyg toolbar offers the same entries, in the same order', () => {
  const actions = { heading() {}, wrap() {}, surround() {}, callout() {} }
  const items = wysiwygMarkupItems(actions)
  const menu = items[0]

  assert.deepStrictEqual(menu.name, 'yw-format')
  assert.deepStrictEqual(
    menu.toolbar.filter((item) => item !== '|').length,
    FORMAT_MENU.flatMap((group) => group.entries).length,
  )
  assert.deepStrictEqual(
    items.slice(1).filter((item) => item !== '|').length,
    INLINE_TOOLS.flat().length,
  )
})

test('an entry Vditor has no command for is carried out by the editor itself', () => {
  const called = []
  const actions = {
    heading: (level) => called.push(`heading ${level}`),
    wrap: (open) => called.push(`wrap ${open}`),
    surround: (open) => called.push(`surround ${open}`),
    callout: (type) => called.push(`callout ${type}`),
  }
  const menu = wysiwygMarkupItems(actions)[0].toolbar
  const click = (name) => menu.find((item) => item.name === name).click()

  click('yw-title3')
  click('yw-center')
  click('yw-comment')
  click('yw-danger')

  assert.deepStrictEqual(called, [
    'heading 3',
    'wrap <center>',
    'surround {# ',
    'callout danger',
  ])
})
