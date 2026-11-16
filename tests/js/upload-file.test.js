// Unit tests for the markup a pasted, dropped or picked file is inserted as (end-users 11), and for what counts as a pasted file.

import { test } from 'node:test'
import assert from 'node:assert'

globalThis._t = (key, replacements = {}) =>
  Object.entries(replacements).reduce(
    (text, [name, value]) => text.replaceAll(`%{${name}}`, value),
    key,
  )
globalThis.wiki = { pageTag: 'PagePrincipale', url: (path) => `/?${path}` }

const { attachCode, attachMarkdown } = await import(
  '../../javascripts/upload-file.js'
)
const { pastedFiles, droppedFiles, dragsFiles } = await import(
  '../../javascripts/editor-paste-upload.js'
)
const { readableSize } = await import('../../javascripts/upload.js')

const screenshot = {
  tag: 'capture_20261116.webp',
  original_filename: 'capture.png',
  family: 'image',
}
const report = {
  tag: 'rapport_2026.pdf',
  original_filename: 'Rapport "final".pdf',
  family: 'document',
}

test('a pasted image becomes the plain attachment', () => {
  assert.strictEqual(
    attachCode(screenshot),
    '{{attach file="capture_20261116.webp" desc="capture.png"}}',
  )
})

test('the file picker options are written as before', () => {
  assert.strictEqual(
    attachCode(report, {
      desc: 'Le "rapport"',
      displayPdf: true,
      size: 'medium',
      align: 'left',
      effects: ['whiteborder', 'zoom'],
      link: 'https://example.org',
      caption: 'Légende',
      noFullImageLink: true,
    }),
    '{{attach file="rapport_2026.pdf" desc="Le &quot;rapport&quot;" displaypdf="1" size="medium" class="left whiteborder zoom" link="https://example.org" caption="Légende" nofullimagelink="1"}}',
  )
})

test('markdown shows an image and links anything else', () => {
  assert.strictEqual(
    attachMarkdown(screenshot),
    '![capture.png](/?api/files/capture_20261116.webp/download)',
  )
  assert.strictEqual(
    attachMarkdown(report, { linkText: 'Le rapport [2026]' }),
    '[Le rapport \\[2026\\]](/?api/files/rapport_2026.pdf/download)',
  )
})

const clipboard = (text, files) => ({
  getData: (type) => (type === 'text/plain' ? text : ''),
  files,
})

test('a paste with text stays a paste of text', () => {
  const picture = { name: 'cell.png' }
  assert.deepStrictEqual(pastedFiles(clipboard('', [picture])), [picture])
  assert.deepStrictEqual(
    pastedFiles(clipboard('12,5\t13', [picture])),
    [],
    'a spreadsheet cell comes with a picture of itself',
  )
  assert.deepStrictEqual(pastedFiles(clipboard('du texte', [])), [])
  assert.deepStrictEqual(pastedFiles(null), [])
})

test('a drop is ours only when it holds files', () => {
  assert.ok(dragsFiles({ types: ['Files'] }))
  assert.ok(!dragsFiles({ types: ['text/plain'] }))
  assert.deepStrictEqual(droppedFiles({ files: [report] }), [report])
})

test('sizes read as people read them', () => {
  assert.strictEqual(readableSize(840), '840 B')
  assert.strictEqual(readableSize(12.4 * 1024 * 1024), '12.4 MB')
})
