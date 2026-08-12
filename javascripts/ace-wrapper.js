// Side-effect only: each self-registers into the global `ace` via ace.define(...)
import './vendor/ace/ace.js'
// Loads html rules cause it's used inside yeswiki mode
import './vendor/ace/mode-html.js'
// CSS, for the screens that edit a stylesheet rather than prose (ticket 30). Same
// side-effect registration; the matching worker-css.js sits beside it so ACE's own loader
// can fetch it from `basePath` and report a bad rule where it is written.
import './vendor/ace/mode-css.js'
// Twig, for the screen that edits template overrides (ticket 30). It brings its own HTML,
// CSS and JavaScript rules, so a template highlights as markup *and* as Twig -- and its
// `createWorker` returns early for anything that is not the HTML mode itself, so unlike
// `ace/mode/html` it spawns no worker and needs none vendored. Which is what you want here:
// an HTML validator would flag every `{% if %}` inside an attribute as a broken tag.
import './vendor/ace/mode-twig.js'
import './vendor/ace/ext-language_tools.js'
// The wiki syntax mode, imported rather than left to ACE's own loader: a module ACE fetches
// by URL is a module nothing can see coming, so a farm instance never publishes it and the
// editor loads without highlighting. Same side-effect registration as the vendor files above.
import './mode-yeswiki.js'

/**
 * A callout's two fences, which are lines of their own. Only the four types the wiki has
 * styles for -- `:::note` is not a callout to the server either, and the editor must not
 * claim otherwise (see AlertExtension and javascripts/vditor-components.js).
 */
const CALLOUT_OPEN = /^:::[ \t]*(success|info|warning|danger)[ \t]*$/i
const CALLOUT_CLOSE = /^:::[ \t]*$/

export default class {
  ace = null
  container
  cursor = {} // ace cursor with more infos
  cursorChangeCallbacks = []
  markerId = null
  langTools
  classesToIgnoreForData = [
    'markup',
    'md-extra-markup',
    'equal',
    'space',
    'attribute-quote-mark',
    'title-quote-mark',
  ]

  constructor(domElement, options = {}) {
    this.container = domElement

    // Where to find anything ACE still loads on its own (themes, ext-*, and the syntax
    // workers a mode spawns). `vendor/ace/`, where those files actually sit -- this module's
    // own directory only ever held the wrapper, so every such fetch 404'd; nothing noticed
    // until `ace/mode/css` became the first mode here with a worker to spawn.
    //
    // Resolved from this module's URL rather than the wiki's base: a farm instance serves
    // its scripts out of cache/assets/{version}/, and `${wiki.baseUrl}javascripts` points at
    // a source path that only exists on the shared code tree.
    ace.config.set('basePath', new URL('./vendor/ace/', import.meta.url).href)

    this.ace = ace.edit(domElement, {
      printMargin: false,
      // wiki prose unless the caller says otherwise: a stylesheet gets `ace/mode/css`
      mode: options.mode || 'ace/mode/yeswiki',
      showGutter: true,
      wrap: 'free',
      maxLines: Infinity,
      minLines: options.rows,
      showFoldWidgets: false,
      behavioursEnabled: true,
      wrapBehavioursEnabled: true,
      fontSize: '1rem',
      useSoftTabs: false,
      tabSize: 3,
      fontFamily: 'monospace',
      highlightActiveLine: true,
    })

    this.ace.selection.on('changeCursor', () => {
      // Timeout for the DOM to be rendered
      setTimeout(() => this.updateCursor(), 100)
    })

    // For autocompletion
    this.langTools = ace.require('ace/ext/language_tools')
    this.ace.setOptions({
      enableBasicAutocompletion: true,
      enableLiveAutocompletion: true,
    })
  }

  // Wrappers methods
  getValue() {
    return this.ace.session.getValue()
  }
  setValue(val) {
    return this.ace.session.setValue(val)
  }
  getSelectedText() {
    return this.ace.getSelectedText()
  }
  on(event, callback) {
    return this.ace.on(event, callback)
  }

  onCursorChange(callback) {
    this.cursorChangeCallbacks.push(callback)
  }

