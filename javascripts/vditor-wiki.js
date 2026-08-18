/** PROTOTYPE -- Vditor editing page/entry wiki syntax, with components rendered in place. */
import {
  filePickerIsOpen,
  filePickerMenuItem,
  hasFilePicker,
} from './vditor-toolbar-file.js'
import { legacyIconToSprite } from './yw-icon-map.js'
import { restoreStashedValue, switchEditorTo } from './editor-switch.js'
import { closeRails } from './editor-rails.js'
import { registerEditor } from './editor-handles.js'
import ActionsBuilder from './actions-builder.js'
import LinkPanel from './link-panel.js'
import {
  FENCE_LANGUAGES,
  fenceComponents,
  LINK_EXTRA,
  calloutRegionAt,
  componentBlockElement,
  markComments,
  markLinkExtras,
  openingBlockFor,
  paintWrappers,
  renderComponent,
  rewriteBlock,
  setPendingWrapper,
  tagOfBlock,
  unfenceComponents,
} from './vditor-components.js'
import { followScheme, vditorThemeOptions } from './editor-scheme.js'

const VDITOR_CDN = 'javascripts/vendor/vditor'

/** The four callouts, and the icon each is recognised by. */
const CALLOUTS = [
  ['info', 'info-circle'],
  ['success', 'circle-check'],
  ['warning', 'alert-triangle'],
  ['danger', 'ban'],
]

/** Written out, so `php src/build-js-lang-keys.php` can see each key. */
function calloutLabel(type) {
  switch (type) {
    case 'success':
      return _t('ALERT_SUCCESS')
    case 'warning':
      return _t('ALERT_WARNING')
    case 'danger':
      return _t('ALERT_DANGER')
    default:
      return _t('ALERT_INFO')
  }
}

/** The `{{...}}` braces off, which is the form the actions builder reads and writes. */
const withoutBraces = (tag) =>
  tag.trim().replace(/^\{\{/, '').replace(/\}\}$/, '')

/** Whether a block's widget opens a wrapper, closes one, or is a component on its own. */
const tagRoleOf = (block) =>
  block
    .querySelector('.yw-component')
    ?.classList.contains('yw-component--close')
    ? 'close'
    : 'other'

/** What the actions builder talks to. */
class ComponentEditor {
  constructor(vditorInstance) {
    this.vditor = vditorInstance
  }

  get content() {
    return this.vditor.vditor.wysiwyg.element
  }

  /** Which widget the rail is on, said in the document rather than only in the panel. */
  highlight(block) {
    this.clearHighlight()
    block
      ?.querySelector('.yw-component')
      ?.classList.add('yw-component--editing')
  }

  clearHighlight() {
    this.content
      .querySelectorAll('.yw-component--editing')
      .forEach((element) => element.classList.remove('yw-component--editing'))
  }

  /** Rewrite the component the rail was opened on, in place. */
  replaceRange(block, wikiCode) {
    this.cancelPendingPreview()
    setPendingWrapper(null)
    rewriteBlock(block, wikiCode)
    this.highlight(block)
    this.sync()
    this.recordUndo()
  }

  /** Put what the rail just wrote on Vditor's undo stack. */
  recordUndo() {
    const vditor = this.vditor.vditor
    try {
      vditor.undo.addToUndoStack(vditor)
    } catch {}
  }

  /** Show what the settings rail currently says, on the widget, without writing it into the document. */
  previewComponent(block, wikiCode) {
    this.cancelPendingPreview()
    this.pendingPreview = setTimeout(() => {
      renderComponent(block.querySelector('.vditor-wysiwyg__preview'), wikiCode)
      setPendingWrapper(block, wikiCode)
      paintWrappers(this.content)
    }, 400)
  }

  /** Back to what the document actually says: the change was abandoned, or never made. */
  restoreComponent(block) {
    this.cancelPendingPreview()
    setPendingWrapper(null)
    if (!this.content.contains(block)) return
    renderComponent(
      block.querySelector('.vditor-wysiwyg__preview'),
      tagOfBlock(block),
    )
    paintWrappers(this.content)
  }

  cancelPendingPreview() {
    clearTimeout(this.pendingPreview)
  }

  /** Write a component the document does not have yet, after whatever block the rail was opened from. */
  insertAt(after, wikiCode) {
    const anchor = after || this.lastBlock()
    const place = (...nodes) =>
      anchor ? anchor.after(...nodes) : this.content.append(...nodes)
    let inserted

    if (wikiCode.trim().includes('\n')) {
      const holder = document.createElement('div')
      holder.innerHTML = this.vditor.vditor.lute.Md2VditorDOM(
        fenceComponents(wikiCode.trim()),
      )
      inserted = holder.firstElementChild
      place(...holder.childNodes)
    } else {
      inserted = componentBlockElement(wikiCode)
      place(inserted)
    }

    this.content
      .querySelectorAll(".vditor-wysiwyg__preview[data-render='2']")
      .forEach((preview) => {
        renderComponent(preview)
        preview.setAttribute('data-render', '1')
      })
    this.highlight(inserted)
    this.sync()
    this.recordUndo()
    inserted.scrollIntoView({ block: 'nearest', behavior: 'smooth' })

    return inserted
  }

