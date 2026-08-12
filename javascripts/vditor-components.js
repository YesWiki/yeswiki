/**
 * PROTOTYPE -- YesWiki components (`{{action ...}}`) as WYSIWYG widgets inside Vditor.
 *
 * Switched on by `vditor_wiki_editor` in the config; the ACeditor is what everyone still
 * gets. Nothing here is covered by a test yet.
 *
 * Vditor edits Markdown, which is also what a YesWiki page body is (CommonMark/GFM plus
 * the action syntax, see src/Render/Formatter/ActionExtension.php) -- so a page passes
 * through it intact. Its parser (Lute) has never heard of `{{...}}` though: it leaves the
 * tag as paragraph text, and parses *inside* it. A parameter like query="a=*x*" shows up
 * italicised in the editing surface, one keystroke from being destroyed.
 *
 * So a component does not travel through the editor as text. On the way in, a line that is
 * nothing but one tag is wrapped in a fenced block; on the way out the fence is peeled back
 * off. Nothing changes about how a page is stored: the fence exists only between setValue()
 * and getValue(), and Lute round-trips both forms byte-for-byte.
 *
 * The fence is what buys the widget. Vditor already renders a fenced block as one -- a
 * `.vditor-wysiwyg__block` holding the source in a hidden <pre> beside a preview panel --
 * and `customRenders` is its documented hook for filling that panel, the same one mermaid
 * and echarts go through. What goes in it is an iframe on `?wiki/render`, which is how the
 * actions builder has always previewed a component, and the only way an action's own CSS
 * and JS can run without leaking into the editor around it.
 */
import { legacyIconToSprite } from './yw-icon-map.js'

/**
 * The fence a component travels in. Not `yeswiki`, which is what someone documenting the
 * wiki syntax would reach for -- and the docs pages are full of exactly that. Unwrapping
 * one of those would not merely lose a fence, it would start *running* the example.
 */
export const FENCE_LANGUAGE = 'yeswiki-component'

/**
 * ...carrying which of its neighbours it was written against, with no blank line between.
 *
 * Lute separates every block it writes back out with a blank line, and a `{{grid}}` with
 * its `{{col}}`s and their content is written glued together. Without this, opening such a
 * page and saving it re-spaces every wrapper on it for someone who came to fix a typo --
 * so the fence carries what the blank lines were, and they are put back.
 */
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

/**
 * One tag and nothing else on the line -- the same shape the server recognises as a block
 * (ActionBlockStartParser), tempered dot included so a line holding a *pair* of tags is
 * not read as one enormous tag.
 *
 * Column zero only. An indented tag is a continuation line of the list item or quote above
 * it, and lifting that out into a fenced block of its own takes it out of the list.
 */
const STANDALONE_TAG = /^\{\{((?:(?!\}\})[\s\S])*)\}\}[ \t]*$/

