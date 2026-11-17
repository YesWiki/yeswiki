// The colour a list value is drawn in: a stated mapping, the list's own colour, or a palette slot hashed from its id, as EntryDisplay::categoryColorFor() does in PHP.
export const CATEGORY_COUNT = 8

/** FNV-1a over the UTF-8 bytes of a value id, unsigned 32 bits. */
export function categoryHash(id) {
  let hash = 0x811c9dc5
  for (const byte of new TextEncoder().encode(String(id))) {
    hash = Math.imul(hash ^ byte, 0x01000193) >>> 0
  }
  return hash
}

/** The palette token a value id always lands on. */
export function categoryColour(id) {
  return `var(--yw-category-${(categoryHash(id) % CATEGORY_COUNT) + 1})`
}

/** The first value's colour by mapping, list colour, then palette; with no value, a single stated colour or null. */
export function colourForValues(values, { mapping, nodeColours } = {}) {
  const single = typeof mapping === 'string' ? mapping.trim() : ''
  const first = values.find((value) => value !== '')
  if (first === undefined) return single || null
  const mapped = mapping && typeof mapping === 'object' ? mapping[first] : null
  if (typeof mapped === 'string' && mapped.trim() !== '') return mapped.trim()
  const stated = nodeColours?.[first]
  if (typeof stated === 'string' && stated.trim() !== '') return stated.trim()
  return categoryColour(first)
}
