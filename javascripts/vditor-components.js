/** PROTOTYPE -- YesWiki components (`{{action ...}}`) as WYSIWYG widgets inside Vditor. */
import { legacyIconToSprite } from './yw-icon-map.js'

/** The fence a component travels in. */
export const FENCE_LANGUAGE = 'yeswiki-component'

/** ...carrying which of its neighbours it was written against, with no blank line between. */
export const FENCE_LANGUAGES = [
  FENCE_LANGUAGE,
  `${FENCE_LANGUAGE}-above`,
  `${FENCE_LANGUAGE}-below`,
  `${FENCE_LANGUAGE}-around`,
]

const fenceLanguage = (above, below) => {
  if (above && below) return FENCE_LANGUAGES[3]
  if (below) return FENCE_LANGUAGES[2]
  if (above) return FENCE_LANGUAGES[1]

  return FENCE_LANGUAGE
}

/** One tag and nothing else on the line -- the same shape the server recognises as a block (ActionBlockStartParser), tempered dot included so a line holding a *pair* of tags is not read as one enormous tag. */
const STANDALONE_TAG = /^\{\{((?:(?!\}\})[\s\S])*)\}\}[ \t]*$/

/** A fence opening or closing, so that a tag written inside one is left alone. */
const FENCE_DELIMITER = /^ {0,3}(`{3,}|~{3,})/

/** The other pair of tags that wraps ordinary prose: HedgeDoc's `:::info` ... */
const ALERT = ':::'
const ALERT_OPEN = /^:::[ \t]*(success|info|warning|danger)[ \t]*$/i
const ALERT_CLOSE = /^:::[ \t]*$/

const alertType = (line) => (ALERT_OPEN.exec(line)?.[1] || '').toLowerCase()

/** Written out, so `php src/build-js-lang-keys.php` can see each key. */
function alertLabel(type) {
  switch (type) {
    case 'success':
      return _t('ALERT_SUCCESS')
    case 'info':
      return _t('ALERT_INFO')
    case 'warning':
      return _t('ALERT_WARNING')
    case 'danger':
      return _t('ALERT_DANGER')
    default:
      return _t('ALERT')
  }
}

const actionsData = () =>
  typeof actionsBuilderData === 'object' ? actionsBuilderData : null

/** The Component that writes this tag, or null for a tag nothing declares. */
function actionConfiguration(name) {
  const data = actionsData()
  if (!data) return null
  const components = Object.values(data.components || {})

  return (
    components.find((c) => (c.tags || []).includes(name) && !c.pins) ||
    components.find((c) => (c.tags || []).includes(name)) ||
    null
  )
}

/** `{{grid}}` and `{{end elem="grid"}}` are half a component each, not two components -- and `:::info` and `:::` are the same thing written differently. */
function tagRole(tag) {
  const line = tag.trim()
  if (ALERT_OPEN.test(line)) return { name: ALERT, role: 'open' }
  if (ALERT_CLOSE.test(line))
    return { name: ALERT, role: 'close', closes: ALERT }

  const name = line
    .replace(/^\{\{/, '')
    .replace(/\}\}$/, '')
    .trim()
    .split(/\s/)[0]
  if (name === 'end')
    return { name, role: 'close', closes: closedElement(line) }

  return {
    name,
    role: actionConfiguration(name)?.isWrapper ? 'open' : 'leaf',
  }
}

/** The half that closes this one, which is what a wrapper has to be rendered with. */
function closingFor(tag) {
  const { name } = tagRole(tag)

  return name === ALERT ? ALERT : `{{end elem="${name}"}}`
}

/** A line that is a component on its own, and so travels through Vditor in a fence. */
const isComponentLine = (line) =>
  (STANDALONE_TAG.test(line) && line !== '{{}}') ||
  ALERT_OPEN.test(line) ||
  ALERT_CLOSE.test(line)

/** Wrap every standalone tag in a fence, so Vditor makes a widget of it. */
export function fenceComponents(markdown) {
  const lines = String(markdown).split('\n')
  const out = []
  let openFence = null
  let alertDepth = 0

  lines.forEach((line, index) => {
    const delimiter = FENCE_DELIMITER.exec(line)
    if (openFence) {
      if (
        delimiter &&
        delimiter[1][0] === openFence[0] &&
        delimiter[1].length >= openFence.length
      ) {
        openFence = null
      }
      out.push(line)

      return
    }
    if (delimiter) {
      openFence = delimiter[1]
      out.push(line)

      return
    }

    if (ALERT_OPEN.test(line)) {
      alertDepth += 1
    } else if (ALERT_CLOSE.test(line)) {
      if (alertDepth === 0) {
        out.push(line)

        return
      }
      alertDepth -= 1
    } else if (!isComponentLine(line)) {
      out.push(line)

      return
    }

    const above = index > 0 && lines[index - 1].trim() !== ''
    const below = index < lines.length - 1 && lines[index + 1].trim() !== ''
    out.push('```' + fenceLanguage(above, below), line, '```')
  })

  return out.join('\n')
}

/** The reverse, and deliberately narrower than the wrapping: only a fence of ours whose whole body is one tag is unwrapped. */
export function unfenceComponents(markdown) {
  const lines = String(markdown).split('\n')
  const opening = new RegExp(
    `^(\`{3,})(${[...FENCE_LANGUAGES].reverse().join('|')})[ \t]*$`,
  )
  const out = []

  for (let i = 0; i < lines.length; i += 1) {
    const fence = opening.exec(lines[i])
    const body = lines[i + 1]
    const closing = lines[i + 2]
    if (
      fence &&
      body !== undefined &&
      isComponentLine(body) &&
      closing !== undefined &&
      new RegExp('^' + fence[1] + '[ \t]*$').test(closing)
    ) {
      const attached = fence[2].slice(FENCE_LANGUAGE.length)
      if (/above|around/.test(attached) && out[out.length - 1] === '') out.pop()
      out.push(body)
      i += 2
      if (/below|around/.test(attached) && lines[i + 1] === '') i += 1
    } else {
      out.push(lines[i])
    }
  }

  return out.join('\n')
}

