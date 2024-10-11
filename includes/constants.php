<?php

// do not change this line, you fool. In fact, don't change anything! Ever!
define('WAKKA_VERSION', '0.1.1');
define('WIKINI_VERSION', '0.5.0');
define('YESWIKI_VERSION', 'doryphore');
define('YESWIKI_RELEASE', '2020-01-22-1');
define('T_START', microtime(true));

// to update with constraint in composer.json > config > platform > php and
// and .github/workflow/phpunit.yml > php-version
define('MINIMUM_PHP_VERSION_FOR_CORE', '7.3.0');

// operational constants
define('WAKKA_ENGINE', 'wakka.php');

// YesWiki Charset
// YW_CHARSET définie dans includes/i18n.inc.php:initI18n()

// constant for parsing rules
define('WN_UPPER', '[A-Z]'); // \xC0-\xD6\xD8-\xDE]");
define('WN_LOWER', '[a-z]'); // \xDF-\xF6\xF8-\xFF]");
define('WN_UPPER_NUM', '[A-Z0-9]'); // \xC0-\xD6\xD8-\xDE]");
define('WN_CHAR', '[A-Za-z0-9]'); // \xC0-\xD6\xD8-\xF6\xF8-\xFF]");
// constant for interwiki: link part
define('WN_CHAR2', '[A-Za-z0-9_-]'); // \xC0-\xD6\xD8-\xF6\xF8-\xFF]");
// constants for WikiWords (CamelCase) and such things
/* the regexp for CamelCase - to use to check the syntax of a word, for example a user name */
define('WN_CAMEL_CASE', WN_UPPER . WN_LOWER . '+' . WN_UPPER_NUM . WN_CHAR . '*');
//define('WN_CAMEL_CASE_EVOLVED', '\b[\p{Lu}]+[\p{L}-_0-9]*[\p{Lu}]+[\p{L}-_0-9]*');
define('WN_CAMEL_CASE_EVOLVED', '[\p{L}\-_.0-9]+');
define('WN_CAMEL_CASE_EVOLVED_WITH_SLASH', '[\p{L}\-_.0-9\/]+');
define('RFC3986_URI_CHARS', '[\p{L}0-9-._~:\/?#[\]@!$&\'()*+,;=%]*');
// to check tag links with param which use camel case evolved characters
define('WN_CAMEL_CASE_EVOLVED_WITH_SLASH_AND_PARAMS', WN_CAMEL_CASE_EVOLVED_WITH_SLASH . '(?:[?&]' . RFC3986_URI_CHARS . ')?');
/* the regexp that matches automatic wiki links in a text */
define('WN_WIKI_LINK', WN_CAMEL_CASE); // this might evolve to handle the page groups and subpages syntax
/* the regexp that checks if a word is a valid page tag */
//define('WN_PAGE_TAG', WN_CHAR . '+');
define('WN_PAGE_TAG', WN_CAMEL_CASE_EVOLVED);
/* the regexp that checks and splits a PageTag/handler into 'PageTag' and 'handler' */
define('WN_TAG_HANDLER_CAPTURE', '(' . WN_PAGE_TAG . ')/(' . WN_CHAR2 . '*)');

// constants for the management of the triples
// standard prefixes
define('THISWIKI_PREFIX', 'ThisWiki:');
define('GROUP_PREFIX', 'ThisWikiGroup:');
define('ADMIN_GROUP', 'admins');
define('WIKINI_VOC_PREFIX', 'http://www.wikini.net/_vocabulary/');
define('WIKINI_VOC_ACTIONS_PREFIX', WIKINI_VOC_PREFIX . 'action/');
define('WIKINI_VOC_HANDLERS_PREFIX', WIKINI_VOC_PREFIX . 'handler/');
// standard properties
define('WIKINI_VOC_ACLS', 'acls');
define('WIKINI_VOC_ACLS_URI', WIKINI_VOC_PREFIX . WIKINI_VOC_ACLS);

// for package updates
define('SEMVER', '(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?');

// default max size for upload
define('DEFAULT_MAX_UPLOAD_SIZE', 2000 * 1024);
