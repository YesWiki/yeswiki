// Side-effect only: each self-registers into the global `ace` via ace.define(...)
import './vendor/ace/ace.js'
// Loads html rules cause it's used inside yeswiki mode
import './vendor/ace/mode-html.js'
import './vendor/ace/ext-language_tools.js'

export default class {
  ace = null
  container
  cursor = {} // ace cursor with more infos
  cursorChangeCallbacks = []
  markerId = null
  langTools
  classesToIgnoreForData = ['markup', 'md-extra-markup', 'equal', 'space',
    'attribute-quote-mark', 'title-quote-mark']

  constructor(domElement, options = {}) {
    this.container = domElement

    // Where to find the 'mode-XXXX' files
    ace.config.set('basePath', `${wiki.baseUrl.replace(/\?+$/, '')}javascripts`)

    this.ace = ace.edit(domElement, {
      printMargin: false,
      mode: 'ace/mode/yeswiki',
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
      highlightActiveLine: true
    })

    this.ace.selection.on('changeCursor', () => {
      // Timeout for the DOM to be rendered
      setTimeout(() => this.updateCursor(), 100)
    })

    // For autocompletion
    this.langTools = ace.require('ace/ext/language_tools')
    this.ace.setOptions({
      enableBasicAutocompletion: true,
      enableLiveAutocompletion: true
    })
  }

  // Wrappers methods
  getValue() { return this.ace.session.getValue() }
  setValue(val) { return this.ace.session.setValue(val) }
  getSelectedText() { return this.ace.getSelectedText() }
  on(event, callback) { return this.ace.on(event, callback) }

  onCursorChange(callback) {
    this.cursorChangeCallbacks.push(callback)
  }

  setAutocompletionList(wordList) {
    this.langTools.setCompleters([{
      getCompletions(editor, session, pos, prefix, callback) {
        callback(null, wordList.map((word) => ({
          caption: word,
          value: word,
          meta: ''
        })))
      }
    }])
  }

  disableAutocompletion() {
    this.setAutocompletionList([])
  }

  get currentLineNodes() {
    const lineGroup = this.container
      .querySelector(`.ace_text-layer > .ace_line_group:nth-of-type(${this.cursor.row + 1})`)
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
            .split(/\s+/).map((cl) => cl.replace('ace_', ''))
          const nodeText = node.textContent || ''

          nextColumn += nodeText.length

          // Openning group markup, before finding the selectedNode
          if (!this.cursor.node && nodeClasses.includes('open')) {
            this.cursor.groupStart = currColumn
            this.cursor.groupData = {}
            this.cursor.groupStartMarkup = nodeText
            this.cursor.groupType = nodeClasses.find((cl) => cl.startsWith('yw-'))
          }
          // Collect data for each node
          if (this.cursor.groupStart !== undefined) {
            const type = nodeClasses.find((cl) => !this.classesToIgnoreForData.includes(cl))
            if (type) this.cursor.groupData[type] = nodeText
          }
          // Detect SelectedNode
          if (!this.cursor.node && nodeText
              && currColumn <= this.cursor.column && nextColumn >= this.cursor.column) {
            this.cursor.node = node
            this.cursor.nodeStart = currColumn
            this.cursor.nodeEnd = nextColumn
            this.cursor.nodeText = nodeText
            this.cursor.nodeType = nodeClasses
          }
          // Closing group markup, after finding selectedNode
          if (this.cursor.node && this.cursor.groupStart !== undefined
              && nodeClasses.includes('close') && nodeClasses.includes(this.cursor.groupType)) {
            this.cursor.groupEnd = nextColumn
            this.cursor.groupEndMarkup = nodeText
            this.cursor.groupText = this.currentGroupText
            this.cursor.groupTextWithoutMarkup = this.currentGroupTextwithoutMarkup
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
    return this.ace.session.getTextRange(new ace.Range(row, colStart, row, colEnd + 1))
  }

  get currentGroupText() {
    return this.textFromRange(this.cursor.row, this.cursor.groupStart, this.cursor.groupEnd - 1)
  }

  get currentGroupTextwithoutMarkup() {
    return this.currentGroupText
      .replace(new RegExp(`^${this.escapeRegExp(this.cursor.groupStartMarkup)}`), '')
      .replace(new RegExp(`${this.escapeRegExp(this.cursor.groupEndMarkup)}$`), '')
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
    this.selectLineRange(this.cursor.row, this.cursor.nodeStart, this.cursor.nodeEnd)
  }

  selectCurrentGroup() {
    this.selectLineRange(this.cursor.row, this.cursor.groupStart, this.cursor.groupEnd)
  }

  selectCurrentGroupAfterEdit() {
    setTimeout(() => {
      this.updateCursor()
      this.selectCurrentGroup()
    }, 0)
  }

  surroundSelectionWith(left = '', right = '') {
    this.ace.session.replace(this.ace.getSelectionRange(), left + this.ace.getSelectedText() + right)
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

  insert(text) {
    this.ace.insert(text)
    this.selectCurrentGroupAfterEdit()
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
  replaceRange(range, text) {
    if (!range) return this.replaceCurrentGroupBy(text)
    this.ace.selection.setRange(range)
    return this.replaceSelectionBy(text)
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
    const closesElem = new RegExp(`elem\\s*=\\s*["']${this.escapeRegExp(elem)}["']`)
    let depth = 0
    for (let row = fromRow; row >= 0; row -= 1) {
      const tags = [...this.ace.session.getLine(row).matchAll(/\{\{\s*(\w+)([^}]*)\}\}/g)]
        // on the row the `end` is on, only what is written before it can open it
        .filter((tag) => row < fromRow || tag.index + tag[0].length <= fromColumn)
      for (let i = tags.length - 1; i >= 0; i -= 1) {
        const [markup, name, attributes] = tags[i]
        if (name === 'end' && closesElem.test(attributes)) {
          depth += 1
        } else if (name === elem) {
          if (depth === 0) {
            return {
              name,
              text: markup.slice(2, -2).trim(),
              range: this.rangeFor(row, tags[i].index, tags[i].index + markup.length)
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
  insertAt(position, text) {
    if (!position) return this.insert(text)
    this.ace.moveCursorTo(position.row, position.column)
    this.ace.clearSelection()
    return this.insert(position.onNewLine ? `\n${text}` : text)
  }
}
