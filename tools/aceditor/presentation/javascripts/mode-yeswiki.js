// ===================================
//     YESWIKI HIGHTLIGHT RULES
// ====================================
ace.define('ace/mode/yeswiki_highlight_rules', ['require', 'exports', 'module', 'ace/lib/oop', 'ace/mode/text_highlight_rules', 'ace/mode/html_highlight_rules'], (require, exports, module) => {
  const oop = require('../lib/oop')
  const { TextHighlightRules } = require('./text_highlight_rules')
  const { HtmlHighlightRules } = require('./html_highlight_rules')

  const markdownLink = { // link markdown
    token: ['markup.open.yw-link-markdown', 'link-text', 'markup', 'markup', 'link-url',
      'space', 'title-quote-mark', 'link-title', 'title-quote-mark'],
    regex: '(\\[)([^\\]]*)(\\])(\\()([^\\)\\s]*)(\\s?)("?)([^\\)"]*)("?)',
    next: 'md-extra'
  }

  const YesWikiHighlightRules = function() {
    this.$rules = {
      start: [{
        token: 'markup.open.yw-action',
        regex: '\\{\\{',
        next: 'yw-action'
      }, {
        token: 'markup.html',
        regex: '""',
        next: 'html-start'
      }, {
        token: 'constant.language.escape',
        regex: /\\[\\`*_{}\[\]()#+\-.!]/
      }, { // pre //
        token: 'markup.pre',
        regex: '([%]{2})',
        next: 'pre-start'
      }, { // headings //
        token: 'markup.headings.1',
        regex: '([=]{6}(?=\\S))(.*?\\S[=]*)(\\1)'
      }, { // headings //
        token: 'markup.headings.2',
        regex: '([=]{5}(?=\\S))(.*?\\S[=]*)(\\1)'
      }, { // headings //
        token: 'markup.headings.3',
        regex: '([=]{4}(?=\\S))(.*?\\S[=]*)(\\1)'
      }, { // headings //
        token: 'markup.headings.4',
        regex: '([=]{3}(?=\\S))(.*?\\S[=]*)(\\1)'
      }, { // headings //
        token: 'markup.headings.5',
        regex: '([=]{2}(?=\\S))(.*?\\S[=]*)(\\1)'
      }, {
        token: 'empty_line',
        regex: '^$',
        next: 'allowBlock'
      },
      { // HR ----
        token: 'constant.hr',
        regex: '^[-]{3,50}$',
        next: 'allowBlock'
      }, { // list ( - or 1. )
        token: 'markup.list',
        regex: '^\\s{1,3}(?:-|\\d+\\.)\\s+'
      }, markdownLink, {
        include: 'basic',
        noEscape: true
      }],
      basic: [
        { // strong ** __
          token: 'bold',
          regex: '([*]{2}(?=.*?))(.*?[*]*)(\\1)'
        }, { // italic //
          token: 'italic',
          regex: '([/]{2}(?=.*?))(.*?[/]*)(\\1)'
        }, { // italic markdown _
          token: 'italic',
          regex: '_([^_]+)_'
        }, { // italic markdown *
          token: 'italic',
          regex: '\\*([^\\*]+)\\*(?!\w)'
        }, { // underline __
          token: 'underline',
          regex: '([_]{2}(?=.*?))(.*?[_]*)(\\1)'
        }, { // stroke @@
          token: 'stroke',
          regex: '([@]{2}(?=.*?))(.*?[@]*)(\\1)'
        }, { // link
          token: ['markup.open.yw-link', 'link-url', 'space', 'link-text', 'markup.close.yw-link'],
          regex: '(\\[\\[)([^\\s]*)(\\s?)([^\\]]*)(\\]\\])'
        }
      ],
      allowBlock: [
        { token: 'support.function', regex: '^ {4}.+', next: 'allowBlock' },
        { token: 'empty_line', regex: '^$', next: 'allowBlock' },
        { token: 'empty', regex: '', next: 'start' }
      ],
      'md-extra': [{
        token: ['markup', 'md-extra-markup', 'md-extra', 'md-extra-markup.close.yw-link-markdown'],
        regex: '(\\))({)([^\\)}"]*)(})',
        next: 'start'
      }, {
        token: 'markup.close.yw-link-markdown',
        regex: '\\)',
        next: 'start'
      }],
      'yw-action': [{
        token: 'action-name',
        regex: '[a-zA-Z0-9-_]+',
        next: 'yw-action-attributes'
      }],
      'yw-action-attributes': [{
        token: ['space', 'attribute-name', 'equal', 'attribute-quote-mark', 'attribute-value',
          'attribute-quote-mark'],
        regex: '(\\s)([-_a-zA-Z0-9]+)(=)(")([^"]*)(")'
      }, {
        token: 'markup.close.yw-action',
        regex: '\\}\\}',
        next: 'start'
      }],
      'pre-start': [
        { // pre //
          token: 'markup.pre',
          regex: '([%]{2})',
          next: 'start'
        },
        {
          token: 'pre',
          regex: '[^%]*'
        }]
    }

    this.embedRules(HtmlHighlightRules, 'html-', [
      {
        token: 'markup.html',
        regex: '""',
        next: 'start'
      }
    ])

    this.normalizeRules()
  }

  oop.inherits(YesWikiHighlightRules, TextHighlightRules)

  exports.YesWikiHighlightRules = YesWikiHighlightRules
})

// ===================================
//       YESWIKI BEHAVIOUR
// ====================================
// custom ace behaviour, so when we type {, the closing } is auto inserted
// This code is copied from Cstyle behaviour : https://raw.githubusercontent.com/ajaxorg/ace/23208f2f19020d1f69b90bc3b02460bda8422072/src/mode/behaviour/cstyle.js
// We just removed the rule "braces" and added the rule "braces2"
ace.define('ace/mode/yeswiki_behaviour', ['require', 'exports', 'module', 'ace/lib/oop', 'ace/mode/text_highlight_rules', 'ace/mode/html_highlight_rules'], (require, exports, module) => {
  const oop = require('../lib/oop')
  const { CstyleBehaviour } = require('./behaviour/cstyle')
  const { TokenIterator } = require('../token_iterator')

  const SAFE_INSERT_IN_TOKENS = ['text', 'paren.rparen', 'rparen', 'paren', 'punctuation.operator']
  const SAFE_INSERT_BEFORE_TOKENS = ['text', 'paren.rparen', 'rparen', 'paren', 'punctuation.operator', 'comment']

  let context
  let contextCache = {}
  const defaultQuotes = { '"': '"', "'": "'" }

  const initContext = function(editor) {
    let id = -1
    if (editor.multiSelect) {
      id = editor.selection.index
      if (contextCache.rangeCount != editor.multiSelect.rangeCount) contextCache = { rangeCount: editor.multiSelect.rangeCount }
    }
    if (contextCache[id]) return context = contextCache[id]
    context = contextCache[id] = {
      autoInsertedBrackets: 0,
      autoInsertedRow: -1,
      autoInsertedLineEnd: '',
      maybeInsertedBrackets: 0,
      maybeInsertedRow: -1,
      maybeInsertedLineStart: '',
      maybeInsertedLineEnd: ''
    }
  }

  const getWrapped = function(selection, selected, opening, closing) {
    const rowDiff = selection.end.row - selection.start.row
    return {
      text: opening + selected + closing,
      selection: [
        0,
        selection.start.column + 1,
        rowDiff,
        selection.end.column + (rowDiff ? 0 : 1)
      ]
    }
  }

  const YesWikiBehaviour = function(options) {
    this.add('braces2', 'insertion', (state, action, editor, session, text) => {
      if (text == '{') {
        initContext(editor)
        const selection = editor.getSelectionRange()
        const selected = session.doc.getTextRange(selection)
        if (selected !== '' && editor.getWrapBehavioursEnabled()) {
          return getWrapped(selection, selected, '[', ']')
        } if (YesWikiBehaviour.isSaneInsertion(editor, session)) {
          YesWikiBehaviour.recordAutoInsert(editor, session, ']')
          return {
            text: '{}',
            selection: [1, 1]
          }
        }
      } else if (text == '}') {
        initContext(editor)
        const cursor = editor.getCursorPosition()
        const line = session.doc.getLine(cursor.row)
        const rightChar = line.substring(cursor.column, cursor.column + 1)
        if (rightChar == '}') {
          const matching = session.$findOpeningBracket(']', { column: cursor.column + 1, row: cursor.row })
          if (matching !== null && YesWikiBehaviour.isAutoInsertedClosing(cursor, line, text)) {
            YesWikiBehaviour.popAutoInsertedClosing()
            return {
              text: '',
              selection: [1, 1]
            }
          }
        }
      }
    })

    this.add('braces2', 'deletion', (state, action, editor, session, range) => {
      const selected = session.doc.getTextRange(range)
      if (!range.isMultiLine() && selected == '[') {
        initContext(editor)
        const line = session.doc.getLine(range.start.row)
        const rightChar = line.substring(range.start.column + 1, range.start.column + 2)
        if (rightChar == ']') {
          range.end.column++
          return range
        }
      }
    })

    this.add('parens', 'insertion', (state, action, editor, session, text) => {
      if (text == '(') {
        initContext(editor)
        const selection = editor.getSelectionRange()
        const selected = session.doc.getTextRange(selection)
        if (selected !== '' && editor.getWrapBehavioursEnabled()) {
          return getWrapped(selection, selected, '(', ')')
        } if (YesWikiBehaviour.isSaneInsertion(editor, session)) {
          YesWikiBehaviour.recordAutoInsert(editor, session, ')')
          return {
            text: '()',
            selection: [1, 1]
          }
        }
      } else if (text == ')') {
        initContext(editor)
        const cursor = editor.getCursorPosition()
        const line = session.doc.getLine(cursor.row)
        const rightChar = line.substring(cursor.column, cursor.column + 1)
        if (rightChar == ')') {
          const matching = session.$findOpeningBracket(')', { column: cursor.column + 1, row: cursor.row })
          if (matching !== null && YesWikiBehaviour.isAutoInsertedClosing(cursor, line, text)) {
            YesWikiBehaviour.popAutoInsertedClosing()
            return {
              text: '',
              selection: [1, 1]
            }
          }
        }
      }
    })

    this.add('parens', 'deletion', (state, action, editor, session, range) => {
      const selected = session.doc.getTextRange(range)
      if (!range.isMultiLine() && selected == '(') {
        initContext(editor)
        const line = session.doc.getLine(range.start.row)
        const rightChar = line.substring(range.start.column + 1, range.start.column + 2)
        if (rightChar == ')') {
          range.end.column++
          return range
        }
      }
    })

    this.add('brackets', 'insertion', (state, action, editor, session, text) => {
      if (text == '[') {
        initContext(editor)
        const selection = editor.getSelectionRange()
        const selected = session.doc.getTextRange(selection)
        if (selected !== '' && editor.getWrapBehavioursEnabled()) {
          return getWrapped(selection, selected, '[', ']')
        } if (YesWikiBehaviour.isSaneInsertion(editor, session)) {
          YesWikiBehaviour.recordAutoInsert(editor, session, ']')
          return {
            text: '[]',
            selection: [1, 1]
          }
        }
      } else if (text == ']') {
        initContext(editor)
        const cursor = editor.getCursorPosition()
        const line = session.doc.getLine(cursor.row)
        const rightChar = line.substring(cursor.column, cursor.column + 1)
        if (rightChar == ']') {
          const matching = session.$findOpeningBracket(']', { column: cursor.column + 1, row: cursor.row })
          if (matching !== null && YesWikiBehaviour.isAutoInsertedClosing(cursor, line, text)) {
            YesWikiBehaviour.popAutoInsertedClosing()
            return {
              text: '',
              selection: [1, 1]
            }
          }
        }
      }
    })

    this.add('brackets', 'deletion', (state, action, editor, session, range) => {
      const selected = session.doc.getTextRange(range)
      if (!range.isMultiLine() && selected == '[') {
        initContext(editor)
        const line = session.doc.getLine(range.start.row)
        const rightChar = line.substring(range.start.column + 1, range.start.column + 2)
        if (rightChar == ']') {
          range.end.column++
          return range
        }
      }
    })

    this.add('string_dquotes', 'insertion', function(state, action, editor, session, text) {
      const quotes = session.$mode.$quotes || defaultQuotes
      if (text.length == 1 && quotes[text]) {
        if (this.lineCommentStart && this.lineCommentStart.indexOf(text) != -1) return
        initContext(editor)
        const quote = text
        const selection = editor.getSelectionRange()
        const selected = session.doc.getTextRange(selection)
        if (selected !== '' && (selected.length != 1 || !quotes[selected]) && editor.getWrapBehavioursEnabled()) {
          return getWrapped(selection, selected, quote, quote)
        } if (!selected) {
          const cursor = editor.getCursorPosition()
          const line = session.doc.getLine(cursor.row)
          const leftChar = line.substring(cursor.column - 1, cursor.column)
          const rightChar = line.substring(cursor.column, cursor.column + 1)

          const token = session.getTokenAt(cursor.row, cursor.column)
          const rightToken = session.getTokenAt(cursor.row, cursor.column + 1)
          // We're escaped.
          if (leftChar == '\\' && token && /escape/.test(token.type)) return null

          const stringBefore = token && /string|escape/.test(token.type)
          const stringAfter = !rightToken || /string|escape/.test(rightToken.type)

          let pair
          if (rightChar == quote) {
            pair = stringBefore !== stringAfter
            if (pair && /string\.end/.test(rightToken.type)) pair = false
          } else {
            if (stringBefore && !stringAfter) return null // wrap string with different quote
            if (stringBefore && stringAfter) return null // do not pair quotes inside strings
            const wordRe = session.$mode.tokenRe
            wordRe.lastIndex = 0
            const isWordBefore = wordRe.test(leftChar)
            wordRe.lastIndex = 0
            const isWordAfter = wordRe.test(leftChar)
            if (isWordBefore || isWordAfter) return null // before or after alphanumeric
            if (rightChar && !/[\s;,.})\]\\]/.test(rightChar)) return null // there is rightChar and it isn't closing
            const charBefore = line[cursor.column - 2]
            if (leftChar == quote && (charBefore == quote || wordRe.test(charBefore))) return null
            pair = true
          }
          return {
            text: pair ? quote + quote : '',
            selection: [1, 1]
          }
        }
      }
    })

    this.add('string_dquotes', 'deletion', (state, action, editor, session, range) => {
      const quotes = session.$mode.$quotes || defaultQuotes

      const selected = session.doc.getTextRange(range)
      if (!range.isMultiLine() && quotes.hasOwnProperty(selected)) {
        initContext(editor)
        const line = session.doc.getLine(range.start.row)
        const rightChar = line.substring(range.start.column + 1, range.start.column + 2)
        if (rightChar == selected) {
          range.end.column++
          return range
        }
      }
    })
  }

  YesWikiBehaviour.isSaneInsertion = function(editor, session) {
    const cursor = editor.getCursorPosition()
    const iterator = new TokenIterator(session, cursor.row, cursor.column)

    // Don't insert in the middle of a keyword/identifier/lexical
    if (!this.$matchTokenType(iterator.getCurrentToken() || 'text', SAFE_INSERT_IN_TOKENS)) {
      if (/[)}\]]/.test(editor.session.getLine(cursor.row)[cursor.column])) return true
      // Look ahead in case we're at the end of a token
      const iterator2 = new TokenIterator(session, cursor.row, cursor.column + 1)
      if (!this.$matchTokenType(iterator2.getCurrentToken() || 'text', SAFE_INSERT_IN_TOKENS)) return false
    }

    // Only insert in front of whitespace/comments
    iterator.stepForward()
    return iterator.getCurrentTokenRow() !== cursor.row
      || this.$matchTokenType(iterator.getCurrentToken() || 'text', SAFE_INSERT_BEFORE_TOKENS)
  }

  YesWikiBehaviour.$matchTokenType = function(token, types) {
    return types.indexOf(token.type || token) > -1
  }

  YesWikiBehaviour.recordAutoInsert = function(editor, session, bracket) {
    const cursor = editor.getCursorPosition()
    const line = session.doc.getLine(cursor.row)
    // Reset previous state if text or context changed too much
    if (!this.isAutoInsertedClosing(cursor, line, context.autoInsertedLineEnd[0])) context.autoInsertedBrackets = 0
    context.autoInsertedRow = cursor.row
    context.autoInsertedLineEnd = bracket + line.substr(cursor.column)
    context.autoInsertedBrackets++
  }

  YesWikiBehaviour.recordMaybeInsert = function(editor, session, bracket) {
    const cursor = editor.getCursorPosition()
    const line = session.doc.getLine(cursor.row)
    if (!this.isMaybeInsertedClosing(cursor, line)) context.maybeInsertedBrackets = 0
    context.maybeInsertedRow = cursor.row
    context.maybeInsertedLineStart = line.substr(0, cursor.column) + bracket
    context.maybeInsertedLineEnd = line.substr(cursor.column)
    context.maybeInsertedBrackets++
  }

  YesWikiBehaviour.isAutoInsertedClosing = function(cursor, line, bracket) {
    return context.autoInsertedBrackets > 0
      && cursor.row === context.autoInsertedRow
      && bracket === context.autoInsertedLineEnd[0]
      && line.substr(cursor.column) === context.autoInsertedLineEnd
  }

  YesWikiBehaviour.isMaybeInsertedClosing = function(cursor, line) {
    return context.maybeInsertedBrackets > 0
      && cursor.row === context.maybeInsertedRow
      && line.substr(cursor.column) === context.maybeInsertedLineEnd
      && line.substr(0, cursor.column) == context.maybeInsertedLineStart
  }

  YesWikiBehaviour.popAutoInsertedClosing = function() {
    context.autoInsertedLineEnd = context.autoInsertedLineEnd.substr(1)
    context.autoInsertedBrackets--
  }

  YesWikiBehaviour.clearMaybeInsertedClosing = function() {
    if (context) {
      context.maybeInsertedBrackets = 0
      context.maybeInsertedRow = -1
    }
  }

  oop.inherits(YesWikiBehaviour, CstyleBehaviour)

  exports.YesWikiBehaviour = YesWikiBehaviour
})

// ===================================
//          YESWIKI MODE
// ====================================
ace.define('ace/mode/yeswiki', ['require', 'exports', 'module', 'ace/lib/oop', 'ace/mode/text', 'ace/mode/yeswiki_highlight_rules', 'ace/mode/yeswiki_behaviour'], (require, exports, module) => {
  const oop = require('../lib/oop')
  const TextMode = require('./text').Mode
  const { YesWikiHighlightRules } = require('./yeswiki_highlight_rules')
  const { YesWikiBehaviour } = require('./yeswiki_behaviour')

  const Mode = function() {
    this.HighlightRules = YesWikiHighlightRules
    this.$behaviour = new YesWikiBehaviour()
  }
  oop.inherits(Mode, TextMode);

  (function() {
    this.type = 'text'
    this.blockComment = { start: '""<!--', end: '-->""' }
    this.$quotes = { '"': '"', '`': '`' }

    this.getNextLineIndent = function(state, line, tab) {
      const match = /^(\s*)(?:([-+*])|(\d+)\.)(\s+)/.exec(line)
      // For lists, add the - on next line, or increment the number for ordered list 1. 2.
      if (match) {
        let marker = match[2]
        if (!marker) marker = `${parseInt(match[3], 10) + 1}.`
        return match[1] + marker + match[4]
      }
      // Next mine use same identation
      return this.$getIndent(line)
    }
    this.$id = 'ace/mode/yeswiki'
  }).call(Mode.prototype)

  exports.Mode = Mode
});

(function() {
  ace.require(['ace/mode/yeswiki'], (m) => {
    if (typeof module == 'object' && typeof exports == 'object' && module) {
      module.exports = m
    }
  })
}())
