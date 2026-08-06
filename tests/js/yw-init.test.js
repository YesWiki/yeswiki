// Unit tests for javascripts/yw-init.js — the one initialisation convention (ticket 14).
//
// Run with `make test-js` (Node's built-in runner, no dependency, no browser).
//
// These exist because of a real outage: ywInitEach() used to record "already initialised" in
// a shared `data-yw-ready` attribute on the element. Fourteen files legitimately initialise
// <body> — page-level setup that used to hang off DOMContentLoaded — so the first one to run
// marked <body> and silently disabled all thirteen others. The editor never started (an edit
// page that looked empty) and dynamic bazar templates never rendered. Nothing in the PHP
// suite could see it, and a linter cannot either.

import { test, beforeEach } from 'node:test'
import assert from 'node:assert'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const here = path.dirname(fileURLToPath(import.meta.url))
const SOURCE = fs.readFileSync(
  path.join(here, '..', '..', 'javascripts', 'yw-init.js'),
  'utf8',
)

/**
 * A DOM stub faithful enough for this file: attributes, `matches` including the
 * `:not([attr])` form the old implementation relied on, and event listeners.
 */
function makeDom() {
  const listeners = {}

  const makeElement = (tag) => ({
    tag,
    attrs: {},
    hasAttribute(name) {
      return name in this.attrs
    },
    setAttribute(name, value) {
      this.attrs[name] = value
    },
    matches(selector) {
      const negated = selector.match(/^([a-z-]+):not\(\[([a-z-]+)\]\)$/)
      if (negated)
        return this.tag === negated[1] && !this.hasAttribute(negated[2])
      return this.tag === selector
    },
  })

  const body = makeElement('body')

  const document = {
    readyState: 'complete',
    body,
    addEventListener(name, fn) {
      listeners[name] = listeners[name] || []
      listeners[name].push(fn)
    },
    querySelectorAll(selector) {
      return [body].filter((element) => element.matches(selector))
    },
  }

  return {
    document,
    body,
    fire: (name, event) => (listeners[name] || []).forEach((fn) => fn(event)),
  }
}

let dom
let ywInit
let ywInitEach

beforeEach(() => {
  dom = makeDom()
  const scope = { document: dom.document, console }

  const load = new Function(
    'document',
    'console',
    `${SOURCE}; return { ywInit, ywInitEach }`,
  )
  const exported = load(scope.document, scope.console)
  ywInit = exported.ywInit
  ywInitEach = exported.ywInitEach
})

test('every initialiser for the same element runs, not just the first', () => {
  const ran = []
  ywInitEach('body', () => ran.push('aceditor'))
  ywInitEach('body', () => ran.push('entries-index-dynamic'))
  ywInitEach('body', () => ran.push('bazar'))

  assert.deepStrictEqual(ran, ['aceditor', 'entries-index-dynamic', 'bazar'])
})

test('an initialiser runs once per element, however many times it is swept', () => {
  const ran = []
  ywInitEach('body', () => ran.push('once'))

  dom.fire('htmx:load', { target: dom.document })
  dom.fire('htmx:load', { target: dom.document })

  assert.deepStrictEqual(
    ran,
    ['once'],
    'body survives a navigation, so its setup must not repeat',
  )
})

test('ywInit sweeps immediately and on every htmx:load', () => {
  const roots = []
  ywInit((root) => roots.push(root))

  assert.strictEqual(
    roots.length,
    1,
    'the immediate sweep is what a fragment-loaded script relies on',
  )

  dom.fire('htmx:load', { target: dom.body })
  assert.strictEqual(roots.length, 2)
  assert.strictEqual(
    roots[1],
    dom.body,
    'a swap passes the inserted element, not the document',
  )
})

/**
 * The failure that made browser tests worth building. A fragment's scripts load
 * asynchronously, so an initialiser can run before the vendor library it needs — the editor
 * threw "Vditor is not defined". Marking the element done *before* calling init meant that
 * one failed attempt was final and the editor stayed dead.
 */
test('an element whose initialiser threw is retried, not written off', () => {
  let attempts = 0
  ywInitEach('body', () => {
    attempts += 1
    if (attempts === 1) throw new Error('Vditor is not defined')
  })

  assert.strictEqual(attempts, 1, 'the first attempt runs and fails')

  dom.fire('yw:assets-ready', {})
  assert.strictEqual(
    attempts,
    2,
    'the dependency arrived, so it must try again',
  )

  dom.fire('yw:assets-ready', {})
  assert.strictEqual(attempts, 2, 'once it succeeds it must not run again')
})

test('an initialiser that throws does not stop the others', () => {
  const ran = []
  ywInit(() => {
    throw new Error('boom')
  })
  ywInit(() => ran.push('survivor'))

  assert.deepStrictEqual(ran, ['survivor'])
})
