/** Twig constructs kept out of Lute's reach: it reads `{...}` in a heading as an id and `|` in a table as a cell border. */
const OPEN = '⦃'
const CLOSE = '⦄'
const PIPE = '¦'

const TWIG = /\{\{[\s\S]*?\}\}|\{%[\s\S]*?%\}/g
const WIKI_CALL = /^\{\{[a-zA-Z0-9_-]+(?:\s+[a-zA-Z0-9_-]+="[^"]*")*\s*\/?\}\}$/
const MASKED = new RegExp(`${OPEN}[^${CLOSE}]*${CLOSE}`, 'g')

/** The text with each Twig construct, wiki action calls aside, written in characters Lute leaves alone. */
export function maskTwig(text) {
  return String(text).replace(TWIG, (construct) => {
    if (WIKI_CALL.test(construct)) return construct
    const percent = construct.startsWith('{%') ? '%' : ''
    const inner = construct.slice(2, -2).replaceAll('|', PIPE)
    return `${OPEN}${percent}${inner}${percent}${CLOSE}`
  })
}

/** The reverse of maskTwig. */
export function unmaskTwig(text) {
  return String(text).replace(MASKED, (masked) => {
    const inner = masked.slice(1, -1).replaceAll(PIPE, '|')
    return inner.startsWith('%') ? `{%${inner.slice(1, -1)}%}` : `{{${inner}}}`
  })
}