  lastBlock() {
    const blocks = this.content.querySelectorAll('[data-block="0"]')
    return blocks[blocks.length - 1] || this.content.lastElementChild
  }

  /** The block the caret is in, which is where the palette inserts what it is asked for. */
  blockAtCursor() {
    const selection = getSelection()
    if (!selection?.rangeCount) return null
    let node = selection.getRangeAt(0).startContainer
    if (node.nodeType === Node.TEXT_NODE) node = node.parentElement
    if (!this.content.contains(node)) return null

    return node.closest('[data-block="0"]')
  }

  sync() {
    this.onChange?.()
  }
}

function initVditorWiki(textareaParam) {
  const textarea = textareaParam
  textarea.setAttribute('data-vditor-ready', '')
  textarea.style.display = 'none'
  restoreStashedValue(textarea)

  const container = document.createElement('div')
  textarea.insertAdjacentElement('afterend', container)

  const saveButton = textarea.form?.querySelector('.vditor-wiki-save')
  const rows = parseInt(textarea.getAttribute('rows'), 10) || 10
  const lang = textarea.getAttribute('data-vditor-lang') || 'en_US'
  const actionsBuilder = new ActionsBuilder()
  const linkPanel = new LinkPanel()
  let componentEditor = null

  const editor = new Vditor(container, {
    cdn: VDITOR_CDN,
    ...vditorThemeOptions(VDITOR_CDN),
    mode: 'wysiwyg',
    lang,
    minHeight: Math.min(600, Math.max(300, rows * 30)),
    width: '100%',
    placeholder: textarea.getAttribute('placeholder') || '',
    toolbar: [
      ...(saveButton
        ? [
            {
              name: 'yw-save',
              tip: saveButton.textContent.trim(),
              tipPosition: 'se',
              icon: `${legacyIconToSprite('device-floppy')}<span>${saveButton.textContent.trim()}</span>`,
              click() {
                saveButton.form.requestSubmit(saveButton)
              },
            },
            '|',
          ]
        : []),
      'headings',
      'bold',
      'italic',
      'strike',
      '|',
      'line',
      'list',
      'ordered-list',
      'check',
      '|',
      'quote',
      'link',
      'table',
      '|',
      ...(hasFilePicker()
        ? [
            filePickerMenuItem({
              format: 'wiki',
              onComplete: (wikiCode) =>
                componentEditor.insertAt(
                  componentEditor.blockAtCursor(),
                  wikiCode,
                ),
            }),
            '|',
          ]
        : []),
      ...CALLOUTS.map(([type, icon]) => ({
        name: `yw-callout-${type}`,
        tip: calloutLabel(type),
        tipPosition: 'n',
        icon: legacyIconToSprite(icon),
        click() {
          applyCallout(type)
        },
      })),
      '|',
      {
        name: 'yw-component',
        tip: _t('ADD_COMPONENT'),
        tipPosition: 'n',
        icon: legacyIconToSprite('stack-2'),
        click() {
          openBuilderForNewComponent()
        },
      },
      '|',
      'emoji',
      '|',
      'undo',
      'redo',
      '|',
      'code',
      'inline-code',
      {
        name: 'yw-switch-editor',
        tip: _t('SWITCH_TO_SOURCE_EDITOR'),
        tipPosition: 'sw',
        icon: legacyIconToSprite('arrows-horizontal'),
        click() {
          switchEditorTo('aceditor', textarea)
        },
      },
    ],
    cache: { enable: false },
    resize: { enable: true },
    customWysiwygToolbar() {},
    toolbarConfig: { pin: true },
    customRenders: FENCE_LANGUAGES.map((language) => ({
      language,
      render: (element) => renderComponent(element),
    })),
    after() {
      componentEditor = new ComponentEditor(editor)
      componentEditor.onChange = sync
      editor.setValue(fenceComponents(textarea.value))
      container
        .querySelectorAll('.vditor-toolbar > .vditor-toolbar__item > button')
        .forEach((button) => button.classList.add('yw-btn'))
      container
        .querySelector('.vditor-toolbar button[data-type="yw-save"]')
        ?.classList.add('yw-btn--primary')
      componentEditor.content.addEventListener('click', onContentClick, true)
      componentEditor.content.addEventListener('keydown', onArrowKeyDown, true)
      componentEditor.content.addEventListener('keyup', onArrowKeyUp, true)
      componentEditor.content.addEventListener('keyup', (event) => {
        if (event.key === 'Enter' && convertSetextHeadings()) sync()
      })
      repaint()
      new ResizeObserver(repaint).observe(componentEditor.content)
      registerEditor(textarea.name, {
        getValue: () => unfenceComponents(editor.getValue()),
        setValue(text) {
          editor.setValue(fenceComponents(text))
          sync()
        },
      })
    },
    input() {
      convertSetextHeadings()
      sync()
    },
  })

  followScheme(editor, VDITOR_CDN)

  /** The textarea stays the thing that gets posted: the editor writes into it and says so, so validation, unsaved-changes and live conditions keep working off the field they always watched. */
  function sync() {
    textarea.value = unfenceComponents(editor.getValue())
    textarea.dispatchEvent(new Event('input', { bubbles: true }))
    textarea.dispatchEvent(new Event('change', { bubbles: true }))
    repaint()
  }

  /** At most once a frame: this is called from an observer and from every keystroke. */
  let repaintQueued = false
  function repaint() {
    if (repaintQueued || !componentEditor) return
    repaintQueued = true
    requestAnimationFrame(() => {
      repaintQueued = false
      markComments(componentEditor.content)
      markLinkExtras(componentEditor.content)
      paintWrappers(componentEditor.content)
    })
  }

  /** `Titre` with a line of `=` under it is a heading -- CommonMark calls it setext, and Lute reads one correctly when a page is opened. */
  function convertSetextHeadings() {
    const caretBlock = componentEditor.blockAtCursor()
    let converted = false

    for (const block of [...componentEditor.content.children]) {
      if (block === caretBlock || block.tagName !== 'P') continue
      if (!/^=+$/.test((block.textContent || '').trim())) continue
      const titled = block.previousElementSibling
      if (!titled || titled.tagName !== 'P') continue

      const heading = document.createElement('h1')
      heading.setAttribute('data-block', '0')
      heading.setAttribute('data-marker', '=')
      heading.innerHTML = titled.innerHTML
      titled.replaceWith(heading)
      block.remove()
      converted = true
    }

    return converted
  }

  /** Make the block at the caret a callout of this type -- or, if it already is one, make it that type instead. */
  function applyCallout(type) {
    const block = componentEditor.blockAtCursor()
    if (!block) return

    const existing = calloutRegionAt(componentEditor.content, block)
    if (existing) {
      rewriteBlock(existing.block, `:::${type}`)
      renderComponent(
        existing.closeBlock.querySelector('.vditor-wysiwyg__preview'),
        ':::',
      )
      sync()
      componentEditor.recordUndo()

      return
    }

    const opening = componentBlockElement(`:::${type}`)
    const closing = componentBlockElement(':::')
    block.before(opening)
    block.after(closing)
    componentEditor.content
      .querySelectorAll(".vditor-wysiwyg__preview[data-render='2']")
      .forEach((preview) => {
        renderComponent(preview)
        preview.setAttribute('data-render', '1')
      })
    sync()
    componentEditor.recordUndo()
  }

  /** The palette, for a component the page does not have yet. */
  function openBuilderForNewComponent() {
    const anchor = componentEditor.blockAtCursor()
    componentEditor.clearHighlight()
    actionsBuilder.open(componentEditor, { insertAt: anchor })
  }

  /** The settings panel, for the component that was clicked. */
  function openBuilderForBlock(clicked) {
    const block =
      tagRoleOf(clicked) === 'close'
        ? openingBlockFor(componentEditor.content, clicked)
        : clicked
    if (!block) return

    const tag = tagOfBlock(block)
    if (!tag.startsWith('{{')) return

    componentEditor.highlight(block)
    actionsBuilder.open(componentEditor, {
      action: withoutBraces(tag),
      target: block,
      insertAt: block,
      key: `${textarea.id}:${tag}`,
    })
  }

  /** Show the wiki code behind a widget, and hide it again. */
  function toggleSource(block) {
    const source = block.querySelector('.vditor-wysiwyg__pre')
    if (source.style.display !== 'none') {
      source.style.display = 'none'

      return
    }

    showSource(block)
  }

  /** Put the caret in a widget's wiki code, which is the only text its block holds. */
  function showSource(block, atEnd = false) {
    const source = block.querySelector('.vditor-wysiwyg__pre')
    source.style.display = 'block'
    const code = source.firstElementChild
    if (!code.firstChild) code.appendChild(document.createTextNode(''))
    const range = document.createRange()
    range.selectNodeContents(code)
    range.collapse(!atEnd)
    const selection = getSelection()
    selection.removeAllRanges()
    selection.addRange(range)
  }

  const isComponentBlock = (block) =>
    Boolean(
      block?.classList?.contains('vditor-wysiwyg__block') &&
      block.querySelector('.yw-component'),
    )

  let arrowFrom = null

  const onArrowKeyDown = (event) => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      arrowFrom = componentEditor.blockAtCursor()
    }
  }

  /** A widget's block holds no text but its wiki code, which is hidden, so the arrow keys step straight over it. Putting the caret back in that code keeps the component on the keyboard's path -- and, on this same event, makes vditor offer its own move and delete panel. */
  function onArrowKeyUp(event) {
    if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return
    const from = arrowFrom
    arrowFrom = null
    if (!from) return

    const down = event.key === 'ArrowDown'
    const skipped = down ? from.nextElementSibling : from.previousElementSibling
    const landed = componentEditor.blockAtCursor()
    if (!isComponentBlock(skipped) || !landed) return
    if (landed === from || landed === skipped) return

    showSource(skipped, !down)
  }

  /** A link, and the `{...}` that may follow it. */
  function linkTargetFrom(anchor) {
    const next = anchor.nextSibling
    const chip = next?.classList?.contains('yw-link-extra') ? next : null
    const carrier = chip || (next?.nodeType === Node.TEXT_NODE ? next : null)
    const extra = carrier ? LINK_EXTRA.exec(carrier.textContent) : null

    return {
      anchor,
      extraNode: extra ? carrier : null,
      wholeNode: Boolean(chip),
      extraLength: extra ? extra[0].length : 0,
      extra: extra ? extra[1] : '',
    }
  }

  /** A link in the editor is not a link to follow -- following it would leave the page being written -- it is the thing to edit. */
  function openLinkPanelFor(anchor) {
    const target = linkTargetFrom(anchor)
    const href = anchor.getAttribute('href') || ''
    componentEditor.clearHighlight()
    linkPanel.open(componentEditor, {
      action: 'edit',
      link: href,
      text: anchor.textContent || '',
      title: anchor.getAttribute('title') || '',
      extra: target.extra,
      key: `${textarea.id}:link:${href}:${anchor.textContent}`,
      onComplete: (markdown) => replaceLink(target, markdown),
    })
  }

  /** Write the rail's link over the one it was opened on, markdown-extra included. */
  function replaceLink(target, markdown) {
    const holder = document.createElement('div')
    holder.innerHTML = editor.vditor.lute.Md2VditorDOM(markdown)
    const produced = holder.firstElementChild
    if (!produced || !target.anchor.isConnected) return

    if (target.wholeNode) {
      target.extraNode?.remove()
    } else if (target.extraNode) {
      target.extraNode.textContent = target.extraNode.textContent.slice(
        target.extraLength,
      )
    }
    target.anchor.replaceWith(...produced.childNodes)
    sync()
  }

  /** The component being worked on, which is what keeps its bar on screen after the pointer has left it. */
  function select(widget) {
    componentEditor.content
      .querySelectorAll('.yw-component--selected')
      .forEach((other) => other.classList.remove('yw-component--selected'))
    widget?.classList.add('yw-component--selected')
  }

  /** A widget is not text, so the click that would put a caret in it does something else: the gear (and the preview itself) opens the rail, and the source button shows the wiki code -- which is the only way to edit a component the rail will not touch. */
  function onContentClick(event) {
    const clicked =
      event.target.closest?.('.yw-link-extra')?.previousElementSibling ||
      event.target
    const anchor = clicked.closest?.('a[href]')
    if (anchor && componentEditor.content.contains(anchor)) {
      event.preventDefault()
      event.stopPropagation()
      select(null)
      openLinkPanelFor(anchor)

      return
    }

    const widget = event.target.closest?.('.yw-component')
    select(widget)
    if (!widget) {
      if (
        !event.target.closest?.('.vditor-wysiwyg__pre') &&
        !filePickerIsOpen()
      ) {
        closeRails()
        componentEditor.clearHighlight()
      }

      return
    }

    const block = widget.closest('.vditor-wysiwyg__block')
    const button = event.target.closest('[data-yw-component-btn]')?.dataset
      .ywComponentBtn

    if (button === 'source') {
      toggleSource(block)
    } else {
      openBuilderForBlock(block)
    }

    event.preventDefault()
    event.stopPropagation()
  }
}

ywInit((root) => {
  if (
    root.matches &&
    root.matches('textarea.vditor-wiki:not([data-vditor-ready])')
  ) {
    initVditorWiki(root)
  }
  ;(root.querySelectorAll ? root : document)
    .querySelectorAll('textarea.vditor-wiki:not([data-vditor-ready])')
    .forEach(initVditorWiki)
})
