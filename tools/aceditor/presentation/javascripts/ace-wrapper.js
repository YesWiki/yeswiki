import '../../../../javascripts/vendor/ace/ace.js'
import '../../../../javascripts/vendor/ace/mode-html.js'
import '../../../../javascripts/vendor/ace/ext-language_tools.js'

export default class {
  ace = null
  $container
  cursor = {}
  cursorChangeCallbacks = []
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
    this.$container = $(domElement)

    ace.config.set(
      'basePath',
      `${wiki.baseUrl.replace(/\?+$/, '')}tools/aceditor/presentation/javascripts`,
    )
    ace.config.setModuleUrl(
      'ace/mode/yeswiki',
      import.meta.resolve('./mode-yeswiki.js'),
    )

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
      highlightActiveLine: true,
    })

    this.ace.selection.on('changeCursor', () => {
      setTimeout(() => this.updateCursor(), 100)
    })

    this.langTools = ace.require('ace/ext/language_tools')
    this.ace.setOptions({
      enableBasicAutocompletion: true,
      enableLiveAutocompletion: true,
    })

    this.enableMiddleClickPaste()
  }

  /** Puts ACE's hidden textarea under the pointer on a middle click, so the primary selection pastes into it. */
  enableMiddleClickPaste() {
    const { container, textInput } = this.ace
    container.addEventListener(
      'mousedown',
      (e) => {
        if (e.button !== 1) return
        textInput.moveToMouse(e, true)
        this.ace.focus()
      },
      true,
    )
    container.addEventListener('mouseup', (e) => {
      if (e.button === 1) textInput.onContextMenuClose()
    })
  }

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
    const $renderedLineGroup = this.$container.find(
      `.ace_text-layer > .ace_line_group:nth-of-type(${this.cursor.row + 1})`,
    )
    const allLineNodes = []
    $renderedLineGroup.find('.ace_line').each(function () {
      this.childNodes.forEach((node) => {
        allLineNodes.push(node)
      })
    })
    return allLineNodes
  }

  /** Finds the group (action, link...) around the cursor from the markup.open/close.GROUP tokens of mode-yeswiki.js. */
  updateCursor() {
    this.cursor = { ...this.ace.selection.getCursor() }
    this.cursor.column ||= 0

    if (this.currentLineNodes) {
      let [currColumn, nextColumn] = [0, 0]
      this.currentLineNodes.forEach((node) => {
        if (this.cursor.groupEnd == null) {
          const $node = $(node)
          const nodeClasses = ($node.attr('class') || '')
            .split(/\s+/)
            .map((cl) => cl.replace('ace_', ''))

          nextColumn += $node.text().length

          if (!this.cursor.$node && nodeClasses.includes('open')) {
            this.cursor.groupStart = currColumn
            this.cursor.groupData = {}
            this.cursor.groupStartMarkup = $node.text()
            this.cursor.groupType = nodeClasses.find((cl) =>
              cl.startsWith('yw-'),
            )
          }
          if (this.cursor.groupStart !== undefined) {
            const type = nodeClasses.find(
              (cl) => !this.classesToIgnoreForData.includes(cl),
            )
            if (type) this.cursor.groupData[type] = $node.text()
          }
          if (
            !this.cursor.$node &&
            $node.text() &&
            currColumn <= this.cursor.column &&
            nextColumn >= this.cursor.column
          ) {
            this.cursor.$node = $node
            this.cursor.nodeStart = currColumn
            this.cursor.nodeEnd = nextColumn
            this.cursor.nodeText = $node.text()
            this.cursor.nodeType = nodeClasses
          }
          if (
            this.cursor.$node &&
            this.cursor.groupStart !== undefined &&
            nodeClasses.includes('close') &&
            nodeClasses.includes(this.cursor.groupType)
          ) {
            this.cursor.groupEnd = nextColumn
            this.cursor.groupEndMarkup = $node.text()
            this.cursor.groupText = this.currentGroupText
            this.cursor.groupTextWithoutMarkup =
              this.currentGroupTextwithoutMarkup
          }
          currColumn = nextColumn
        }
      })
    }
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

  /** Escapes a string for use inside a regular expression. */
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

  insert(text) {
    this.ace.insert(text)
    this.selectCurrentGroupAfterEdit()
  }
}
