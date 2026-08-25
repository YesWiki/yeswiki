<?php

define('YESWIKI_VERSION', 'doryphore');
define('YESWIKI_RELEASE', '2020-01-22-1');
define('T_START', microtime(true));

define('WN_UPPER', '[A-Z]');
define('WN_LOWER', '[a-z]');
define('WN_UPPER_NUM', '[A-Z0-9]');
define('WN_CHAR', '[A-Za-z0-9]');

define('WN_CHAR2', '[A-Za-z0-9_-]');

define('WN_CAMEL_CASE', WN_UPPER . WN_LOWER . '+' . WN_UPPER_NUM . WN_CHAR . '*');

define('WN_CAMEL_CASE_EVOLVED', '[\p{L}\-_.0-9]+');
define('WN_CAMEL_CASE_EVOLVED_WITH_SLASH', '[\p{L}\-_.0-9\/]+');
define('RFC3986_URI_CHARS', '[\p{L}0-9-._~:\/?#[\]@!$&\'()*+,;=%]*');

define('WN_CAMEL_CASE_EVOLVED_WITH_SLASH_AND_PARAMS', WN_CAMEL_CASE_EVOLVED_WITH_SLASH . '(?:[?&]' . RFC3986_URI_CHARS . ')?');

define('WN_PAGE_TAG', WN_CAMEL_CASE_EVOLVED);

define('WN_TAG_HANDLER_CAPTURE', '(' . WN_PAGE_TAG . ')/(' . WN_CHAR2 . '*)');

define('THISWIKI_PREFIX', 'ThisWiki:');
define('GROUP_PREFIX', 'ThisWikiGroup:');
define('ADMIN_GROUP', 'admins');
define('WIKINI_VOC_PREFIX', 'http://www.wikini.net/_vocabulary/');
define('WIKINI_VOC_ACTIONS_PREFIX', WIKINI_VOC_PREFIX . 'action/');
define('WIKINI_VOC_HANDLERS_PREFIX', WIKINI_VOC_PREFIX . 'handler/');

define('WIKINI_VOC_ACLS', 'acls');
define('WIKINI_VOC_ACLS_URI', WIKINI_VOC_PREFIX . WIKINI_VOC_ACLS);

define('SEMVER', '(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?');

define('DEFAULT_MAX_UPLOAD_SIZE', 2000 * 1024);

define('MIN_SEARCH_KEYWORD_LENGTH', 3);

define('THEME_PAR_DEFAUT', 'yeswiki');
define('CSS_PAR_DEFAUT', 'yeswiki.css');
define('SQUELETTE_PAR_DEFAUT', '1col.twig');
define('BACKGROUND_IMAGE_PAR_DEFAUT', '');

define('SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME', false);
