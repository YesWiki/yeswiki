import * as aceModule from '../../../../javascripts/vendor/ace/ace.js'
// Loads html rules cause it's used inside yeswiki mode
import * as aceModeHtml from '../../../../javascripts/vendor/ace/mode-html.js'

import setupAceditorToolbarBindings from './aceditor-toolbar.js'
import setupAceditorKeyBindings from './aceditor-key-bindings.js'

// Aceditor Plugin
// Transform a textarea into an Ace editor, with toolbar
$.fn.aceditor = function(options) {
  // Lightweight plugin wrapper, preventing against multiple instantiations
  return this.each(function() {
    if (!$.data(this, 'plugin_acedior')) {
      $.data(this, 'plugin_acedior', new Plugin(this, options))
    }
  })
}

// The actual plugin constructor
function Plugin(element, options) {
  this.element = element
  const defaults = {
    savebtn: false,
    syntax: 'yeswiki'
  }
  this.options = $.extend({}, defaults, options)

  return this.init()
}

Plugin.prototype.init = function() {
  // Place initialization logic here
  // You already have access to the DOM element and the options via the instance,
  // e.g., this.element and this.options
  if ($(this.element).is('textarea')) {
    const textarea = $(this.element)

    const $editorContainer = $('.ace-editor-container')
    setupAceditorKeyBindings($editorContainer)

    // Where to find the 'mode-XXXX' files
    if (this.options.syntax === 'yeswiki') {
      ace.config.set('basePath', 'tools/aceditor/presentation/javascripts')
    } else {
      ace.config.set('basePath', 'javascripts/vendor/ace')
    }

    const aceditor = ace.edit($editorContainer.find('pre')[0], {
      printMargin: false,
      mode: `ace/mode/${this.options.syntax}`,
      showGutter: true,
      wrap: 'free',
      maxLines: Infinity,
      minLines: $(this.element).attr('rows'),
      showFoldWidgets: false,
      fontSize: '18px',
      useSoftTabs: false,
      tabSize: 3,
      fontFamily: 'monospace',
      highlightActiveLine: true
    })

    // Sync textarea and editor
    aceditor.getSession().setValue(textarea.val())
    aceditor.getSession().on('change', () => {
      textarea.val(aceditor.getSession().getValue())
    })

    // Enable alert popup when leaving the page
    aceditor.on('change', () => {
      if (typeof showPopup !== 'undefined') { showPopup = 1 }
    })

    // Setup DOM
    setupAceditorToolbarBindings(textarea, aceditor)
    textarea.data('aceditor', aceditor)

    return aceditor
  }
  return false
}

// Edit handler of yeswiki
$('#body').aceditor()

// For comments and Bazar's textarea
$('.wiki-textarea, .commentform textarea').aceditor()