/** A fence opening or closing, so that a tag written inside one is left alone. */
const FENCE_DELIMITER = /^ {0,3}(`{3,}|~{3,})/

/**
 * The other pair of tags that wraps ordinary prose: HedgeDoc's `:::info` ... `:::` alerts.
 *
 * Lute has never heard of those either -- worse than a `{{tag}}`, in fact, since the three
 * lines have no blank line between them and so arrive as *one paragraph* with `:::info` as
 * its first line of text. So they go through the same fence, and come out as the same two
 * chips with the box painted behind what they hold.
 *
 * Only the four types the wiki has styles for. `:::note` is not an alert to the server
 * either (src/Render/Formatter/AlertExtension.php) -- it stays the literal text it is, and
 * a chip saying otherwise would be a lie about what the page will look like.
 */
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

/**
 * The Component that writes this tag, or null for a tag nothing declares.
 *
 * By the tag rather than by the component id: what is in the page is `{{entrylist}}`, and
 * the thirteen components that write it are told apart by their pinned settings -- which is
 * more than this needs. All it asks is "is this a wrapper, and what is it called".
 */
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

/**
 * `{{grid}}` and `{{end elem="grid"}}` are half a component each, not two components -- and
 * `:::info` and `:::` are the same thing written differently.
 *
 * `name` is what an opening tag and its closing tag agree on, which is what lets a stack
 * pair them; `closes` is that name, said by the closing half.
 */
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

/**
 * Wrap every standalone tag in a fence, so Vditor makes a widget of it.
 *
 * A tag already inside a fenced block is left exactly as it is: that is someone
 * documenting the wiki syntax, which the docs pages do constantly.
 */
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

    // A `:::` closing nothing is not half of an alert -- the server leaves it as the text
    // it is, so the depth is counted here rather than the line merely matched.
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

/**
 * The reverse, and deliberately narrower than the wrapping: only a fence of ours whose
 * whole body is one tag is unwrapped. A fence someone wrote by hand around an explanation,
 * or around two tags, is left as the fence they wrote.
 */
export function unfenceComponents(markdown) {
  const lines = String(markdown).split('\n')
  // longest first, so `-around` is not matched as `-a`... nothing, but as itself
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
      // the blank lines Lute puts around every block it writes are Lute's, not the
      // author's, wherever this component was written against its neighbour
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

/**
 * The block Lute would have built for that fence, built by hand -- so that a component
 * inserted from the palette can be dropped straight into the document instead of going
 * through setValue(), which would rebuild every widget on the page (and reload every
 * preview iframe in it) for the sake of adding one.
 *
 * Verified against Lute's own Md2VditorDOM output for the same fence: what getValue()
 * reads back off this is the fence, unchanged.
 */
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
  // ...and say so. `2` means "not rendered yet", and leaving it there after rendering
  // invites Vditor's next pass to render the block again -- as the fenced code block it
  // is underneath, since that pass does not go through customRenders. The widget then
  // briefly wears the grey slab and monospace of a code block. insertAt() has always put
  // this back; rewriting one did not, which is why it only happened while editing.
  preview.setAttribute('data-render', '1')
}

const icon = (name) => legacyIconToSprite(name) || ''

const labelFor = (name) => actionConfiguration(name)?.label || name

/**
 * Whether the settings rail has anything to say about this component. The same rule the
 * ACeditor applies before opening it on the one under the cursor: an action the palette
 * does not declare (`{{toc}}`, anything an extension ships without a YAML) would open a
 * panel with no title, no fields and a button that would rewrite the tag as `{{}}`.
 *
 * Such a component still gets a widget and a preview -- only the gear is missing, and
 * the source button beside it is how you edit one.
 */
const isConfigurable = (name) => {
  const configuration = actionConfiguration(name)

  return Boolean(configuration && !configuration.onlyAdd)
}

/** What a `{{end elem="..."}}` closes. */
const closedElement = (tag) =>
  /elem\s*=\s*["']([^"']+)["']/.exec(tag)?.[1] || 'end'

/**
 * Which alert a `:::` closes. Unlike `{{end elem="section"}}`, a closing alert fence says
 * nothing about what it closes -- the type is on the opening fence, several blocks above --
 * so the chip has to go and look, or it could only ever be labelled "alert".
 *
 * Backwards from the block, counting: the nearest opening fence that is not already closed
 * by one of the fences in between is the one this closes.
 */
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

/**
 * Whether this tag is half of a wrapper, and so has no preview of its own -- `{{grid}}`
 * without its `{{end}}` is not a grid, it is an error message about a missing `{{end}}`.
 *
 * The palette knows which actions are wrappers, but only those it lists: `{{col}}` is a
 * real action that is only ever written inside a `{{grid}}`, so the palette offers it
 * separately from nothing and declares nothing about it. The document is the better
 * witness -- a tag with a matching `{{end elem="..."}}` written below it is an opening
 * tag, whatever any declaration says.
 */
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
  // The block's own source, not the panel's text. They are the same thing the FIRST time
  // this runs -- Vditor hands over a preview still holding the fenced code -- but only the
  // first time: rendering replaces that content with the widget, so on any later render
  // `previewElement.textContent` is the widget's LABEL. Re-rendering a `{{section}}` after
  // the settings rail rewrote it therefore read the tag as `Section`, which no declaration
  // matches, so it lost its wrapper role and came back as a leaf: the inline chip that says
  // where a region starts turned into the floating bar of a standalone component.
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
  // the whole widget is one thing to the editor: without this, a click lands inside the
  // preview and Vditor puts a caret in the middle of a rendered entry list
  widget.setAttribute('contenteditable', 'false')

  // which way the content runs: a leaf holds its own, an opening tag has the content
  // below it and a closing one above. There is nothing else to tell the two halves apart
  // by -- both are a bar with a name on it.
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

  // A wrapper's two halves are not renderable on their own -- `{{grid}}` with no `{{end}}`
  // is not a grid, it is an error message about a missing `{{end}}` -- so they show as a
  // chip naming what opens and closes here, the content between them stays ordinary
  // editable prose, and what the wrapper looks like is painted behind that content by
  // paintWrappers() below.
  if (role === 'leaf') {
    const body = document.createElement('div')
    body.className = 'yw-component__body'
    body.innerHTML =
      `<iframe class="yw-component__frame" title="${label}" src="${wiki.url('wiki/render', { content: tag })}"></iframe>` +
      // the preview is a picture of the component, not the component: clicks belong to
      // the editor, the same reason the actions builder covers its own iframe
      `<div class="yw-component__blocker"></div>`
    widget.appendChild(body)
    fitFrameToItsContent(body.querySelector('iframe'))
  }

  previewElement.innerHTML = ''
  previewElement.appendChild(widget)
}

/**
 * What a wrapper looks like, painted behind what it wraps.
 *
 * `{{section bgcolor="..." pattern="..."}}` is a background, a border and a pattern -- the
 * whole point of writing one -- and a chip saying "section" shows none of it. The two
 * halves cannot become one widget, though: everything between them is ordinary prose that
 * has to stay editable, and a widget's insides are not.
 *
 * So the region is painted rather than wrapped. The paint is the element the action really
 * renders as, fetched once and emptied, stretched from the opening chip to the closing one
 * in a layer of its own -- outside the edited content, so that nothing about it can reach
 * the document, and behind it, so the text stays the text.
 *
 * Only the wrapper's own appearance survives this: a `{{grid}}`'s columns are a layout, and
 * a layout cannot be painted behind text that is not in it.
 */
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
          // the wrapper's *look*, not its contents: those are in the document already
          rendered.innerHTML = ''

          return rendered
        })
        .catch(() => null),
    )
  }

  return wrapperPaints.get(openTag)
}

/**
 * A wrapper the settings rail is being filled in for, shown as it is being described
 * rather than as the document still has it -- the same "a preview is not a change" the
 * leaf widgets get, for the one thing you cannot see by looking at a chip.
 */
let pending = null

export function setPendingWrapper(block, tag) {
  pending = block ? { block, tag } : null
}

const pendingTag = (block) => (pending?.block === block ? pending.tag : null)

/**
 * The block that opens the wrapper this one closes -- which is the component a closing
 * chip is about. `{{end elem="section"}}` has no settings of its own: its only parameter
 * is the name of the thing it closes, several blocks above.
 */
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

/**
 * The wrapper regions of the document, outermost first, paired by walking the blocks with
 * a stack -- which is what makes a `{{col}}` inside a `{{grid}}` close the col and not the
 * grid, and an unclosed tag paint nothing rather than paint to the end of the page.
 */
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

  // A region is recorded when its closing tag is found, so an inner one is recorded before
  // the outer one holding it. Reversed, that is outermost first -- which is the order they
  // have to be painted in for a nested wrapper to be painted over the one it sits in.
  regions.reverse()

  // ...and which of them sits inside another, by where their halves fall among the blocks.
  // Only a region that is nobody's inside may reach out past the text column (see the
  // inset in paintInto): a callout written inside a section that grew wider than the
  // section holding it, by exactly the padding of each.
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

/**
 * Grey out the `{# ... #}` comments.
 *
 * Not a component -- a comment renders as nothing at all -- but the same kind of problem:
 * something the writer needs to recognise on sight and which Markdown has no opinion
 * about, so Lute leaves it as the paragraphs it looks like. Marking them is a class on the
 * blocks, which VditorDOM2Md ignores, so nothing about this reaches the document.
 *
 * A comment can run over several blocks, so this is a walk rather than a test: everything
 * from the block that opens one to the block that closes it is inside it.
 */
export function markComments(content) {
  let inside = false

  for (const block of content.children) {
    const text = block.textContent || ''
    let isComment = inside

    if (inside) {
      if (text.includes('#}')) inside = false
    } else if (text.includes('{#')) {
      isComment = true
      // `{# a note #}` opens and closes on one line; a banner comment does not
      inside = !text.includes('#}', text.indexOf('{#') + 2)
    }

    block.classList.toggle('yw-comment', isComment)
  }
}

/** The markdown-extra that may follow a link, and what is inside its braces. */
export const LINK_EXTRA = /^\{([^}]*)\}/

/**
 * A link's `{.newtab}`, drawn as a mark on the link rather than as the braces it is
 * written with.
 *
 * markdown-extra is not something Lute parses, so `[a](b){.newtab}` arrives as an anchor
 * followed by the literal text `{.newtab}` -- which reads as part of the sentence, in an
 * editor whose whole point is that you see the page rather than the notation. It cannot
 * simply be dropped: it *is* the document, and it is what the link rail edits.
 *
 * So it is wrapped rather than removed. The braces stay in the DOM -- and so in what
 * getValue() reads back off it -- and are hidden by the stylesheet instead, which is what
 * keeps this round-trip byte-for-byte. Vditor re-renders a paragraph from Markdown as it
 * is typed in, which undoes this; repaint() puts it back on the next frame.
 */
export function markLinkExtras(content) {
  const caret = getSelection()?.anchorNode

  for (const anchor of content.querySelectorAll('a[href]')) {
    const next = anchor.nextSibling
    if (next?.nodeType !== Node.TEXT_NODE) continue
    const match = LINK_EXTRA.exec(next.textContent)
    // never the text node the caret is in: this splits it, and a caret is not worth losing
    // to redraw something the next keystroke redraws anyway
    if (!match || next === caret) continue

    // `next` keeps the `{...}`; the rest of the sentence goes on in the node this returns
    next.splitText(match[0].length)
    const chip = document.createElement('span')
    chip.className = 'yw-link-extra'
    // one thing, which is what it is to whoever wrote it: no inside for a caret to get
    // lost in, and one keystroke to be rid of
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

/**
 * Classes a wrapper puts on its CONTENT rather than on its box.
 *
 * The paint behind a region is the action's own element, emptied -- so anything that styles
 * what is inside it can never show there. Alignment and text tone are exactly that: a
 * `{{section class="text-center white"}}` centres and lightens its words, and the words in
 * the editor are the editor's own, sitting in front of an empty painted box. Changing
 * either setting therefore did nothing on screen at all.
 *
 * So these few are copied onto the blocks the region holds. Classes only, which
 * VditorDOM2Md ignores -- the same reason markComments can mark a comment without the mark
 * reaching the document.
 */
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
  blocks.forEach((block) => block.classList.remove(...CONTENT_CLASSES))

  regions.forEach((region) => {
    const written = /class\s*=\s*["']([^"']*)["']/.exec(region.tag)?.[1] ?? ''
    const classes = written
      .split(/\s+/)
      .filter((token) => CONTENT_CLASSES.includes(token))
    if (!classes.length) return
    for (let i = region.from + 1; i < region.to; i += 1) {
      blocks[i]?.classList.add(...classes)
    }
  })
}

/**
 * The callout region the caret's block sits in, or null.
 *
 * Reuses the same stack-paired regions the paint uses: a callout IS a wrapper, so "which
 * one am I inside" is a question already answered. Only its own region counts -- a block
 * inside a `{{section}}` that happens to hold a callout elsewhere is not in a callout.
 */
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
    regions.forEach((region) => {
      const slot = document.createElement('div')
      layer.appendChild(slot)
      region.slot = slot
    })
  }

  styleRegionContent(content, regions)

  // positions are recomputed every time: the region moves whenever anything above it or
  // inside it changes height, which is most keystrokes
  const layerBox = layer.getBoundingClientRect()
  regions.forEach((region, index) => {
    const slot = region.slot || layer.children[index]
    if (!slot) return
    // ...and the paint, if the slot has none. A paint is fetched, so a rebuild that
    // happens while one is in flight throws away the slot it was going to land in
    // (`isConnected` is false by the time it arrives) -- and since the promise is cached
    // and the signature does not change again, nothing would ever ask for it a second
    // time. Painting from here rather than only on a rebuild makes that self-healing.
    paintInto(slot, region.tag, region.nested)
    const from = region.block.getBoundingClientRect()
    const to = region.closeBlock.getBoundingClientRect()
    slot.style.top = `${from.top - layerBox.top}px`
    slot.style.height = `${Math.max(0, to.bottom - from.top)}px`
  })
}

