import { createAceditorToolbar } from "./aceditor-toolbar.js"
import { setupAceditorKeyBindings } from './aceditor-key-bindings.js'

/*
 Transform a textarea into an Ace editor, with toolbar
*/
;(function ($, window ) {
  // Create the defaults once
  var pluginName = 'aceditor',
      defaults = {
        savebtn: false,
        syntax: 'yeswiki',
        class: ""
      };

  // The actual plugin constructor
  function Plugin( element, options ) {
    this.element = element;
    this.lang = wiki.lang;
    this.wiki = wiki;

    this.options = $.extend( {}, defaults, options)

    this._defaults = defaults;
    this._name = pluginName;

    this.init();
    return this.aceditor;
  }

  Plugin.prototype.init = function () {
    // Place initialization logic here
    // You already have access to the DOM element and the options via the instance,
    // e.g., this.element and this.options
    if ($(this.element).is('textarea')) {
      var textarea = $(this.element);
      var aceeditor = $('<div class="ace-editor-container ' + this.options.class +'"><pre class="ace-body"></pre></div>')

      var aceditor = ace.edit(aceeditor.find('pre')[0], {
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
      aceditor.getSession().setValue(textarea.val());
      aceditor.getSession().on('change', function(){
        textarea.val(aceditor.getSession().getValue());
      });
      aceditor.on('change', function(){
        if (typeof showPopup !== "undefined") { showPopup = 1 };
      });

      textarea.data('aceditor', aceditor);

      setupAceditorKeyBindings(aceeditor)

      // Add buttonbar and aceeditor container over textarea

      var toolbar = createAceditorToolbar(textarea, aceditor, this.options)
      textarea.before(toolbar)
      textarea.after(aceeditor);
      textarea.hide();
      textarea.addClass('textarea-aceditor')
    }
    return aceditor;
  };

  // A really lightweight plugin wrapper around the constructor,
  // preventing against multiple instantiations
  $.fn[pluginName] = function ( options ) {
    return this.each(function () {
      if (!$.data(this, 'plugin_' + pluginName)) {
        $.data(this, 'plugin_' + pluginName, new Plugin( this, options ));
      }
    });
  }

}(jQuery, window));

// Edit handler of yeswiki
$('#body').aceditor({savebtn : true, class: "big"});

// For comments and Bazar's textarea
$('.wiki-textarea, .commentform textarea').aceditor();
