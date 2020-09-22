var SYNTAX = {
  yeswiki : {
    'TITLE1_LFT'      : '======',
    'TITLE1_RGT'      : '======',
    'TITLE2_LFT'      : '=====',
    'TITLE2_RGT'      : '=====',
    'TITLE3_LFT'      : '====',
    'TITLE3_RGT'      : '====',
    'TITLE4_LFT'      : '===',
    'TITLE4_RGT'      : '===',
    'TITLE5_LFT'      : '==',
    'TITLE5_RGT'      : '==',
    'LEAD_LFT'        : '&quot;&quot;<div class=&quot;lead&quot;>&quot;&quot;',
    'LEAD_RGT'        : '&quot;&quot;</div>&quot;&quot;',
    'HIGHLIGHT_LFT'   : '&quot;&quot;<div class=&quot;well&quot;>&quot;&quot;',
    'HIGHLIGHT_RGT'   : '&quot;&quot;</div>&quot;&quot;',
    'CODE_LFT'        : '%%',
    'CODE_RGT'        : '%%',
    'BOLD_LFT'        : '**',
    'BOLD_RGT'        : '**',
    'ITALIC_LFT'      : '//',
    'ITALIC_RGT'      : '//',
    'UNDERLINE_LFT'   : '__',
    'UNDERLINE_RGT'   : '__',
    'STRIKE_LFT'      : '@@',
    'STRIKE_RGT'      : '@@',
    'LIST_LFT'        : ' - ',
    'LIST_RGT'        : '\n',
    'LINE_LFT'        : '\n------\n',
    'LINE_RGT'        : '',
    'LINK_LFT'        : '[[',
    'LINK_RGT'        : ']]'
  },
  html : {
    'TITLE1_LFT'      : '<h1>',
    'TITLE1_RGT'      : '</h1>',
    'TITLE2_LFT'      : '<h2>',
    'TITLE2_RGT'      : '</h2>',
    'TITLE3_LFT'      : '<h3>',
    'TITLE3_RGT'      : '</h3>',
    'TITLE4_LFT'      : '<h4>',
    'TITLE4_RGT'      : '</h4>',
    'TITLE5_LFT'      : '<h5>',
    'TITLE5_RGT'      : '</h5>',
    'LEAD_LFT'        : '<div class=&quot;lead&quot;>',
    'LEAD_RGT'        : '</div>',
    'HIGHLIGHT_LFT'   : '<div class=&quot;well&quot;>',
    'HIGHLIGHT_RGT'   : '</div>',
    'CODE_LFT'        : '<pre>',
    'CODE_RGT'        : '</pre>',
    'BOLD_LFT'        : '<strong>',
    'BOLD_RGT'        : '</strong>',
    'ITALIC_LFT'      : '<em>',
    'ITALIC_RGT'      : '</em>',
    'UNDERLINE_LFT'   : '<u>',
    'UNDERLINE_RGT'   : '</u>',
    'STRIKE_LFT'      : '<span class=&quot;del&quot;>',
    'STRIKE_RGT'      : '</span>',
    'LIST_LFT'        : '<li>',
    'LIST_RGT'        : '</li>',
    'LINE_LFT'        : '<hr>\n',
    'LINE_RGT'        : '',
    'LINK_LFT'        : '<a href=&quot;#&quot;>',
    'LINK_RGT'        : '</a>'
  }
};

;(function ( $, window, undefined ) {
  $.fn.surroundSelectedText = function ( left = "", right = "") {
    return this.each(function () {
      var aceditor = $(this).data('aceditor');
      if (!aceditor) aceditor = $(this).aceditor()
      aceditor.session.replace(aceditor.getSelectionRange(), left + aceditor.getSelectedText() + right)
    });
  }
}(jQuery, window));