/**
 * Put a wrapper's paint in its slot, unless it is already there or already on its way.
 *
 * Idempotent on purpose: this is called from every repaint, which is every animation frame
 * in which anything moved, and a fetch per frame would be a fetch per keystroke.
 */
function paintInto(slot, tag, nested = false) {
  if (slot.firstElementChild || slot.dataset.painting === tag) return
  slot.dataset.painting = tag

  wrapperPaint(tag).then((paint) => {
    if (!paint || !slot.isConnected) return
    const clone = paint.cloneNode(true)
    slot.appendChild(clone)
    // measured rather than guessed, and only once it is in the document: whether an
    // element paints anything is a question about the theme, not about the action
    if (!paintsSomething(clone)) {
      clone.remove()

      return
    }
    // A box's padding is part of how it looks -- an alert's words are not written against
    // its coloured rule -- and the text this is painted behind is not inside the box, so it
    // cannot be pushed off the edge by it. The region grows outwards by that much instead,
    // which puts the words where the padding would have put them.
    //
    // Not when it is inside another one, though: growing outwards there means growing past
    // the box it is written in. A callout inside a section came out wider than the section.
    if (!nested) {
      slot.style.setProperty(
        '--yw-paint-inset',
        getComputedStyle(clone).paddingLeft,
      )
    }
  })
}

