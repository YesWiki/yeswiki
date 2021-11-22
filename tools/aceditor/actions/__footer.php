<?php

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}
$lang = array(
  'ACEDITOR_SAVE' => _t('ACEDITOR_SAVE'),
  'ACEDITOR_FORMAT' => _t('ACEDITOR_FORMAT'),
  'ACEDITOR_TITLE1' => _t('ACEDITOR_TITLE1'),
  'ACEDITOR_TITLE2' => _t('ACEDITOR_TITLE2'),
  'ACEDITOR_TITLE3' => _t('ACEDITOR_TITLE3'),
  'ACEDITOR_TITLE4' => _t('ACEDITOR_TITLE4'),
  'ACEDITOR_TITLE5' => _t('ACEDITOR_TITLE5'),
  'ACEDITOR_BIGGER_TEXT' => _t('ACEDITOR_BIGGER_TEXT'),
  'ACEDITOR_HIGHLIGHT_TEXT' => _t('ACEDITOR_HIGHLIGHT_TEXT'),
  'ACEDITOR_SOURCE_CODE' => _t('ACEDITOR_SOURCE_CODE'),
  'ACEDITOR_BOLD_TEXT' => _t('ACEDITOR_BOLD_TEXT'),
  'ACEDITOR_ITALIC_TEXT' => _t('ACEDITOR_ITALIC_TEXT'),
  'ACEDITOR_UNDERLINE_TEXT' => _t('ACEDITOR_UNDERLINE_TEXT'),
  'ACEDITOR_STRIKE_TEXT' => _t('ACEDITOR_STRIKE_TEXT'),
  'ACEDITOR_LINE' => _t('ACEDITOR_LINE'),
  'ACEDITOR_LIST' => _t('ACEDITOR_LIST'),
  'ACEDITOR_LINK' => _t('ACEDITOR_LINK'),
  'ACEDITOR_LINK_PROMPT' => _t('ACEDITOR_LINK_PROMPT'),
  'ACEDITOR_LINK_TITLE' => _t('ACEDITOR_LINK_TITLE'),
  'ACEDITOR_HELP' => _t('ACEDITOR_HELP'),
  'ACEDITOR_ACTIONS' => _t('ACEDITOR_ACTIONS'),
  'ACEDITOR_ACTIONS_EDIT_CURRENT' => _t('ACEDITOR_ACTIONS_EDIT_CURRENT'),
  'ACEDITOR_COMMENT' => _t('ACEDITOR_COMMENT'),
  'ACEDITOR_LINK_ADD_INTERNAL'          => _t('ACEDITOR_LINK_ADD_INTERNAL'),
  'ACEDITOR_LINK_ADD_EXTERNAL'          => _t('ACEDITOR_LINK_ADD_EXTERNAL'),
  'ACEDITOR_LINK_PAGE_NAME'             => _t('ACEDITOR_LINK_PAGE_NAME'),
  'ACEDITOR_LINK_HINT_NEW_PAGE_NAME'    => _t('ACEDITOR_LINK_HINT_NEW_PAGE_NAME'),
  'ACEDITOR_LINK_EXTERNAL'              => _t('ACEDITOR_LINK_EXTERNAL'),
  'ACEDITOR_LINK_TEXT'                  => _t('ACEDITOR_LINK_TEXT'),
  'ACEDITOR_LINK_OPEN_IN_CURRENT_TAB'   => _t('ACEDITOR_LINK_OPEN_IN_CURRENT_TAB'),
  'ACEDITOR_LINK_OPEN_IN_NEW_TAB'       => _t('ACEDITOR_LINK_OPEN_IN_NEW_TAB'),
  'ACEDITOR_LINK_OPEN_IN_MODAL'         => _t('ACEDITOR_LINK_OPEN_IN_MODAL'),
  'ACEDITOR_LINK_CANCEL'                => _t('ACEDITOR_LINK_CANCEL'),
  'ACEDITOR_LINK_INSERT'                => _t('ACEDITOR_LINK_INSERT'),
  'ACTION_BUILDER_PREVIEW'       => _t('ACTION_BUILDER_PREVIEW'),
  'ACTION_BUILDER_ONLINEDOC'     => _t('ACTION_BUILDER_ONLINEDOC'),
  'ACTION_BUILDER_COPY'          => _t('ACTION_BUILDER_COPY'),
  'ACTION_BUILDER_UPDATE_CODE'   => _t('ACTION_BUILDER_UPDATE_CODE'),
  'ACTION_BUILDER_INSERT_CODE'   => _t('ACTION_BUILDER_INSERT_CODE'),
);
$js = 'if (typeof wiki === \'undefined\'){var wiki = {lang:'.json_encode($lang).'};}else {wiki.lang = {...(wiki.lang ?? null),...'.json_encode($lang).'};}';

$this->AddJavascript($js);
$this->AddJavascriptFile('tools/aceditor/presentation/javascripts/ace-lib.js');
$this->AddJavascriptFile('tools/aceditor/presentation/javascripts/mode-html.js');
$this->AddJavascriptFile('tools/aceditor/presentation/javascripts/aceditor.js');
$this->AddCSSFile('tools/aceditor/presentation/styles/aceditor.css');