  setAutocompletionList(wordList) {
    this.langTools.setCompleters([
      {
        getCompletions(editor, session, pos, prefix, callback) {
          callback(
            null,
            wordList.map((word) => ({
              caption: word,
              value: word,
              meta: '',
            })),
          )
        },
      },
    ])
  }

  disableAutocompletion() {
    this.setAutocompletionList([])
  }

  get currentLineNodes() {
    const lineGroup = this.container.querySelector(
      `.ace_text-layer > .ace_line_group:nth-of-type(${this.cursor.row + 1})`,
    )
    const allLineNodes = []
    if (lineGroup) {
      lineGroup.querySelectorAll('.ace_line').forEach((line) => {
        line.childNodes.forEach((node) => {
          allLineNodes.push(node)
        })
      })
    }
    return allLineNodes
  }

  // Based on current cursor, detect if we are inside a specific group (like action or link),
  // and where we are in this group (are we on the action name, or in the link url?)
  // This is using the text highlightings rules. So if you want to detect a group, you need to use
  // "markup.open.GROUP_NAME" and "markup.close.GROUP_NAME" inside mode-yeswiki.js
  // Please refer to "yw-action" as an example
  updateCursor() {
    // Copy ace cursor
    this.cursor = { ...this.ace.selection.getCursor() }
    this.cursor.column ||= 0

    if (this.currentLineNodes) {
      let [currColumn, nextColumn] = [0, 0]
      this.currentLineNodes.forEach((node) => {
        // iterate until we find a full group, or reach the end of line
        if (this.cursor.groupEnd == null) {
          const nodeClasses = (node.className || '')
            .split(/\s+/)
            .map((cl) => cl.replace('ace_', ''))
          const nodeText = node.textContent || ''

          nextColumn += nodeText.length

          // Openning group markup, before finding the selectedNode
          if (!this.cursor.node && nodeClasses.includes('open')) {
            this.cursor.groupStart = currColumn
            this.cursor.groupData = {}
            this.cursor.groupStartMarkup = nodeText
            this.cursor.groupType = nodeClasses.find((cl) =>
              cl.startsWith('yw-'),
            )
          }
          // Collect data for each node
          if (this.cursor.groupStart !== undefined) {
            const type = nodeClasses.find(
              (cl) => !this.classesToIgnoreForData.includes(cl),
            )
            if (type) this.cursor.groupData[type] = nodeText
          }
          // Detect SelectedNode
          if (
            !this.cursor.node &&
            nodeText &&
            currColumn <= this.cursor.column &&
            nextColumn >= this.cursor.column
          ) {
            this.cursor.node = node
            this.cursor.nodeStart = currColumn
            this.cursor.nodeEnd = nextColumn
            this.cursor.nodeText = nodeText
            this.cursor.nodeType = nodeClasses
          }
          // Closing group markup, after finding selectedNode
          if (
            this.cursor.node &&
            this.cursor.groupStart !== undefined &&
            nodeClasses.includes('close') &&
            nodeClasses.includes(this.cursor.groupType)
          ) {
            this.cursor.groupEnd = nextColumn
            this.cursor.groupEndMarkup = nodeText
            this.cursor.groupText = this.currentGroupText
            this.cursor.groupTextWithoutMarkup =
              this.currentGroupTextwithoutMarkup
          }
          currColumn = nextColumn
        }
      })
    }
    // Trigger callbacks
    this.cursorChangeCallbacks.forEach((callback) => {
      callback(this.cursor)
    })
  }

  textFromRange(row, colStart, colEnd) {
    return this.ace.session.getTextRange(
      new ace.Range(row, colStart, row, colEnd + 1),
    )
  }

  get currentGroupText() {
    return this.textFromRange(
      this.cursor.row,
      this.cursor.groupStart,
      this.cursor.groupEnd - 1,
    )
  }

  get currentGroupTextwithoutMarkup() {
    return this.currentGroupText
      .replace(
        new RegExp(`^${this.escapeRegExp(this.cursor.groupStartMarkup)}`),
        '',
      )
      .replace(
        new RegExp(`${this.escapeRegExp(this.cursor.groupEndMarkup)}$`),
        '',
      )
      .trim()
  }

