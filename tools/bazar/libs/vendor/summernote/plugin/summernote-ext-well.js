(function (factory) {
  /* global define */
  if (typeof define === 'function' && define.amd) {
    // AMD. Register as an anonymous module.
    define(['jquery'], factory);
  } else {
    // Browser globals: jQuery
    factory(window.jQuery);
  }
}(function ($) {
  // template, editor
  var tmpl = $.summernote.renderer.getTemplate();
  // core functions: range, dom
  var range = $.summernote.core.range;
  //var dom = $.summernote.core.dom;

  var getTextOnRange = function ($editable) {
    $editable.focus();

    var rng = range.create();

    // if range on anchor, expand range with anchor
    if (rng.isOnAnchor()) {
      var anchor = dom.ancestor(rng.sc, dom.isAnchor);
      rng = range.createFromNode(anchor);
    }

    return rng.toString();
  };
  /**
   * @class plugin.textbox 
   * 
   * Text box Plugin  
   */
  $.summernote.addPlugin({
    /** @property {String} name name of plugin */
    name: 'textboxwell',
    /** 
     * @property {Object} buttons 
     * @property {Function} buttons.well   function to make button
     */
    buttons: { // buttons
      textboxwell: function (lang) {

        return tmpl.iconButton('fa fa-square-o', {
          event : 'well',
          title: lang.textboxwell.welltext,
          hide: true
        });
      },
    },

    /**
     * @property {Object} events 
     * @property {Function} events.well  run function when button that has a 'well' event name  fires click
     */
    events: { // events
      well: function (event, editor, layoutInfo) {
        var $editable = layoutInfo.editable();
        text = getTextOnRange($editable);
        //console.log(text);

        var div = $('<div class="well">' + text + '</div>');
        editor.insertNode($editable, div[0], true);
      }
    },

    langs: {
      "en-US": {
        textboxwell: {
          welltext: 'Text box'
        }
      },
      "fr-FR": {
        textboxwell: {
          welltext: 'Texte encadré'
        }
      }
    }
  });
}));
