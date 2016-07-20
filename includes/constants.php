<?php

// operational constants
define('WAKKA_ENGINE', 'wakka.php');

// YesWiki Charset
// YW_CHARSET définie dans includes/i18n.inc.php:initI18n()

// constant for parsing rules
define("WN_UPPER", "[A-Z]"); // \xC0-\xD6\xD8-\xDE]");
define("WN_LOWER", "[a-z]"); // \xDF-\xF6\xF8-\xFF]");
define("WN_UPPER_NUM", "[A-Z0-9]"); // \xC0-\xD6\xD8-\xDE]");
define("WN_CHAR", "[A-Za-z0-9]"); // \xC0-\xD6\xD8-\xF6\xF8-\xFF]");
// constant for interwiki: link part
define("WN_CHAR2", "[A-Za-z0-9_-]"); // \xC0-\xD6\xD8-\xF6\xF8-\xFF]");
// constants for WikiWords (CamelCase) and such things
/** the regexp for CamelCase - to use to check the syntax of a word, for example a user name */
define('WN_CAMEL_CASE', WN_UPPER . WN_LOWER . '+' . WN_UPPER_NUM . WN_CHAR . '*');
/** the regexp that matches automatic wiki links in a text */
define('WN_WIKI_LINK', WN_CAMEL_CASE); // this might evolve to handle the page groups and subpages syntax
/** the regexp that checks if a word is a valid page tag */
define('WN_PAGE_TAG', WN_CHAR . '+');
/** the regexp that checks and splits a PageTag/handler into 'PageTag' and 'handler' */
define('WN_TAG_HANDLER_CAPTURE', '(' . WN_PAGE_TAG . ')/(' . WN_CHAR2 . '*)');
/** the regexp that matches InterWiki links in a text */
define('WN_INTERWIKI_LINK', WN_UPPER . WN_CHAR . '+:' . WN_CHAR2 . '*');
/** the regexp that matches InterWiki links with 2 capture brackets for site name and page tag */
define('WN_INTERWIKI_CAPTURE', '(' . WN_UPPER . WN_CHAR . '+):(' . WN_CHAR2 . '*)');

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