/** The block Lute would have built for that fence, built by hand -- so that a component inserted from the palette can be dropped straight into the document instead of going through setValue(), which would rebuild every widget on the page (and reload every preview iframe in it) for the sake of adding one. */
export function componentBlockElement(tag) {
  const block = document.createElement('div')
  block.className = 'vditor-wysiwyg__block'
  block.setAttribute('data-type', 'code-block')
  block.setAttribute('data-block', '0')
  block.setAttribute('data-marker', '```')

  const escaped = tag
    .trim()
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
  const code = `<code class="language-${FENCE_LANGUAGE}">${escaped}\n</code>`
  block.innerHTML =
    `<pre class="vditor-wysiwyg__pre" style="display: none">${code}</pre>` +
    `<pre class="vditor-wysiwyg__preview" data-render="2">${code}</pre>`

  return block
}

/** The tag a widget currently holds, read off the source half nobody renders. */
export function tagOfBlock(block) {
  return (
    block.querySelector('.vditor-wysiwyg__pre code')?.textContent || ''
  ).trim()
}

/** Rewrite the component a widget holds, in place, and re-render its preview. */
export function rewriteBlock(block, tag) {
  block.querySelectorAll('code').forEach((code) => {
    code.textContent = `${tag.trim()}\n`
  })
  const preview = block.querySelector('.vditor-wysiwyg__preview')
  preview.setAttribute('data-render', '2')
  renderComponent(preview)
  preview.setAttribute('data-render', '1')
}

const icon = (name) => legacyIconToSprite(name) || ''

const labelFor = (name) => actionConfiguration(name)?.label || name

/** Whether the settings rail has anything to say about this component. */
const isConfigurable = (name) => {
  const configuration = actionConfiguration(name)

  return Boolean(configuration && !configuration.onlyAdd)
}

/** What a `{{end elem="..."}}` closes. */
const closedElement = (tag) =>
  /elem\s*=\s*["']([^"']+)["']/.exec(tag)?.[1] || 'end'

/** Which alert a `:::` closes. */
function alertTypeClosedBy(previewElement) {
  const root = previewElement.closest('.vditor-wysiwyg') || document
  const codes = [...root.querySelectorAll('.vditor-wysiwyg__pre code')]
  const own = previewElement
    .closest('.vditor-wysiwyg__block')
    ?.querySelector('.vditor-wysiwyg__pre code')
  let depth = 0

  for (let i = codes.indexOf(own) - 1; i >= 0; i -= 1) {
    const line = codes[i].textContent.trim()
    if (ALERT_CLOSE.test(line)) depth += 1
    else if (ALERT_OPEN.test(line)) {
      if (depth === 0) return alertType(line)
      depth -= 1
    }
  }

  return ''
}

