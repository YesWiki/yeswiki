/**
 * PROTOTYPE -- Vditor editing page/entry wiki syntax, with components rendered in place.
 *
 * Stands in for the ACeditor when `vditor_wiki_editor` is on. Where the ACeditor shows the
 * source and opens a rail on the component the cursor is in, this shows the component and
 * opens the same rail on the one that was clicked -- the actions builder is reached
 * through three methods (clearHighlight/insertAt/replaceRange), so it does not care which
 * editor is underneath it.
 *
 * The widget itself, and why a component travels through Vditor as a fenced block rather
 * than as text, is vditor-components.js.
 */
import { filePickerMenuItem, hasFilePicker } from './vditor-toolbar-file.js'
import { legacyIconToSprite } from './yw-icon-map.js'
import { restoreStashedValue, switchEditorTo } from './editor-switch.js'
import { closeRails } from './editor-rails.js'
import ActionsBuilder from './actions-builder.js'
import LinkPanel from './link-panel.js'
import {
  FENCE_LANGUAGES,
  componentBlockElement,
  fenceComponents,
  LINK_EXTRA,
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

/**
 * What the actions builder talks to. The ACeditor's version of this addresses the document
 * by row and column; here a "range" is simply the widget element itself, which survives
 * the document being edited around it in a way a coordinate does not.
 */
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
  }

  /**
   * Show what the settings rail currently says, on the widget, without writing it into
   * the document. Its presence is also what tells the rail it has nowhere else to put a
   * preview and can drop its own -- see `editorRendersComponents` in actions-builder-app.
   *
   * Debounced: this fires on every keystroke in a parameter field, and each preview is a
   * page fetched into an iframe.
   */
  previewComponent(block, wikiCode) {
    this.cancelPendingPreview()
    this.pendingPreview = setTimeout(() => {
      renderComponent(block.querySelector('.vditor-wysiwyg__preview'), wikiCode)
      // a wrapper's chip shows none of what is being changed about it -- a section's
      // colour is the region behind the content, so that is what has to follow the rail
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

  /**
   * Write a component the document does not have yet, after whatever block the rail was
   * opened from.
   *
   * Anything but a single tag -- a wrapper arrives as its opening tag, some example
   * content and its `{{end}}` -- is handed to Lute to turn into blocks, so that the
   * content between the two halves lands as the ordinary editable prose it is.
   */
  insertAt(after, wikiCode) {
    const anchor = after || this.lastBlock()
    let inserted

    if (wikiCode.trim().includes('\n')) {
      const holder = document.createElement('div')
      holder.innerHTML = this.vditor.vditor.lute.Md2VditorDOM(
        fenceComponents(wikiCode.trim()),
      )
      inserted = holder.firstElementChild
      anchor.after(...holder.childNodes)
    } else {
      inserted = componentBlockElement(wikiCode)
      anchor.after(inserted)
    }

    this.content
      .querySelectorAll(".vditor-wysiwyg__preview[data-render='2']")
      .forEach((preview) => {
        renderComponent(preview)
        preview.setAttribute('data-render', '1')
      })
    this.highlight(inserted)
    this.sync()

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
  // whatever the other editor was holding, if this is a switch rather than a page opened
  restoreStashedValue(textarea)

  const container = document.createElement('div')
  textarea.insertAdjacentElement('afterend', container)

  // the page's own save button, hidden by the template: the toolbar item below submits
  // through it rather than duplicating what it knows
  const saveButton = textarea.form?.querySelector('.vditor-wiki-save')
  const rows = parseInt(textarea.getAttribute('rows'), 10) || 10
  const lang = textarea.getAttribute('data-vditor-lang') || 'en_US'
  const actionsBuilder = new ActionsBuilder()
  const linkPanel = new LinkPanel()
  let componentEditor = null

  const editor = new Vditor(container, {
    cdn: 'javascripts/vendor/vditor',
    mode: 'wysiwyg',
    lang,
    minHeight: Math.min(600, Math.max(300, rows * 30)),
    width: '100%',
    placeholder: textarea.getAttribute('placeholder') || '',
    toolbar: [
      // the toolbar is where your hands already are while writing, which is the whole
      // reason this is not only at the foot of a long page
      ...(saveButton
        ? [
            {
              name: 'yw-save',
              tip: saveButton.textContent.trim(),
              tipPosition: 'se',
              // named, not just drawn: saving is the one thing in this bar you look for
              // rather than recognise. Its label comes off the button it submits through,
              // so `preview_before_save` still says what it is going to do.
              icon: `${legacyIconToSprite('device-floppy')}<span>${saveButton.textContent.trim()}</span>`,
              click() {
                // through the real button rather than form.submit(): the handler decides
                // what to do from the submitter's name and value (save, or preview first)
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
      // an attached file is written as `{{attach}}`, so it arrives as a component and gets
      // the same widget: what the picker inserts is previewed where it was inserted
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
      // the one button that has no equivalent in a Markdown editor: everything YesWiki can
      // put on a page that is not prose is behind it
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
      // last, and pushed to the far end of the bar by the stylesheet: leaving for the other
      // editor is not one of the things you do to what you are writing
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
    // see vditor-textarea.js: 3.11 calls this hook unconditionally although it declares it
    // optional, and the TypeError leaves every block popover built but never positioned
    customWysiwygToolbar() {},
    toolbarConfig: { pin: true },
    // the documented hook for a fenced block Vditor does not know: it hands us the preview
    // panel of every component block, on load and whenever one of them is edited. One
    // entry per fence language, which differ only in what blank lines they put back.
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
      // the same primary button the form's own Save is, since it does the same thing
      container
        .querySelector('.vditor-toolbar button[data-type="yw-save"]')
        ?.classList.add('yw-btn--primary')
      componentEditor.content.addEventListener('click', onContentClick, true)
      // Enter is when an underline is finished, and Vditor's own `input` option is not: it
      // fires when Vditor re-renders, which is neither every keystroke nor every Enter
      componentEditor.content.addEventListener('keyup', (event) => {
        if (event.key === 'Enter' && convertSetextHeadings()) sync()
      })
      repaint()
      // a wrapper region moves whenever anything above it or inside it changes height,
      // which includes a preview iframe finishing loading and the window being resized
      new ResizeObserver(repaint).observe(componentEditor.content)
    },
    input() {
      convertSetextHeadings()
      sync()
    },
  })

  /**
   * The textarea stays the thing that gets posted: the editor writes into it and says so,
   * so validation, unsaved-changes and live conditions keep working off the field they
   * always watched.
   */
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

  /**
   * `Titre` with a line of `=` under it is a heading -- CommonMark calls it setext, and
   * Lute reads one correctly when a page is opened. Vditor never *makes* one, though: it
   * has input rules for `# ` and none for an underline, which is written under the line it
   * applies to and so is not something the block being typed can decide about itself.
   *
   * Converted once the caret leaves the underline, which is also when Vditor turns `---`
   * into a divider. `-` is left alone for that very reason: in this editor three dashes
   * already mean "divider", and that is what someone typing them is usually after --
   * whatever CommonMark would make of the same three dashes under a paragraph.
   */
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
      // Lute writes a heading back out in whichever form this says, so the page keeps the
      // notation it was written in rather than being rewritten to `# Titre`
      heading.setAttribute('data-marker', '=')
      heading.innerHTML = titled.innerHTML
      titled.replaceWith(heading)
      block.remove()
      converted = true
    }

    return converted
  }

  /** The palette, for a component the page does not have yet. */
  function openBuilderForNewComponent() {
    const anchor = componentEditor.blockAtCursor()
    componentEditor.clearHighlight()
    actionsBuilder.open(componentEditor, { insertAt: anchor })
  }

  /**
   * The settings panel, for the component that was clicked.
   *
   * A closing chip counts as the component it closes: `{{end elem="section"}}` has no
   * settings of its own -- its only parameter is the name of the thing several blocks
   * above -- and that is the one the panel writes back to. The ACeditor does the same for
   * a cursor landing on a closing tag.
   */
  function openBuilderForBlock(clicked) {
    const block =
      tagRoleOf(clicked) === 'close'
        ? openingBlockFor(componentEditor.content, clicked)
        : clicked
    if (!block) return

    const tag = tagOfBlock(block)
    componentEditor.highlight(block)
    actionsBuilder.open(componentEditor, {
      action: withoutBraces(tag),
      target: block,
      insertAt: block,
      // same widget, same panel: asking twice must not rebuild the settings under someone
      // who is halfway through filling them in
      key: `${textarea.id}:${tag}`,
    })
  }

  /**
   * Show the wiki code behind a widget, and hide it again.
   *
   * The preview stays put throughout: this reveals the source *above* it, which is what
   * Vditor itself does for a code block. Hiding the preview instead is a one-way trip --
   * nothing but a full re-render of the document ever puts one back, so the widget went
   * to source and stayed there.
   *
   * Vditor also hides the source again on its own once the caret leaves the block, but
   * that only fires for clicks it sees, and a click on this button is one it does not.
   */
  function toggleSource(block) {
    const source = block.querySelector('.vditor-wysiwyg__pre')
    if (source.style.display !== 'none') {
      source.style.display = 'none'

      return
    }

    source.style.display = 'block'
    const code = source.firstElementChild
    // an empty code element has no text node for a caret to sit in
    if (!code.firstChild) code.appendChild(document.createTextNode(''))
    const range = document.createRange()
    range.selectNodeContents(code)
    range.collapse(true)
    const selection = getSelection()
    selection.removeAllRanges()
    selection.addRange(range)
  }

  /**
   * A link, and the `{...}` that may follow it. markdown-extra is not something Lute
   * parses, so a link's classes sit after the anchor rather than on it -- but they are part
   * of the same link to anyone writing one, and the rail edits them.
   *
   * Either in the chip markLinkExtras() has made of them, or still in the text node it has
   * not reached yet: this is read on a click, and the rail must not depend on a repaint
   * having happened first.
   */
  function linkTargetFrom(anchor) {
    const next = anchor.nextSibling
    const chip = next?.classList?.contains('yw-link-extra') ? next : null
    const carrier = chip || (next?.nodeType === Node.TEXT_NODE ? next : null)
    const extra = carrier ? LINK_EXTRA.exec(carrier.textContent) : null

    return {
      anchor,
      extraNode: extra ? carrier : null,
      // a chip is the extra and nothing else, so replacing the link takes the whole of it;
      // a text node carries the rest of the sentence too, so only its first characters go
      wholeNode: Boolean(chip),
      extraLength: extra ? extra[0].length : 0,
      extra: extra ? extra[1] : '',
    }
  }

  /**
   * A link in the editor is not a link to follow -- following it would leave the page
   * being written -- it is the thing to edit. So a click opens the same rail the ACeditor
   * opens when the cursor lands in one.
   */
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
      // which link this is, so clicking the same one twice does not rebuild the fields
      key: `${textarea.id}:link:${href}:${anchor.textContent}`,
      onComplete: (markdown) => replaceLink(target, markdown),
    })
  }

  /** Write the rail's link over the one it was opened on, markdown-extra included. */
  function replaceLink(target, markdown) {
    const holder = document.createElement('div')
    holder.innerHTML = editor.vditor.lute.Md2VditorDOM(markdown)
    // Lute wraps what it is given in a block; a link is inline, so it is the block's
    // *contents* that go into the paragraph the old link was in
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

  /**
   * The component being worked on, which is what keeps its bar on screen after the pointer
   * has left it. Hovering is enough to see what a component is; selecting is what says
   * "this one", and a caret cannot: a widget is not text.
   */
  function select(widget) {
    componentEditor.content
      .querySelectorAll('.yw-component--selected')
      .forEach((other) => other.classList.remove('yw-component--selected'))
    widget?.classList.add('yw-component--selected')
  }

  /**
   * A widget is not text, so the click that would put a caret in it does something else:
   * the gear (and the preview itself) opens the rail, and the source button shows the
   * wiki code -- which is the only way to edit a component the rail will not touch.
   */
  function onContentClick(event) {
    // the chip is the link's, so it is the link it opens the rail on -- the anchor is the
    // element right before it
    const clicked =
      event.target.closest?.('.yw-link-extra')?.previousElementSibling ||
      event.target
    const anchor = clicked.closest?.('a[href]')
    if (anchor && componentEditor.content.contains(anchor)) {
      // never follow it: the page being written is what you are looking at
      event.preventDefault()
      event.stopPropagation()
      select(null)
      openLinkPanelFor(anchor)

      return
    }

    const widget = event.target.closest?.('.yw-component')
    select(widget)
    if (!widget) {
      if (!event.target.closest?.('.vditor-wysiwyg__pre')) {
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

    // Vditor's own handler would put the caret inside the rendered component
    event.preventDefault()
    event.stopPropagation()
  }
}

// ticket 14's convention, keyed on the field rather than on <body>: a boosted navigation
// swaps the body's contents and a body-keyed initialiser would run once per session
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