  // Espace characters to be used in reg expr
  escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  }

  selectLineRange(row, colStart, colEnd) {
    this.ace.selection.setRange(new ace.Range(row, colStart, row, colEnd))
  }

  selectCurrentNode() {
    this.selectLineRange(
      this.cursor.row,
      this.cursor.nodeStart,
      this.cursor.nodeEnd,
    )
  }

  selectCurrentGroup() {
    this.selectLineRange(
      this.cursor.row,
      this.cursor.groupStart,
      this.cursor.groupEnd,
    )
  }

  selectCurrentGroupAfterEdit() {
    setTimeout(() => {
      this.updateCursor()
      this.selectCurrentGroup()
    }, 0)
  }

  surroundSelectionWith(left = '', right = '') {
    this.ace.session.replace(
      this.ace.getSelectionRange(),
      left + this.ace.getSelectedText() + right,
    )
    this.ace.focus()
  }

  replaceSelectionBy(replacement, selectGroup = true) {
    this.ace.session.replace(this.ace.getSelectionRange(), replacement)
    if (selectGroup) this.selectCurrentGroupAfterEdit()
  }

  replaceCurrentNodeBy(text) {
    this.selectCurrentNode()
    this.replaceSelectionBy(text, false)
    this.ace.focus()
  }

  replaceCurrentGroupBy(text) {
    this.selectCurrentGroup()
    this.replaceSelectionBy(text)
    this.selectCurrentGroupAfterEdit()
  }

  /** Returns the range the text landed in, so the rail can keep editing what it inserted. */
  insert(text) {
    const start = this.ace.getCursorPosition()
    this.ace.insert(text)
    const end = this.ace.getCursorPosition()
    this.selectCurrentGroupAfterEdit()

    return new ace.Range(start.row, start.column, end.row, end.column)
  }

  lineLength(row) {
    return this.ace.session.getLine(row).length
  }

  /** A range on one line. The rails describe what they are on with these. */
  rangeFor(row, colStart, colEnd) {
    return new ace.Range(row, colStart, row, colEnd)
  }

  selectionRange() {
    return this.ace.getSelectionRange()
  }

  /**
   * Replace a range decided earlier rather than whatever is selected now. A rail is not a
   * modal: between opening it and pressing its button, the cursor is free to wander, and
   * what the rail rewrites has to be what it was opened on.
   */
  /**
   * @returns the range the replacement now occupies, so a caller that replaces the same
   *   span again knows where it moved to. The rail does exactly that: it writes on every
   *   change now rather than once on a button, and the second write against the range the
   *   first one was measured from would land on the wrong span of text -- the tag having
   *   changed length under it.
   */
  replaceRange(range, text) {
    if (!range) return this.replaceCurrentGroupBy(text)
    this.ace.selection.setRange(range)
    const { start } = this.ace.getSelectionRange()
    const end = this.ace.session.replace(this.ace.getSelectionRange(), text)
    this.selectCurrentGroupAfterEdit()

    return new ace.Range(start.row, start.column, end.row, end.column)
  }

  /**
   * Tint what a rail is open on, so that the panel on the right and the text on the left
   * obviously refer to the same thing.
   *
   * A marker rather than a selection: a selection is something the next keystroke wipes
   * out, and this has to survive typing inside the very thing it points at.
   */
  highlightRange(range) {
    this.clearHighlight()
    if (!range) return
    this.markerId = this.ace.session.addMarker(range, 'yw-active-group', 'text')
  }

  clearHighlight() {
    if (this.markerId === null) return
    this.ace.session.removeMarker(this.markerId)
    this.markerId = null
  }

  /**
   * The tag that a `{{end elem="X"}}` closes: the nearest `{{X ...}}` above it that is not
   * already closed in between, so that a grid inside a grid resolves to the one it really
   * belongs to. Null when there is none -- an unbalanced `end` is not this method's
   * problem to report.
   *
   * A scan of the text rather than a walk of the highlighter's tokens: ace tokenises the
   * line the cursor is on, and the opening tag can be any number of lines above it.
   */
  findOpeningTag(elem, fromRow, fromColumn) {
    const closesElem = new RegExp(
      `elem\\s*=\\s*["']${this.escapeRegExp(elem)}["']`,
    )
    let depth = 0
    for (let row = fromRow; row >= 0; row -= 1) {
      const tags = [
        ...this.ace.session.getLine(row).matchAll(/\{\{\s*(\w+)([^}]*)\}\}/g),
      ]
        // on the row the `end` is on, only what is written before it can open it
        .filter(
          (tag) => row < fromRow || tag.index + tag[0].length <= fromColumn,
        )
      for (let i = tags.length - 1; i >= 0; i -= 1) {
        const [markup, name, attributes] = tags[i]
        if (name === 'end' && closesElem.test(attributes)) {
          depth += 1
        } else if (name === elem) {
          if (depth === 0) {
            return {
              name,
              text: markup.slice(2, -2).trim(),
              range: this.rangeFor(
                row,
                tags[i].index,
                tags[i].index + markup.length,
              ),
            }
          }
          depth -= 1
        }
      }
    }
    return null
  }

  /**
   * Insert somewhere other than where the cursor happens to be. The actions builder
   * decides where a new component goes when it opens -- the cursor may well have moved
   * on by the time the user is done configuring it.
   */
  /**
   * The `:::info … :::` callout the cursor is in, as row numbers, or null.
   *
   * Scanned rather than parsed: the document is text here, and the two fences are lines.
   * A closing fence found on the way up means the cursor is BELOW a block rather than in
   * one, which is the case that would otherwise retype somebody else's callout.
   */
  calloutAt(row) {
    const session = this.ace.session
    let start = -1
    for (let r = row; r >= 0; r -= 1) {
      const line = session.getLine(r)
      if (r !== row && CALLOUT_CLOSE.test(line)) return null
      if (CALLOUT_OPEN.test(line)) {
        start = r
        break
      }
    }
    if (start === -1) return null
    for (let r = Math.max(start + 1, row); r < session.getLength(); r += 1) {
      if (CALLOUT_CLOSE.test(session.getLine(r))) return { start, end: r }
    }

    return null
  }

  /**
   * Make the text at the cursor a callout of this type -- or, if it already is one,
   * make it that type instead.
   *
   * Three cases, and they are the three a format button has always had: something is
   * selected, so wrap it; nothing is, so wrap the paragraph the cursor is in; it is
   * already formatted, so change it rather than nesting a second one inside the first.
   */
  applyCallout(type) {
    const cursor = this.ace.getCursorPosition()
    const existing = this.calloutAt(cursor.row)
    if (existing) {
      this.ace.session.replace(
        new ace.Range(
          existing.start,
          0,
          existing.start,
          this.lineLength(existing.start),
        ),
        `:::${type}`,
      )
      this.ace.focus()

      return
    }

    if (this.ace.getSelectedText()) {
      this.surroundSelectionWith(`:::${type}\n`, '\n:::')

      return
    }

    // the paragraph the cursor is in: contiguous non-blank lines around it
    const session = this.ace.session
    let first = cursor.row
    let last = cursor.row
    while (first > 0 && session.getLine(first - 1).trim() !== '') first -= 1
    while (
      last < session.getLength() - 1 &&
      session.getLine(last + 1).trim() !== ''
    )
      last += 1

    this.ace.selection.setRange(
      new ace.Range(first, 0, last, this.lineLength(last)),
    )
    this.surroundSelectionWith(`:::${type}\n`, '\n:::')
  }

  insertAt(position, text) {
    if (!position) return this.insert(text)
    this.ace.moveCursorTo(position.row, position.column)
    this.ace.clearSelection()
    // the newline goes in first, so that the range handed back covers the tag and not the
    // break before it -- rewriting that range would otherwise eat the newline and weld the
    // component onto the line above
    if (position.onNewLine) this.insert('\n')

    return this.insert(text)
  }
}
