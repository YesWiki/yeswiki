// Unit tests for the attach settings shown for a file named by its tag, without an extension.

import { test } from 'node:test'
import assert from 'node:assert'

globalThis.Vue = { reactive: (object) => object }
globalThis.wiki = { url: (path) => `/?${path}` }
let requests = 0
globalThis.fetch = async () => {
  requests += 1

  return {
    ok: true,
    json: async () => [
      {
        tag: 'schema-parcours-nov26',
        original_filename: 'schéma_parcours-nov26.pdf',
        extension: 'pdf',
      },
      { tag: 'photo-sans-nom', original_filename: 'photo', extension: 'jpg' },
    ],
  }
}

const { fileNamesReady, fileNameFor } =
  await import('../../javascripts/services/file-names.js')
const { default: InputHelper } =
  await import('../../javascripts/components/InputHelper.js')

const shown = (file, showif) =>
  InputHelper.methods.checkConfigDisplay.call(
    { values: { file }, specialValues: {} },
    { showif },
  )

test('a file with an extension needs no request', async () => {
  await fileNamesReady('attach file="rapport.pdf"')
  assert.strictEqual(requests, 0)
  assert.strictEqual(fileNameFor('rapport.pdf'), 'rapport.pdf')
})

test('a PDF named by its tag gets the PDF settings, not the picture ones', async () => {
  await fileNamesReady('attach file="schema-parcours-nov26" displaypdf="1"')
  assert.strictEqual(shown('schema-parcours-nov26', { file: '\\.pdf$' }), true)
  assert.strictEqual(
    shown('schema-parcours-nov26', { file: '\\.(png|jpg|jpeg|gif|svg|webp)$' }),
    false,
  )
})

test('an original name without its extension gets it from the entry', () => {
  assert.strictEqual(fileNameFor('photo-sans-nom'), 'photo.jpg')
})

test('the file entries are fetched once', async () => {
  await fileNamesReady('attach file="autre-tag"')
  assert.strictEqual(requests, 1)
})