/** Whether this tag is half of a wrapper, and so has no preview of its own -- `{{grid}}` without its `{{end}}` is not a grid, it is an error message about a missing `{{end}}`. */
function componentRole(previewElement, tag) {
  const { name, role } = tagRole(tag)
  if (role !== 'leaf') return { name, role }

  const root = previewElement.closest('.vditor-wysiwyg') || document
  const closing = new RegExp(`^\\{\\{end\\s+elem\\s*=\\s*["']${name}["']`)
  const isOpened = [...root.querySelectorAll('.vditor-wysiwyg__pre code')].some(
    (code) => closing.test(code.textContent.trim()),
  )

  return { name, role: isOpened ? 'open' : 'leaf' }
}

/**
 * Fill a preview panel with a component. Registered as Vditor's `customRenders` entry for
 * the component fences, which is called on load and on every edit of the block -- never on
 * every keystroke elsewhere, since Vditor marks a rendered panel data-render="1" and skips
 * it afterwards.
 *
 * @param tag what to show, which is what the block says unless someone is being shown a
 *   change they have not made yet: the settings rail drives this panel as it is filled in,
 *   and only its button writes into the document.
 */
export function renderComponent(previewElement, pendingTag = null) {
  const source = previewElement
    .closest('.vditor-wysiwyg__block')
    ?.querySelector('.vditor-wysiwyg__pre code')?.textContent
  const tag = (pendingTag ?? source ?? previewElement.textContent ?? '').trim()
  const { name, role } = componentRole(previewElement, tag)
  const label =
    name === ALERT
      ? alertLabel(
          role === 'close' ? alertTypeClosedBy(previewElement) : alertType(tag),
        )
      : labelFor(role === 'close' ? closedElement(tag) : name)

  const widget = document.createElement('div')
  widget.className = `yw-component yw-component--${role}`
  widget.setAttribute('data-yw-component', name)
  widget.setAttribute('contenteditable', 'false')

  const roleIcon = {
    leaf: 'stack-2',
    open: 'chevron-down',
    close: 'chevron-up',
  }[role]

  const settingsButton = isConfigurable(
    role === 'close' ? closedElement(tag) : name,
  )
    ? `<button type="button" class="yw-component__btn" data-yw-component-btn="settings" title="${_t('COMPONENT_SETTINGS')}">${icon('settings')}</button>`
    : ''

  widget.innerHTML =
    `<div class="yw-component__bar">` +
    `<span class="yw-component__name">${icon(roleIcon)}${label}</span>` +
    `<span class="yw-component__buttons">` +
    settingsButton +
    `<button type="button" class="yw-component__btn" data-yw-component-btn="source" title="${_t('COMPONENT_SOURCE')}">${icon('code')}</button>` +
    `</span></div>`

  if (role === 'leaf') {
    const body = document.createElement('div')
    body.className = 'yw-component__body'
    body.innerHTML =
      `<iframe class="yw-component__frame" title="${label}" src="${wiki.url('wiki/render', { content: tag })}"></iframe>` +
      `<div class="yw-component__blocker"></div>`
    widget.appendChild(body)
    fitFrameToItsContent(body.querySelector('iframe'))
  }

  previewElement.innerHTML = ''
  previewElement.appendChild(widget)
}

/** What a wrapper looks like, painted behind what it wraps. */
const wrapperPaints = new Map()

function wrapperPaint(openTag) {
  if (!wrapperPaints.has(openTag)) {
    const content = `${openTag}\n${closingFor(openTag)}`
    wrapperPaints.set(
      openTag,
      fetch(wiki.url('wiki/render', { content }))
        .then((response) => (response.ok ? response.text() : ''))
        .then((html) => {
          const rendered = new DOMParser()
            .parseFromString(html, 'text/html')
            .querySelector('.page-widget')?.firstElementChild
          if (!rendered) return null
          rendered.innerHTML = ''

          return rendered
        })
        .catch(() => null),
    )
  }

  return wrapperPaints.get(openTag)
}

/** A wrapper the settings rail is being filled in for, shown as it is being described rather than as the document still has it -- the same "a preview is not a change" the leaf widgets get, for the one thing you cannot see by looking at a chip. */
let pending = null

export function setPendingWrapper(block, tag) {
  pending = block ? { block, tag } : null
}

const pendingTag = (block) => (pending?.block === block ? pending.tag : null)

/** The block that opens the wrapper this one closes -- which is the component a closing chip is about. */
export function openingBlockFor(content, closeBlock) {
  return (
    wrapperRegions(content).find((region) => region.closeBlock === closeBlock)
      ?.block || null
  )
}

/** The tag a block holds, or null for a block that is not a component. */
function blockTag(block) {
  if (!block.classList?.contains('vditor-wysiwyg__block')) return null
  const code = block.querySelector('.vditor-wysiwyg__pre code')
  if (!code || !/^language-yeswiki-component/.test(code.className)) return null

  return code.textContent.trim()
}

