import * as aceModule from '../../../../javascripts/vendor/ace/ace.js'
// Loads html rules cause it's used inside yeswiki mode
import * as aceModeHtml from '../../../../javascripts/vendor/ace/mode-html.js'
import * as language_tools from '../../../../javascripts/vendor/ace/ext-language_tools.js'

export default class {
  ace = null
  $container
  cursor = {} // ace cursor with more infos
  cursorChangeCallbacks = []
  langTools
  classesToIgnoreForData = ['markup', 'md-extra-markup', 'equal', 'space',
    'attribute-quote-mark', 'title-quote-mark']

  constructor(domElement, options = {}) {
    this.$container = $(domElement)

    // Where to find the 'mode-XXXX' files
    ace.config.set('basePath', 'tools/aceditor/presentation/javascripts')

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
    const $renderedLineGroup = this.$container
      .find(`.ace_text-layer > .ace_line_group:nth-of-type(${this.cursor.row + 1})`)
    const allLineNodes = []
    $renderedLineGroup.find('.ace_line').each(function() {
      this.childNodes.forEach((node) => {
        allLineNodes.push(node)
      })
    })
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
          const $node = $(node)
          const nodeClasses = ($node.attr('class') || '')
            .split(/\s+/).map((cl) => cl.replace('ace_', ''))

          nextColumn += $node.text().length

          // Openning group markup, before finding the selectedNode
          if (!this.cursor.$node && nodeClasses.includes('open')) {
            this.cursor.groupStart = currColumn
            this.cursor.groupData = {}
            this.cursor.groupStartMarkup = $node.text()
            this.cursor.groupType = nodeClasses.find((cl) => cl.startsWith('yw-'))
          }
          // Collect data for each node
          if (this.cursor.groupStart !== undefined) {
            const type = nodeClasses.find((cl) => !this.classesToIgnoreForData.includes(cl))
            if (type) this.cursor.groupData[type] = $node.text()
          }
          // Detect SelectedNode
          if (!this.cursor.$node && $node.text()
              && currColumn <= this.cursor.column && nextColumn >= this.cursor.column) {
            this.cursor.$node = $node
            this.cursor.nodeStart = currColumn
            this.cursor.nodeEnd = nextColumn
            this.cursor.nodeText = $node.text()
            this.cursor.nodeType = nodeClasses
          }
          // Closing group markup, after finding selectedNode
          if (this.cursor.$node && this.cursor.groupStart !== undefined
              && nodeClasses.includes('close') && nodeClasses.includes(this.cursor.groupType)) {
            this.cursor.groupEnd = nextColumn
            this.cursor.groupEndMarkup = $node.text()
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
}
