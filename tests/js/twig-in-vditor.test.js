// Unit tests for javascripts/twig-in-vditor.js: Twig has to come out of the wysiwyg editor as it went in.

import { test } from 'node:test'
import assert from 'node:assert'

const { maskTwig, unmaskTwig } =
  await import('../../javascripts/twig-in-vditor.js')

test('a masked expression holds nothing Lute would read', () => {
  const masked = maskTwig('## {{ html.bf_titre|raw }}')
  assert.ok(!/[{}|]/.test(masked), masked)
})

test('Twig comes back unchanged', () => {
  const source = [
    '## {{ html.bf_titre|raw }} et {{ formFields.bf_lieu.label }}',
    '{% if entry.a|length > 1 %}',
    '| {{ html.a|raw }} | {{ entry.a }} |',
    '{% endif %}',
  ].join('\n')
  assert.strictEqual(unmaskTwig(maskTwig(source)), source)
})

test('a wiki action stays what the components editor recognises', () => {
  const call = '{{button link="Contact" text="Écrire"}}'
  assert.strictEqual(maskTwig(call), call)
  assert.strictEqual(maskTwig('{{end elem="grid"}}'), '{{end elem="grid"}}')
})