/*
 Transform a textarea into an Ace editor, with toolbar
*/
;(function ( $, window, undefined ) {
  // Create the defaults once
  var pluginName = 'aceditor',
      document = window.document,
      defaults = {
        savebtn: false,
        syntax: 'yeswiki',
        class: ""
      };

  // The actual plugin constructor
  function Plugin( element, options ) {
    this.element = element;
    this.lang = aceditorlang;

    this.options = $.extend( {}, defaults, options) ;

    this.syntax = SYNTAX[this.options.syntax];

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
      var aceeditor = $('<div class="ace-editor-container ' + this.options.class +'"><pre class="ace-body"></pre></div>')
      var toolbar = $('<div>').addClass("btn-toolbar aceditor-toolbar");

      // Add buttonbar and aceeditor container over textarea
      var textarea = $(this.element);
      textarea.before(toolbar);
      textarea.after(aceeditor);
      textarea.hide();

      var aceditor = ace.edit(aceeditor.find('pre')[0], {
        printMargin: false,
        // theme: "ace/theme/monokai",
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

      textarea.data('aceditor', aceditor);

      // ---- TOOLBAR ----
      if (this.options.savebtn) {
        toolbar.append($('<div class="btn-group"><button type="submit" name="submit" value="Sauver" class="aceditor-btn-save btn btn-primary">'+this.lang['ACEDITOR_SAVE']+'</button></div>'));
      }

      // Text formatting
      toolbar.append( '<div class="btn-group">' +
              '<a class="btn btn-default dropdown-toggle" data-toggle="dropdown" href="#">'+this.lang['ACEDITOR_FORMAT']+'  <span class="caret"></span></a>' +
              '<ul class="dropdown-menu">' +
                '<li><a title="'+this.lang['ACEDITOR_TITLE1']+'" class="aceditor-btn aceditor-btn-title1" data-lft="'+this.syntax['TITLE1_LFT']+'" data-rgt="'+this.syntax['TITLE1_RGT']+'"><h1>'+this.lang['ACEDITOR_TITLE1']+'</h1></a></li>' +
                '<li><a title="'+this.lang['ACEDITOR_TITLE2']+'" class="aceditor-btn aceditor-btn-title2" data-lft="'+this.syntax['TITLE2_LFT']+'" data-rgt="'+this.syntax['TITLE2_RGT']+'"><h2>'+this.lang['ACEDITOR_TITLE2']+'</h2></a></li>' +
                '<li><a title="'+this.lang['ACEDITOR_TITLE3']+'" class="aceditor-btn aceditor-btn-title3" data-lft="'+this.syntax['TITLE3_LFT']+'" data-rgt="'+this.syntax['TITLE3_RGT']+'"><h3>'+this.lang['ACEDITOR_TITLE3']+'</h3></a></li>' +
                '<li><a title="'+this.lang['ACEDITOR_TITLE4']+'" class="aceditor-btn aceditor-btn-title4" data-lft="'+this.syntax['TITLE4_LFT']+'" data-rgt="'+this.syntax['TITLE4_RGT']+'"><h4>'+this.lang['ACEDITOR_TITLE4']+'</h4></a></li>' +
                '<li class="divider"></li>' +
                '<li><a title="'+this.lang['ACEDITOR_BIGGER_TEXT']+'" class="aceditor-btn aceditor-btn-lead" data-lft="'+this.syntax['LEAD_LFT']+'" data-rgt="'+this.syntax['LEAD_RGT']+'"><div class="lead">'+this.lang['ACEDITOR_BIGGER_TEXT']+'</div></a></li>' +
                '<li><a title="'+this.lang['ACEDITOR_HIGHLIGHT_TEXT']+'" class="aceditor-btn aceditor-btn-well" data-lft="'+this.syntax['HIGHLIGHT_LFT']+'" data-rgt="'+this.syntax['HIGHLIGHT_RGT']+'"><div class="well">'+this.lang['ACEDITOR_HIGHLIGHT_TEXT']+'</div></a></li>' +
                '<li><a title="'+this.lang['ACEDITOR_SOURCE_CODE']+'" class="aceditor-btn aceditor-btn-code" data-lft="'+this.syntax['CODE_LFT']+'" data-rgt="'+this.syntax['CODE_RGT']+'"><div class="code"><pre>'+this.lang['ACEDITOR_SOURCE_CODE']+'</pre></div></a></li>' +
              '</ul>' +
            '</div>');

      // Bold Italic Underline Stroke
      toolbar.append( '<div class="btn-group">' +
              '<a class="btn btn-default aceditor-btn aceditor-btn-bold" data-lft="'+this.syntax['BOLD_LFT']+'" data-rgt="'+this.syntax['BOLD_RGT']+'" title="'+this.lang['ACEDITOR_BOLD_TEXT']+'">' +
                '<span class="fa fa-bold"></span>' +
              '</a>' +
              '<a class="btn btn-default aceditor-btn aceditor-btn-italic" data-lft="'+this.syntax['ITALIC_LFT']+'" data-rgt="'+this.syntax['ITALIC_RGT']+'" title="'+this.lang['ACEDITOR_ITALIC_TEXT']+'">' +
                '<span class="fa fa-italic"></span>' +
              '</a>' +
              '<a class="btn btn-default aceditor-btn aceditor-btn-underline" data-lft="'+this.syntax['UNDERLINE_LFT']+'" data-rgt="'+this.syntax['UNDERLINE_RGT']+'" title="'+this.lang['ACEDITOR_UNDERLINE_TEXT']+'">' +
                '<span class="fa fa-underline"></span>' +
              '</a>' +
              '<a class="btn btn-default aceditor-btn aceditor-btn-strike" data-lft="'+this.syntax['STRIKE_LFT']+'" data-rgt="'+this.syntax['STRIKE_RGT']+'" title="'+this.lang['ACEDITOR_STRIKE_TEXT']+'">' +
                '<span class="fa fa-strikethrough"></span>' +
              '</a>' +
            '</div>');

      // Lists
      toolbar.append( '<div class="btn-group">' +
              '<a class="btn btn-default aceditor-btn aceditor-btn-list" data-lft="'+this.syntax['LIST_LFT']+'" data-rgt="'+this.syntax['LIST_RGT']+'" title="'+this.lang['ACEDITOR_LIST']+'">' +
                '<i class="fa fa-list"></i>' +
              '</a>' +
            '</div>');

      // Horizontal line and links
      toolbar.append( '<div class="btn-group">' +
              '<a class="btn btn-default aceditor-btn aceditor-btn-line" data-lft="'+this.syntax['LINE_LFT']+'" data-rgt="'+this.syntax['LINE_RGT']+'" title="'+this.lang['ACEDITOR_LINE']+'">' +
                '<i class="fa fa-minus icon-minus"></i>' +
              '</a>' +
              '<a class="btn btn-default aceditor-btn aceditor-btn-link" data-prompt="'+this.lang['ACEDITOR_LINK_PROMPT']+'" data-prompt-val="http://" data-lft="'+this.syntax['LINK_LFT']+'" data-rgt="'+this.syntax['LINK_RGT']+'" title="'+this.lang['ACEDITOR_LINK_TITLE']+'" class="btn">' +
                '<i class="fa fa-link"></i></a>' +
            '</div>');

      // Actions Builder
      toolbar.append( '<div class="btn-group">' +
              '<a class="btn btn-default dropdown-toggle" data-toggle="dropdown" href="#">'+this.lang['ACEDITOR_ACTIONS']+'  <span class="caret"></span></a>' +
              '<ul class="dropdown-menu">' +
                '<li><a class="aceditor-btn-actions-bazar" data-toggle="modal" data-target="#bazar-actions-modal">'+this.lang['ACEDITOR_ACTIONS_BAZAR']+'</a></li>' +
              '</ul>' +
            '</div>');

      // help
      toolbar.append( '<div class="btn-group pull-right">' +
              '<a class="btn btn-default aceditor-btn aceditor-btn-help" data-remote="true" href="wakka.php?wiki=ReglesDeFormatage" title="'+this.lang['ACEDITOR_HELP']+'">' +
                '<i class="fa fa-question-circle"></i></a>' +
            '</div>');


      // ---- BUTTONS BINDING --------
      toolbar.find('a.aceditor-btn').each(function() {
        $(this).on('click', function(e){
          e.preventDefault();
          e.stopPropagation();
          return(false);
        }).on('mousedown', function(e){
          e.preventDefault();
          e.stopPropagation();
          if ($(this).data('prompt')) {
            var prompt = window.prompt($(this).data('prompt'), $(this).data('prompt-val'));
            if (prompt != null) {
              textarea.surroundSelectedText($(this).data('lft') + prompt + " ", $(this).data('rgt'))
            }
          } else if ($(this).data('remote')) {
              var $modal = $('<div class="modal fade">' +
                '<div class="modal-dialog modal-lg">' +
                '<div class="modal-content">' +
                '<div class="modal-header">' +
                '<button type="button" class="close" data-dismiss="modal">&times;</button>' +
                '<h2>' + $(this).attr('title') + '</h2>' +
                '</div>' +
                '<div class="modal-body" style="min-height:500px">' +
                '<span id="yw-modal-loading" class="throbber"></span>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>');
              $('body').append($modal);
              $modal.find('.modal-body').load($(this).attr('href') + ' .page', function (response, status, xhr) {
                return false;
              });
              $modal.modal({
                keyboard: false,
              }).modal('show').on('hidden hidden.bs.modal', function () {
                $modal.remove();
              });
          } else {
            textarea.surroundSelectedText($(this).data('lft'), $(this).data('rgt'))
          }

          $(this).parents('.btn-group').removeClass('open');
          return false;
        })
      });


      // ---- KEY BINDING --------
      var isCtrl = false;
      var isAlt = false;
      var isShift = false;

      aceeditor.keyup(function(e) {
        if (e.ctrlKey) {
          isCtrl = false;
        }
        if (e.altKey) {
          isAlt = false;
        }
        if (e.shiftKey) {
          isShift = false;
        }
      });

      aceeditor.keydown(function(e) {
        var keyCode = e.which;
        if (e.ctrlKey) {
          isCtrl = true;
        } else {
          isCtrl = false;
        }
        if (e.altKey) {
          isAlt = true;
        } else {
          isAlt = false;
        }
        if (e.shiftKey) {
          isShift = true;
        } else {
          isShift = false;
        }

        if (isCtrl === true && isAlt === false) {
          // title 1
          if (keyCode == 49 && isShift === true) {
            $('.aceditor-btn-title1').mousedown();e.preventDefault();
          }
          // title 2
          else if (keyCode == 50 && isShift === true) {
            $('.aceditor-btn-title2').mousedown();e.preventDefault();
          }
          // title 3
          else if (keyCode == 51 && isShift === true) {
            $('.aceditor-btn-title3').mousedown();e.preventDefault();
          }
          // title 4
          else if (keyCode == 52 && isShift === true) {
            $('.aceditor-btn-title4').mousedown();e.preventDefault();
          }
          // title 5
          else if (keyCode == 53 && isShift === true) {
            $('.aceditor-btn-title5').mousedown();e.preventDefault();
          }
          // bold
          else if (keyCode == 66 && isShift === false) {
            $('.aceditor-btn-bold').mousedown();e.preventDefault();
          }
          // italic
          else if (keyCode == 73 && isShift === false) {
            $('.aceditor-btn-italic').mousedown();e.preventDefault();
          }
          // underline
          else if (keyCode == 85 && isShift === false) {
            $('.aceditor-btn-underline').mousedown();e.preventDefault();
          }
          // strike
          else if (keyCode == 89 && isShift === false) {
            $('.aceditor-btn-strike').mousedown();e.preventDefault();
          }
          // save page
          else if (keyCode == 83 && isShift === false) {
            $('.aceditor-btn-save').click();e.preventDefault();
          }
          return;
        }
      });
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
$('textarea.nohtml').aceditor({syntax:'html'});