/** The wrapper regions of the document, outermost first, paired by walking the blocks with a stack -- which is what makes a `{{col}}` inside a `{{grid}}` close the col and not the grid, and an unclosed tag paint nothing rather than paint to the end of the page. */
function wrapperRegions(content) {
  const open = []
  const regions = []

  for (const block of content.children) {
    const tag = pendingTag(block) ?? blockTag(block)
    if (!tag) continue
    const { name, role, closes } = tagRole(tag)
    if (role !== 'close') {
      open.push({ name, block, tag })
      continue
    }
    const index = open.findLastIndex((candidate) => candidate.name === closes)
    if (index === -1) continue
    regions.push({ ...open[index], closeBlock: block })
    open.splice(index, 1)
  }

  regions.reverse()

  const blocks = [...content.children]
  regions.forEach((region) => {
    region.from = blocks.indexOf(region.block)
    region.to = blocks.indexOf(region.closeBlock)
  })
  regions.forEach((region) => {
    region.nested = regions.some(
      (other) =>
        other !== region && other.from < region.from && other.to > region.to,
    )
  })

  return regions
}

/** Grey out the `{# ... */
export function markComments(content) {
  let inside = false

  for (const block of content.children) {
    const text = block.textContent || ''
    let isComment = inside

    if (inside) {
      if (text.includes('#}')) inside = false
    } else if (text.includes('{#')) {
      isComment = true
      inside = !text.includes('#}', text.indexOf('{#') + 2)
    }

    block.classList.toggle('yw-comment', isComment)
  }
}

/** The markdown-extra that may follow a link, and what is inside its braces. */
export const LINK_EXTRA = /^\{([^}]*)\}/

/** A link's `{.newtab}`, drawn as a mark on the link rather than as the braces it is written with. */
export function markLinkExtras(content) {
  const caret = getSelection()?.anchorNode

  for (const anchor of content.querySelectorAll('a[href]')) {
    const next = anchor.nextSibling
    if (next?.nodeType !== Node.TEXT_NODE) continue
    const match = LINK_EXTRA.exec(next.textContent)
    if (!match || next === caret) continue

    next.splitText(match[0].length)
    const chip = document.createElement('span')
    chip.className = 'yw-link-extra'
    chip.setAttribute('contenteditable', 'false')
    chip.append(brace('{'), match[1], brace('}'))
    next.replaceWith(chip)
  }
}

function brace(character) {
  const span = document.createElement('span')
  span.className = 'yw-link-extra__brace'
  span.textContent = character

  return span
}

/** Classes a wrapper puts on its CONTENT rather than on its box -- the ink it derives from its own background travels the same way. */
const CONTENT_CLASSES = [
  'text-left',
  'text-center',
  'text-right',
  'text-justify',
  'white',
  'black',
]

function styleRegionContent(content, regions) {
  const blocks = [...content.children]
  blocks.forEach((block) => {
    block.classList.remove(...CONTENT_CLASSES)
    block.style.color = ''
  })
  const inherited = getComputedStyle(content).color

  ;[...regions].reverse().forEach((region) => {
    const written = /class\s*=\s*["']([^"']*)["']/.exec(region.tag)?.[1] ?? ''
    const classes = written
      .split(/\s+/)
      .filter((token) => CONTENT_CLASSES.includes(token))
    const ink = region.slot?.firstElementChild?.style.color ?? ''
    if (!classes.length && !ink) return

    for (let i = region.from + 1; i < region.to; i += 1) {
      const block = blocks[i]
      if (!block) continue
      block.classList.add(...classes)
      if (!ink || block.style.color) continue
      if (getComputedStyle(block).color === inherited) block.style.color = ink
    }
  })
}

/** The callout region the caret's block sits in, or null. */
export function calloutRegionAt(content, block) {
  if (!block) return null
  const blocks = [...content.children]
  const index = blocks.indexOf(block)
  if (index === -1) return null

  return (
    wrapperRegions(content).find(
      (region) =>
        tagRole(region.tag).name === ALERT &&
        region.from < index &&
        region.to > index,
    ) ?? null
  )
}

