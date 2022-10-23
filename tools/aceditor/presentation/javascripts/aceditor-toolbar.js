import { openModal } from './aceditor-toolbar-remote-modal.js'
import { openAceditorToolbarLinkModal } from './aceditor-toolbar-link-modal.js'

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
    'CENTER_LFT'      : '&quot;&quot;<center>&quot;&quot;',
    'CENTER_RGT'      : '&quot;&quot;</center>&quot;&quot;',
    'LEAD_LFT'        : '&quot;&quot;<div class=&quot;lead&quot;>&quot;&quot;',
    'LEAD_RGT'        : '&quot;&quot;</div>&quot;&quot;',
    'HIGHLIGHT_LFT'   : '{{section bgcolor=&quot;var(--primary-color)&quot; class=&quot;shape-rounded&quot; pattern=&quot;border-solid&quot; }}',
    'HIGHLIGHT_RGT'   : '{{end elem=&quot;section&quot;}}',
    'CODE_LFT'        : '%%',
    'CODE_RGT'        : '%%',
    'INLINE_CODE_LFT' : '`',
    'INLINE_CODE_RGT' : '`',
    'BOLD_LFT'        : '**',
    'BOLD_RGT'        : '**',
    'ITALIC_LFT'      : '//',
    'ITALIC_RGT'      : '//',
    'UNDERLINE_LFT'   : '__',
    'UNDERLINE_RGT'   : '__',
    'STRIKE_LFT'      : '@@',
    'STRIKE_RGT'      : '@@',
    'LIST_LFT'        : ' - ',
    'LIST_RGT'        : '',
    'LINE_LFT'        : '\n------\n',
    'LINE_RGT'        : '',
    'LINK_LFT'        : '[[',
    'LINK_RGT'        : ']]',
    'COMMENT_LFT'     : '{#',
    'COMMENT_RGT'     : '#}'
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
    'CENTER_LFT'      : '<center>',
    'CENTER_RGT'      : '</center>',
    'LEAD_LFT'        : '<div class=&quot;lead&quot;>',
    'LEAD_RGT'        : '</div>',
    'HIGHLIGHT_LFT'   : '<div class=&quot;well&quot;>',
    'HIGHLIGHT_RGT'   : '</div>',
    'CODE_LFT'        : '<pre>',
    'CODE_RGT'        : '</pre>',
    'INLINE_CODE_LFT' : '<code>',
    'INLINE_CODE_RGT' : '</code>',
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
    'LINK_RGT'        : '</a>',
    'COMMENT_LFT'     : '<!--',
    'COMMENT_RGT'     : '-->'
  }
};

