<?php

// do not change this line, you fool. In fact, don't change anything! Ever!
define('WAKKA_VERSION', '0.1.1');
define('WIKINI_VERSION', '0.5.0');
define("YESWIKI_VERSION", 'doryphore');
define("YESWIKI_RELEASE", '2020-01-22-1');
define('T_START', microtime(true));

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
//define('WN_CAMEL_CASE_EVOLVED', '\b[\p{Lu}]+[\p{L}-_0-9]*[\p{Lu}]+[\p{L}-_0-9]*');
define('WN_CAMEL_CASE_EVOLVED', '[\p{L}\-_.0-9]+');
define('WN_CAMEL_CASE_EVOLVED_WITH_SLASH', '[\p{L}\-_.0-9\/]+');
define('RFC3986_URI_CHARS', '[\p{L}0-9-._~:\/?#[\]@!$&\'()*+,;=%]*');
// to check tag links with param which use camel case evolved characters
define('WN_CAMEL_CASE_EVOLVED_WITH_SLASH_AND_PARAMS', WN_CAMEL_CASE_EVOLVED_WITH_SLASH . '(?:[?&]' . RFC3986_URI_CHARS . ')?');
/** the regexp that matches automatic wiki links in a text */
define('WN_WIKI_LINK', WN_CAMEL_CASE); // this might evolve to handle the page groups and subpages syntax
/** the regexp that checks if a word is a valid page tag */
//define('WN_PAGE_TAG', WN_CHAR . '+');
define('WN_PAGE_TAG', WN_CAMEL_CASE_EVOLVED);
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

// for package updates
define('SEMVER', '(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?');

//////////////////////////////////////////////////////////////////////// - yg
// ForUser signin validation process and login

// User Activation/Inactivation pages

define ("ACTIVATEUSERPAGE", "ActivateUser");
define ("INACTIVATEUSERPAGE", "InactivateUser");

// $_GET parameters name for activation/inactivation link

define ("ACTIONPARAMETER_ACTIVATEUSER_USER", "u");	// user
define ("ACTIONPARAMETER_ACTIVATEUSER_KEY", "k");	// key

define ("ACTIONPARAMETER_INACTIVATEUSER_USER", ACTIONPARAMETER_ACTIVATEUSER_USER);
define ("ACTIONPARAMETER_INACTIVATEUSER_KEY", ACTIONPARAMETER_ACTIVATEUSER_KEY);

// Make it harder for a potential hacker who might have access to the DB and not the FS to read and understand the meaning of properties and values in the triples table
// It is perhaps unusefull at his points since it may be understood quite easily by changing the values in the DB but it has no cost to do it since we use constants ("Y" = "whatever we want", ...)
// Furthermore the activation status and key might be encrypted with a per-server generated key stored in the FS so that activating/inactivating an account by modifying the values in DB becomes "impossible"
// The strings used are composed of 0 and O, more confusing for the eyes. Furthermore there is the same numbers of O and 0 to try to deal with statistics crytanaalysis.

define ("TRIPLERESSOURCEPREFIX_ACCOUNTSECURITY", '');			// Prefixes used in the 
define ("TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY", "0O0O00OO");	// TripleStore methods : create, update, delete, etc...
define ("TRIPLEVALUEPREFIX_ACCOUNTSECURITY", TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY); // Add confusion for values
	
define ("TRIPLEPROPERTY_USER_ACTIVATIONKEY", 		"0O00O0OO"); // properties name must differ (case insensitive)
define ("TRIPLEPROPERTY_USER_INACTIVATIONKEY", 		"00O0O0OO");
define ("TRIPLEPROPERTY_USER_ISACTIVATED", 			"0O0O00OO");
	
define ("TRIPLEVALUE_USER_ISACTIVATED_YES", TRIPLEVALUEPREFIX_ACCOUNTSECURITY .	"0O0OO00O"); // values name must differ (case insensitive)
define ("TRIPLEVALUE_USER_ISACTIVATED_NO", TRIPLEVALUEPREFIX_ACCOUNTSECURITY .	"0O0O0O0O");

//
////////////////////////////////////////////////////////////////////////