export function paintWrappers(content) {
  const layer = wrapperLayer(content)
  const regions = wrapperRegions(content)
  const signature = regions.map((region) => region.tag).join(' ')

  if (layer.dataset.signature !== signature) {
    layer.dataset.signature = signature
    layer.innerHTML = ''
    regions.forEach(() => layer.appendChild(document.createElement('div')))
  }

  regions.forEach((region, index) => {
    region.slot = layer.children[index] || null
    clearReservation(region)
    if (region.slot) paintInto(region.slot, region.tag, region.nested, content)
  })

  styleRegionContent(content, regions)

  ;[...regions].reverse().forEach(reserveDeclaredHeight)

  const layerBox = layer.getBoundingClientRect()
  regions.forEach((region) => {
    if (!region.slot) return
    region.slot.style.top = `${region.block.getBoundingClientRect().top - layerBox.top}px`
    region.slot.style.height = `${spannedHeight(region)}px`
  })
}

/** How much room a wrapper's own blocks take, from the top of its opening chip to the bottom of its closing one. */
function spannedHeight(region) {
  const from = region.block.getBoundingClientRect()
  const to = region.closeBlock.getBoundingClientRect()

  return Math.max(0, to.bottom - from.top)
}

/** Where a band's reserved height is put: a chip's own box, which no block margin collapses through. */
function spacerOf(block) {
  return block.querySelector('.vditor-wysiwyg__preview') ?? block
}

function clearReservation(region) {
  spacerOf(region.block).style.paddingBottom = ''
  spacerOf(region.closeBlock).style.paddingTop = ''
}

/** A band asking for more height than its blocks fill gets the rest reserved around them -- innermost first, so a nested band's reservation is part of what its parent has to hold. */
function reserveDeclaredHeight(region) {
  const paint = region.slot?.firstElementChild
  if (!paint) return

  const spanned = spannedHeight(region)
  region.slot.style.height = `${spanned}px`
  const wanted = paint.offsetHeight
  if (wanted - spanned <= 1) return

  reserve(region, paint, wanted - spanned)
  const overshoot = spannedHeight(region) - wanted
  if (overshoot > 1) {
    reserve(region, paint, Math.max(0, wanted - spanned - overshoot))
  }
}

/** Split as the band splits it: half above the blocks when the band centres what it holds, all under them when it does not. */
function reserve(region, paint, missing) {
  const above =
    getComputedStyle(paint).alignItems === 'center' ? missing / 2 : 0
  spacerOf(region.block).style.paddingBottom = `${above}px`
  spacerOf(region.closeBlock).style.paddingTop = `${missing - above}px`
}

/** Put a wrapper's paint in its slot, unless it is already there or already on its way. */
function paintInto(slot, tag, nested = false, content = null) {
  if (slot.firstElementChild || slot.dataset.painting === tag) return
  slot.dataset.painting = tag

  wrapperPaint(tag).then((paint) => {
    if (!paint || !slot.isConnected) return
    const clone = paint.cloneNode(true)
    slot.appendChild(clone)
    if (!paintsSomething(clone)) {
      clone.remove()

      return
    }
    if (!nested) {
      slot.style.setProperty(
        '--yw-paint-inset',
        getComputedStyle(clone).paddingLeft,
      )
    }
    if (content?.isConnected) paintWrappers(content)
  })
}

/** Whether this element has an appearance worth painting: a colour, an image, a border, a shadow. */
function paintsSomething(element) {
  const style = getComputedStyle(element)

  return (
    !isTransparent(style.backgroundColor) ||
    style.backgroundImage !== 'none' ||
    style.boxShadow !== 'none' ||
    ['Top', 'Right', 'Bottom', 'Left'].some(
      (side) =>
        parseFloat(style[`border${side}Width`]) > 0 &&
        style[`border${side}Style`] !== 'none' &&
        !isTransparent(style[`border${side}Color`]),
    )
  )
}

/** Whether a computed colour paints nothing at all -- which is to say, whether its ALPHA is zero. */
function isTransparent(color) {
  if (!color || color === 'transparent') return true
  const parts = /^rgba?\(([^)]+)\)/.exec(color)?.[1].split(',')

  return parts !== undefined && parts.length > 3 && parseFloat(parts[3]) === 0
}

function wrapperLayer(content) {
  const host = content.parentElement
  let layer = host.querySelector(':scope > .yw-wrapper-layer')
  if (!layer) {
    layer = document.createElement('div')
    layer.className = 'yw-wrapper-layer'
    layer.setAttribute('aria-hidden', 'true')
    host.insertBefore(layer, host.firstChild)
  }

  return layer
}

/** Grow the iframe to whatever it turned out to hold. */
function fitFrameToItsContent(frame) {
  const fit = () => {
    try {
      const { body } = frame.contentDocument
      if (body) frame.style.height = `${Math.max(60, body.scrollHeight)}px`
    } catch {}
  }
  frame.addEventListener('load', () => {
    fit()
    try {
      new ResizeObserver(fit).observe(frame.contentDocument.body)
    } catch {}
  })
}