export function createAceditorToolbar(textarea, aceditor, options = {}) {
  var toolbar = $('<div>').addClass("btn-toolbar aceditor-toolbar");

  var syntax = SYNTAX[options.syntax || "yeswiki"];

  // Save Button
  if (options.savebtn) {
    toolbar.append(`
      <div class="btn-group">
        <button type="submit" name="submit" value="Sauver" class="aceditor-btn-save btn btn-primary">
          ${wiki.lang['ACEDITOR_SAVE']}
        </button>
      </div>̀`);
  }

  // Text formatting
  toolbar.append(
    '<div class="btn-group">' +
      '<a class="btn btn-default dropdown-toggle" data-toggle="dropdown" href="#">'+wiki.lang['ACEDITOR_FORMAT']+'  <span class="caret"></span></a>' +
      '<ul class="dropdown-menu">' +
        '<li><a title="'+wiki.lang['ACEDITOR_TITLE1']+'" class="aceditor-btn aceditor-btn-title1" data-lft="'+syntax['TITLE1_LFT']+'" data-rgt="'+syntax['TITLE1_RGT']+'"><h1>'+wiki.lang['ACEDITOR_TITLE1']+'</h1></a></li>' +
        '<li><a title="'+wiki.lang['ACEDITOR_TITLE2']+'" class="aceditor-btn aceditor-btn-title2" data-lft="'+syntax['TITLE2_LFT']+'" data-rgt="'+syntax['TITLE2_RGT']+'"><h2>'+wiki.lang['ACEDITOR_TITLE2']+'</h2></a></li>' +
        '<li><a title="'+wiki.lang['ACEDITOR_TITLE3']+'" class="aceditor-btn aceditor-btn-title3" data-lft="'+syntax['TITLE3_LFT']+'" data-rgt="'+syntax['TITLE3_RGT']+'"><h3>'+wiki.lang['ACEDITOR_TITLE3']+'</h3></a></li>' +
        '<li><a title="'+wiki.lang['ACEDITOR_TITLE4']+'" class="aceditor-btn aceditor-btn-title4" data-lft="'+syntax['TITLE4_LFT']+'" data-rgt="'+syntax['TITLE4_RGT']+'"><h4>'+wiki.lang['ACEDITOR_TITLE4']+'</h4></a></li>' +
        '<li class="divider"></li>' +
        '<li><a title="'+wiki.lang['ACEDITOR_CENTER']+'" class="aceditor-btn aceditor-btn-center" data-lft="'+syntax['CENTER_LFT']+'" data-rgt="'+syntax['CENTER_RGT']+'"><center>'+wiki.lang['ACEDITOR_CENTER']+'</center></a></li>' +
        '<li><a title="'+wiki.lang['ACEDITOR_BIGGER_TEXT']+'" class="aceditor-btn aceditor-btn-lead" data-lft="'+syntax['LEAD_LFT']+'" data-rgt="'+syntax['LEAD_RGT']+'"><div class="lead">'+wiki.lang['ACEDITOR_BIGGER_TEXT']+'</div></a></li>' +
        '<li><a title="'+wiki.lang['ACEDITOR_HIGHLIGHT_TEXT']+'" class="aceditor-btn aceditor-btn-well" data-lft="'+syntax['HIGHLIGHT_LFT']+'" data-rgt="'+syntax['HIGHLIGHT_RGT']+'"><div class="well">'+wiki.lang['ACEDITOR_HIGHLIGHT_TEXT']+'</div></a></li>' +
        '<li><a title="'+wiki.lang['ACEDITOR_SOURCE_CODE']+'" class="aceditor-btn aceditor-btn-code" data-lft="'+syntax['CODE_LFT']+'" data-rgt="'+syntax['CODE_RGT']+'"><div class="code"><pre>'+wiki.lang['ACEDITOR_SOURCE_CODE']+'</pre></div></a></li>' +
        '<li><a title="'+wiki.lang['ACEDITOR_INLINE_CODE']+'" class="aceditor-btn aceditor-btn-code" data-lft="'+syntax['INLINE_CODE_LFT']+'" data-rgt="'+syntax['INLINE_CODE_RGT']+'"><code>'+wiki.lang['ACEDITOR_INLINE_CODE']+'</code></a></li>' +
        '<li><a title="'+wiki.lang['ACEDITOR_COMMENT']+'" class="aceditor-btn aceditor-btn-comment" data-lft="'+syntax['COMMENT_LFT']+'" data-rgt="'+syntax['COMMENT_RGT']+'">'+wiki.lang['ACEDITOR_COMMENT']+'</a></li>' +
      '</ul>' +
    '</div>');

  // Bold Italic Underline Stroke
  toolbar.append(
    '<div class="btn-group">' +
      '<a class="btn btn-default aceditor-btn aceditor-btn-bold" data-lft="'+syntax['BOLD_LFT']+'" data-rgt="'+syntax['BOLD_RGT']+'" title="'+wiki.lang['ACEDITOR_BOLD_TEXT']+'">' +
        '<span class="fa fa-bold"></span>' +
      '</a>' +
      '<a class="btn btn-default aceditor-btn aceditor-btn-italic" data-lft="'+syntax['ITALIC_LFT']+'" data-rgt="'+syntax['ITALIC_RGT']+'" title="'+wiki.lang['ACEDITOR_ITALIC_TEXT']+'">' +
        '<span class="fa fa-italic"></span>' +
      '</a>' +
      '<a class="btn btn-default aceditor-btn aceditor-btn-underline" data-lft="'+syntax['UNDERLINE_LFT']+'" data-rgt="'+syntax['UNDERLINE_RGT']+'" title="'+wiki.lang['ACEDITOR_UNDERLINE_TEXT']+'">' +
        '<span class="fa fa-underline"></span>' +
      '</a>' +
      '<a class="btn btn-default aceditor-btn aceditor-btn-strike" data-lft="'+syntax['STRIKE_LFT']+'" data-rgt="'+syntax['STRIKE_RGT']+'" title="'+wiki.lang['ACEDITOR_STRIKE_TEXT']+'">' +
        '<span class="fa fa-strikethrough"></span>' +
      '</a>' +
    '</div>');

  // Lists
  toolbar.append(
    '<div class="btn-group">' +
      '<a class="btn btn-default aceditor-btn aceditor-btn-list" data-lft="'+syntax['LIST_LFT']+'" data-rgt="'+syntax['LIST_RGT']+'" title="'+wiki.lang['ACEDITOR_LIST']+'">' +
        '<i class="fa fa-list"></i>' +
      '</a>' +
    '</div>');

  // Horizontal line and links
  toolbar.append(
    '<div class="btn-group">' +
      '<a class="btn btn-default aceditor-btn aceditor-btn-line" data-lft="'+syntax['LINE_LFT']+'" data-rgt="'+syntax['LINE_RGT']+'" title="'+wiki.lang['ACEDITOR_LINE']+'">' +
        '<i class="fa fa-minus icon-minus"></i>' +
      '</a>' +
      '<a class="btn btn-default aceditor-btn aceditor-btn-link" '+
          'data-link="1" data-lft="" data-rgt="" '+
          'title="'+wiki.lang["ACEDITOR_LINK_TITLE"]+'">'+
          '<i class="fa fa-link"></i> ' +
          "</a>" +
    '</div>');

  // Actions Builder
  // Note: actionsBuilderData has been defined in action-builder.tpl.html
  var actionsHtml = ''
  for (var actionGroupName in actionsBuilderData.action_groups) {
      var groupConfig = actionsBuilderData.action_groups[actionGroupName]
      if (groupConfig.onlyEdit) continue
      actionsHtml += `
        <li>
          <a class="open-actions-builder-btn" data-group-name="${actionGroupName}">
            ${groupConfig.label}
          </a>
        </li>`
  }
  actionsHtml += `
    <li class="open-actions-builder-btn open-existing-action">
      <a>${wiki.lang['ACEDITOR_ACTIONS_EDIT_CURRENT']}</a>
    </li>`

  toolbar.append(`
    <div class="btn-group actions-builder-button">
      <a class="btn btn-default dropdown-toggle" data-toggle="dropdown" href="#">
        ${wiki.lang['ACEDITOR_ACTIONS']}
        <span class="caret"></span>
      </a>
      <ul class="dropdown-menu component-action-list">
        ${actionsHtml}
      </ul>
    </div>`)
  // TODO handle file uploader button here also (seems quite weird the way it is handled now)

  // help
  toolbar.append(
    '<div class="btn-group pull-right">' +
      '<a class="btn btn-info aceditor-btn aceditor-btn-help" data-remote="true" href="'+wiki.url('ReglesDeFormatage')+'" title="'+wiki.lang['ACEDITOR_HELP']+'">' +
        wiki.lang['ACEDITOR_HELP'] +
        '<i class="fa fa-question-circle" style="margin-left: 8px"></i>' +
      '</a>' +
    '</div>');

  // ---- BUTTONS BINDING --------
  (function($) {
    $.fn.surroundSelectedText = function (left = "", right = "") {
      return this.each(function () {
        var aceditor = $(this).data('aceditor');
        if (!aceditor) aceditor = $(this).aceditor()
        aceditor.session.replace(aceditor.getSelectionRange(), left + aceditor.getSelectedText() + right)
      });
    }
  }(jQuery, window));

  toolbar.find('a.aceditor-btn')
    // .on('click', function(e) {
    //   e.preventDefault();
    //   e.stopPropagation();
    //   return(false);
    // })
    .on('click', function(e) {
      e.preventDefault();
      e.stopPropagation();

      if ($(this).data('prompt')) {
        // Prompt Button
        var prompt = window.prompt($(this).data('prompt'), $(this).data('prompt-val'));
        if (prompt != null) {
          textarea.surroundSelectedText($(this).data('lft') + prompt + " ", $(this).data('rgt'))
        }
      } else if ($(this).data('remote')) {
        // Remote Modal Button
        openModal($(this).attr('title'), $(this).attr('href'))
      }  else if ($(this).data('link')) {
        // Link Button
        openAceditorToolbarLinkModal($(this), aceditor)
      } else {
        // Other Buttons
        textarea.surroundSelectedText($(this).data('lft'), $(this).data('rgt'))
      }

      $(this).parents('.btn-group').removeClass('open');
      return false;
    })

    return toolbar
  }