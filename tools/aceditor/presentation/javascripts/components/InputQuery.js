import InputHelper from './InputHelper.js'
import QueryGroup from './QueryGroup.js'

function isGroup(node) {
  return (
    node !== null && typeof node === 'object' && Array.isArray(node.children)
  )
}

function tokenizeQuery(query) {
  const tokens = []
  let buffer = ''
  let bracketDepth = 0
  const flushLeaf = () => {
    if (buffer.trim() !== '')
      tokens.push({ type: 'leaf', value: buffer.trim() })
    buffer = ''
  }
  for (let i = 0; i < query.length; i++) {
    const char = query[i]
    if (char === '[') {
      bracketDepth++
      buffer += char
      continue
    }
    if (char === ']') {
      if (bracketDepth > 0) bracketDepth--
      buffer += char
      continue
    }
    if (bracketDepth === 0) {
      if (char === '|' || char === ',') return null
      if (char === '(') {
        flushLeaf()
        tokens.push({ type: '(' })
        continue
      }
      if (char === ')') {
        flushLeaf()
        tokens.push({ type: ')' })
        continue
      }
      if (query.startsWith(' AND ', i)) {
        flushLeaf()
        tokens.push({ type: 'AND' })
        i += 4
        continue
      }
      if (query.startsWith(' OR ', i)) {
        flushLeaf()
        tokens.push({ type: 'OR' })
        i += 3
        continue
      }
    }
    buffer += char
  }
  flushLeaf()
  return tokens
}

function parseLeaf(value) {
  const match = value.match(/^\s*([^=!<>]*?)\s*(==|!=|<=|>=|=|<|>)(.*)$/)
  if (!match) return null
  return {
    field: match[1].trim(),
    operator: match[2] === '==' ? '=' : match[2],
    value: match[3].trim(),
  }
}

function parseTokens(tokens) {
  let pos = 0
  const peek = () => tokens[pos]
  let failed = false

  const parseTerm = () => {
    const token = peek()
    if (!token) {
      failed = true
      return null
    }
    if (token.type === '(') {
      pos++
      const expression = parseOr()
      if (!peek() || peek().type !== ')') {
        failed = true
        return null
      }
      pos++
      return expression
    }
    if (token.type === 'leaf') {
      pos++
      const leaf = parseLeaf(token.value)
      if (leaf === null) failed = true
      return leaf
    }
    failed = true
    return null
  }

  const parseAnd = () => {
    const nodes = [parseTerm()]
    while (!failed && peek() && peek().type === 'AND') {
      pos++
      nodes.push(parseTerm())
    }
    return nodes.length === 1 ? nodes[0] : { connector: 'AND', children: nodes }
  }

  const parseOr = () => {
    const nodes = [parseAnd()]
    while (!failed && peek() && peek().type === 'OR') {
      pos++
      nodes.push(parseAnd())
    }
    return nodes.length === 1 ? nodes[0] : { connector: 'OR', children: nodes }
  }

  const root = parseOr()
  if (failed || pos !== tokens.length) return null
  return root
}

export function convertLegacyQuery(query) {
  const trimmed = (query ?? '').trim()
  if (trimmed === '' || / (AND|OR) /.test(trimmed) || trimmed.includes('(')) {
    return trimmed
  }
  const fragments = []
  for (const fragment of trimmed.split('|')) {
    if (fragment.trim() === '') continue
    const match = fragment.match(
      /^\s*([^=!<>]*?)\s*(==|!=|<=|>=|=|<|>)([\s\S]*)$/,
    )
    if (!match) {
      fragments.push(fragment.trim())
      continue
    }
    const name = match[1].trim()
    const operator = match[2]
    const rest = match[3].trim()
    const values = rest.split(',').map((value) => value.trim())
    if (values.length <= 1) {
      fragments.push(name + operator + rest)
      continue
    }
    const glue = operator === '!=' ? ' AND ' : ' OR '
    fragments.push(
      '(' + values.map((value) => name + operator + value).join(glue) + ')',
    )
  }
  return fragments.join(' AND ')
}

export function parseQuery(query) {
  const trimmed = convertLegacyQuery(query)
  if (trimmed === '')
    return { ok: true, root: { connector: 'AND', children: [] } }
  const tokens = tokenizeQuery(trimmed)
  if (tokens === null) return { ok: false }
  const parsed = parseTokens(tokens)
  if (parsed === null) return { ok: false }
  const root = isGroup(parsed)
    ? parsed
    : { connector: 'AND', children: [parsed] }
  return { ok: true, root }
}

function serializeLeaf(node) {
  if (!node.field || !node.operator) return ''
  return `${node.field}${node.operator}${node.value ?? ''}`
}

function serializeChild(node) {
  if (isGroup(node)) {
    const parts = node.children
      .map(serializeChild)
      .filter((part) => part !== '')
    if (parts.length <= 1) {
      return parts[0] ?? ''
    }
    return `(${parts.join(` ${node.connector} `)})`
  }
  return serializeLeaf(node)
}

export function buildQuery(root) {
  const parts = root.children.map(serializeChild).filter((part) => part !== '')
  return parts.join(` ${root.connector} `)
}

export default {
  mixins: [InputHelper],
  components: { QueryGroup },
  props: ['name', 'value', 'config', 'selectedForms', 'values'],
  emits: ['input'],
  data() {
    return { root: { connector: 'AND', children: [] }, raw: '', rawMode: false }
  },
  mounted() {
    this.parseNewValues(this.values)
  },
  methods: {
    resetValues() {
      this.root = { connector: 'AND', children: [] }
      this.raw = ''
      this.rawMode = false
    },
    parseNewValues(newValues) {
      const parsed = parseQuery(newValues ? newValues.query : '')
      if (parsed.ok) {
        this.root = parsed.root
        this.rawMode = false
      } else {
        this.raw = (newValues.query ?? '').trim()
        this.rawMode = true
      }
    },
    showVisual() {
      const parsed = parseQuery(this.raw)
      if (parsed.ok) {
        this.root = parsed.root
        this.rawMode = false
      }
    },
    showRaw() {
      this.raw = buildQuery(this.root)
      this.rawMode = true
    },
    getValues() {
      return {
        query: this.rawMode ? this.raw.trim() : buildQuery(this.root),
      }
    },
  },
  watch: {
    root: {
      handler() {
        this.$emit('input', null)
      },
      deep: true,
    },
    raw() {
      this.$emit('input', null)
    },
  },
  template: `
    <div class="query-builder" :class="name">
      <div class="query-builder__tabs">
        <button type="button" class="query-builder__tab" :class="{ 'is-active': !rawMode }" @click="showVisual">
          {{ _t('ACTION_BUILDER_QUERY_EDIT_VISUAL') }}
        </button>
        <button type="button" class="query-builder__tab" :class="{ 'is-active': rawMode }" @click="showRaw">
          {{ _t('ACTION_BUILDER_QUERY_CODE') }}
        </button>
      </div>
      <query-group v-if="!rawMode" :group="root" :selected-forms="selectedForms"
                   :values="values" :depth="0"></query-group>
      <div v-else class="query-builder__raw">
        <textarea class="form-control qb-input" v-model="raw" rows="3"></textarea>
      </div>
    </div>`,
}
