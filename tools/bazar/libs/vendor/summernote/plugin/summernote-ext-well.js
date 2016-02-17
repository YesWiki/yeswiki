(function (factory) {
  /* global define */
  if (typeof define === 'function' && define.amd) {
    // AMD. Register as an anonymous module.
    define(['jquery'], factory);
  } else if (typeof module === 'object' && module.exports) {
    // Node/CommonJS
    module.exports = factory(require('jquery'));
  } else {
    // Browser globals
    factory(window.jQuery);
  }
}(function($) {

  // Extends plugins for adding well.
  //  - plugin is external module for customizing.
  $.extend($.summernote.plugins, {
    /**
     * @param {Object} context - context object has status of editor.
     */
    'well': function(context) {
      var self = this;

      // ui has renders to build ui elements.
      //  - you can create a button with `ui.button`
      var ui = $.summernote.ui;
      var $editor = context.layoutInfo.editor;
      var options = context.options;
      var lang = options.langInfo;

      // add well button
      context.memo('button.well', function() {
        // create button
        var button = ui.button({
          contents: '<i class="glyphicon glyphicon-unchecked"/>',
          tooltip: 'Text box',
          hide: true,
          click: function() {
            // invoke insertText method with 'well' on editor module.
            context.invoke('editor.insertText', 'well');
          },
        });

        // create jQuery object from button instance.
        var $well = button.render();
        return $well;
      });

      // This events will be attached when editor is initialized.
      this.events = {
        // This will be called after modules are initialized.
        'summernote.init': function (we, e) {
          //console.log('summernote initialized', we, e);
        },
        // This will be called when user releases a key on editable.
        'summernote.keyup': function (we, e) {
          //console.log('summernote keyup', we, e);
        }
      };
    }
  });
}));
//
//
// (function(factory) {
//   /* global define */
//   if (typeof define === 'function' && define.amd) {
//     // AMD. Register as an anonymous module.
//     define(['jquery'], factory);
//   } else {
//     // Browser globals: jQuery
//     factory(window.jQuery);
//   }
// }(function($) {
//
//   // template, editor
//   var tmpl = $.summernote.renderer.getTemplate();
//
//   // core functions: range, dom
//   var range = $.summernote.core.range;
//
//   //var dom = $.summernote.core.dom;
//
//   var getTextOnRange = function($editable) {
//     $editable.focus();
//
//     var rng = range.create();
//
//     // if range on anchor, expand range with anchor
//     if (rng.isOnAnchor()) {
//       var anchor = dom.ancestor(rng.sc, dom.isAnchor);
//       rng = range.createFromNode(anchor);
//     }
//
//     return rng.toString();
//   };
//   /**
//    * @class plugin.textbox
//    *
//    * Text box Plugin
//    */
//   $.summernote.addPlugin({
//     /** @property {String} name name of plugin */
//     name: 'textboxwell',
//     /**
//      * @property {Object} buttons
//      * @property {Function} buttons.well   function to make button
//      */
//     buttons: { // buttons
//       textboxwell: function(lang) {
//
//         return tmpl.iconButton('fa fa-square-o', {
//           event: 'well',
//           title: lang.textboxwell.welltext,
//           hide: true,
//         });
//       },
//     },
//
//     /**
//      * @property {Object} events
//      * @property {Function} events.well  run function when button that has a 'well' event name  fires click
//      */
//     events: { // events
//       well: function(event, editor, layoutInfo) {
//         var $editable = layoutInfo.editable();
//         text = getTextOnRange($editable);
//
//         var div = $('<div class="well">' + text + '</div>');
//         editor.insertNode($editable, div[0], true);
//       },
//     },
//
//
//   });
// }));
