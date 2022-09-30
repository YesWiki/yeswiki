import { createAceditorToolbar } from "./aceditor-toolbar.js"
import { setupAceditorKeyBindings } from './aceditor-key-bindings.js'

// Aceditor Plugin
// Transform a textarea into an Ace editor, with toolbar
$.fn['aceditor'] = function (options) {
  // Lightweight plugin wrapper, preventing against multiple instantiations
  return this.each(function () {
    if (!$.data(this, 'plugin_acedior')) {
      $.data(this, 'plugin_acedior', new Plugin(this, options))
    }
  })
}

// The actual plugin constructor
function Plugin(element, options) {
  this.element = element;
  const defaults = {
    savebtn: false,
    syntax: 'yeswiki'
  };
  this.options = $.extend({}, defaults, options)

  return this.init()
}

Plugin.prototype.init = function () {
  // Place initialization logic here
  // You already have access to the DOM element and the options via the instance,
  // e.g., this.element and this.options
  if ($(this.element).is('textarea')) {
    var textarea = $(this.element);

    var $editorContainer = $('<div class="ace-editor-container"><pre class="ace-body"></pre></div>')
    setupAceditorKeyBindings($editorContainer)

    var aceditor = ace.edit($editorContainer.find('pre')[0], {
      printMargin: false,
      mode: "ace/mode/" + this.options.syntax,
      showGutter: true,
      wrap: 'free',
      maxLines: Infinity,
      minLines: $(this.element).attr('rows'),
      showFoldWidgets:false,
      fontSize: "18px",
      useSoftTabs: false,
      tabSize: 3,
      fontFamily: 'monospace',
      highlightActiveLine: true,
    });

    // Sync textarea and editor
    aceditor.getSession().setValue(textarea.val());
    aceditor.getSession().on('change', function(){
      textarea.val(aceditor.getSession().getValue());
    });

    // Enable alert popup when leaving the page
    aceditor.on('change', function(){
      if (typeof showPopup !== "undefined") { showPopup = 1 };
    });

    // Setup DOM
    var toolbar = createAceditorToolbar(textarea, aceditor, this.options)
    textarea.data('aceditor', aceditor);
    textarea.before(toolbar)
    textarea.after($editorContainer);
    textarea.hide().addClass('textarea-aceditor');
  }
  return aceditor;
}

// Edit handler of yeswiki
$('#body').aceditor({savebtn : true});

// For comments and Bazar's textarea
$('.wiki-textarea, .commentform textarea').aceditor();