/**
 * Whether this element has an appearance worth painting: a colour, an image, a border, a
 * shadow. Anything else is a wrapper that exists to arrange its contents rather than to
 * look like something -- `{{grid}}` and `{{col}}` are a row and two columns -- and a
 * layout cannot be painted behind text that is not laid out in it.
 *
 * Dropping those is not only tidiness. A `{{col}}` renders half a width wide and lands on
 * top of whatever encloses it, so a `{{section}}` holding a grid had its colour hidden
 * down the left-hand half of the region by two invisible columns -- visible only where
 * they were not.
 */
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

/**
 * Whether a computed colour paints nothing at all -- which is to say, whether its ALPHA is
 * zero.
 *
 * This used to be `/,\s*0\)$/.test(color)`, meaning "ends in `, 0)`". That is what
 * `rgba(0, 0, 0, 0)` looks like, so it worked on the case it was written for and on nothing
 * else: `rgb()` has no alpha component, so the test read its BLUE channel instead. Every
 * colour with no blue in it -- pure red, orange, olive, `#c64600` -- was declared invisible
 * and its section painted nothing.
 */
function isTransparent(color) {
  if (!color || color === 'transparent') return true
  const parts = /^rgba?\(([^)]+)\)/.exec(color)?.[1].split(',')

  // three components is `rgb()`, which has no alpha and is therefore opaque
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

/**
 * Grow the iframe to whatever it turned out to hold. Same origin, so the height can simply
 * be read -- and re-read, since an action that renders itself in the browser (an entry
 * list, a map) has nothing in it at load.
 */
function fitFrameToItsContent(frame) {
  const fit = () => {
    try {
      const { body } = frame.contentDocument
      if (body) frame.style.height = `${Math.max(60, body.scrollHeight)}px`
    } catch {
      // a preview that cannot be measured keeps the height the stylesheet gave it
    }
  }
  frame.addEventListener('load', () => {
    fit()
    try {
      new ResizeObserver(fit).observe(frame.contentDocument.body)
    } catch {
      // no observer: the load-time height is what it keeps
    }
  })
}
