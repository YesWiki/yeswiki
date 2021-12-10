<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                        |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2014 Outils-Réseaux (accueil@outils-reseaux.org)                                       |
// +------------------------------------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or                                        |
// | modify it under the terms of the GNU Lesser General Public                                           |
// | License as published by the Free Software Foundation; either                                         |
// | version 2.1 of the License, or (at your option) any later version.                                   |
// |                                                                                                      |
// | This library is distributed in the hope that it will be useful,                                      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
// | Lesser General Public License for more details.                                                      |
// |                                                                                                      |
// | You should have received a copy of the GNU Lesser General Public                                     |
// | License along with this library; if not, write to the Free Software                                  |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
//
/**
* English translation file for YesWiki's main program
*
*@package 		yeswiki
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2014 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations']??[], array(

// wakka.php
'UNKNOWN_ACTION' => 'Unknown action',
'INVALID_ACTION' => 'Invalid action',
'ERROR_NO_ACCESS' => 'Error : you have no rights to access this action',
'INCORRECT_CLASS' => 'Incorrect class',
'UNKNOWN_METHOD' => 'Unknown method',
'FORMATTER_NOT_FOUND' => 'Formatter not found',
'HANDLER_NO_ACCESS' => 'You cannot access this page with the specified handler.',
'NO_REQUEST_FOUND' => '$_REQUEST[] not found. Wakka needs PHP 4.1.0 or more recent!',
'SITE_BEING_UPDATED' => 'This website is currently updated. Please try again later.',
'INCORRECT_PAGENAME' => 'Incorrect page name.',
'DB_CONNECT_FAIL' => 'For some reasons, probably database access error, this YesWiki is temporaly unavailable. Please try again later, thank you for your patience.',
'LOG_DB_CONNECT_FAIL' => 'YesWiki : database connection failed',
'INCORRECT_PAGENAME' => 'Incorrect page name.',
'HOMEPAGE_WIKINAME' => 'HomePage',
'MY_YESWIKI_SITE' => 'My YesWiki website',

// tools.php
'YESWIKI_TOOLS_CONFIG' => 'Yeswiki\'s extensions configuration',
'DISCONNECT' => 'Disconnect',
'RETURN_TO_EXTENSION_LIST' => 'Return to activated extensions list',
'NO_TOOL_AVAILABLE' => 'No extensions available or active',
'LIST_OF_ACTIVE_TOOLS' => 'Activated extensions list',

// actions/backlinks.php
'PAGES_WITH_LINK' => 'Pages with link to',
'PAGES_WITH_LINK_TO_CURRENT_PAGE' => 'Pages with link to current page',
'NO_PAGES_WITH_LINK_TO' => 'No page with link to',

// actions/changestyle.php ignoree...

// handlers/page/acls.php
'YW_ACLS_LIST' => 'List of acces rights to the page',
'YW_ACLS_UPDATED' => 'Access rights updated',
'YW_NEW_OWNER' => ' and owner change . New owner: ',
'YW_CANCEL' => 'Cancel',
'YW_ACLS_READ' => 'Read rights',
'YW_ACLS_WRITE' => 'Write rights',
'YW_CHANGE_OWNER' => 'Change the owner',
'YW_CHANGE_NOTHING' => 'No changes',
'YW_CANNOT_CHANGE_ACLS' => 'You cannot change rights on this page',

// actions/editactionsacls.class.php
'ACTION_RIGHTS' => 'Action\'s rights',
'SEE' => 'See',
'ERROR_WHILE_SAVING_ACL' => 'Error while saving the rights of this action',
'ERROR_CODE' => 'Error code',
'NEW_ACL_FOR_ACTION' => 'New ACL for action',
'NEW_ACL_SUCCESSFULLY_SAVED_FOR_ACTION' => 'New ACL successfully saved for action',
'EDIT_RIGHTS_FOR_ACTION' => 'Edit rights for this action',
'SAVE' => 'Save',

// actions/editgroups.class.php
'DEFINITION_OF_THE_GROUP' => 'Definition of the group',
'DEFINE' => 'Define',
'CREATE_NEW_GROUP' => 'Or create a new group',
'ONLY_ADMINS_CAN_CHANGE_MEMBERS' => 'Only admins can change members of a group',
'YOU_CANNOT_REMOVE_YOURSELF' => 'You cannot remove yourself from the admins group',
'ERROR_RECURSIVE_GROUP' => 'Error: you cannot define a group recursively',
'ERROR_WHILE_SAVING_GROUP' => 'Error while saving the group',
'NEW_ACL_FOR_GROUP' => 'New ACL for the group',
'NEW_ACL_SUCCESSFULLY_SAVED_FOR_THE_GROUP' => 'New ACL successfully saved for the group',
'EDIT_GROUP' => 'Edit group',
'ONLY_ALPHANUM_FOR_GROUP_NAME' => 'The group names can only contain alphanumerical characters',

// actions/edithandlersacls.class.php
'HANDLER_RIGHTS' => 'Handler\'s rights',
'ERROR_WHILE_SAVING_HANDLER_ACL' => 'Error while saving ACL for the handler',
'NEW_ACL_FOR_HANDLER' => 'New ACL for the handler',
'NEW_ACL_SUCCESSFULLY_SAVED_FOR_HANDLER' => 'New ACL successfully saved for the handler',
'EDIT_RIGHTS_FOR_HANDLER' => 'Edit handler\'s rights',

// actions/erasespamedcomments.class.php ignoree...
// actions/footer.php ignoree, car le tools templates court circuite
// actions/header.php ignoree, car le tools templates court circuite

// actions/include.php
'ERROR' => 'Error',
'ACTION' => 'Action',
'MISSING_PAGE_PARAMETER' => '"page" parameter is missing',
'IMPOSSIBLE_FOR_THIS_PAGE' => 'Impossible for this page',
'TO_INCLUDE_ITSELF' => 'to include itself',
'INCLUSIONS_CHAIN' => 'Inclusions chain',
'EDITION' => 'Edition',
'READING_OF_INCLUDED_PAGE' => 'Reading of the included page',
'NOT_ALLOWED' => 'not allowed',
'INCLUDED_PAGE' => 'The included page',
'DOESNT_EXIST' => 'doesn\'t exist',

// actions/listpages.php
'THE_PAGE' => 'The page',
'BELONGING_TO' => 'belonging to',
'UNKNOWN' => 'Unknown',
'LAST_CHANGE_BY' => 'last change by',
'LAST_CHANGE' => 'last change',
'PAGE_LIST_WHERE' => 'Page list where',
'HAS_PARTICIPATED' => 'has participated',
'EXCLUDING_EXCLUSIONS' => 'excluding exclusions',
'INCLUDING' => 'and where',
'IS_THE_OWNER' => 'is the owner',
'NO_PAGE_FOUND' => 'No page found',
'IN_THIS_WIKI' => 'in this wiki',
'LIST_PAGES_BELONGING_TO' => 'List of pages belonging to',
'THIS_USER_HAS_NO_PAGE' => 'This user has no page',
'UNKNOWN' => 'Unknown',
'BY' => 'by',

// actions/mychanges.php
'YOUR_MODIFIED_PAGES_ORDERED_BY_MODIFICATION_DATE' => 'List of your modified pages, ordered by date of modification',
'YOUR_MODIFIED_PAGES_ORDERED_BY_NAME' => 'List of your modified pages, ordered by name',
'YOU_DIDNT_MODIFY_ANY_PAGE' => 'You didn\'t modify any page',
'YOU_ARENT_LOGGED_IN' => 'You aren\'t logged in',
'IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES' => 'impossible to show the list of pages that you have modified',
'LIST_OF_PAGES_WHERE_YOU_ARE_THE_OWNER' => 'List of pages belonging to you',
'YOU_DONT_OWN_ANY_PAGE' => 'You don\'t own any page',

// actions/orphanedpages.php
'NO_ORPHAN_PAGES' => 'No orphan pages',

// actions/recentchanges.php
'HISTORY' => 'history',

// actions/recentchangesrss.php
'TO_OBTAIN_RSS_FEED_TO_GO_THIS_ADDRESS' => 'To obtain the RSS feed, use this URL address',
'LATEST_CHANGES_ON' => 'Latest changes on',

// actions/recentcomments.php
'NO_RECENT_COMMENTS' => 'No recent comments',

// actions/recentcommentsrss.php
'TO_OBTAIN_COMMENTS_RSS_FEED_TO_GO_THIS_ADDRESS' => 'To obtain the RSS feed of latest comments, use this URL address',
'LATEST_COMMENTS_ON' => 'Latest comments on',

// actions/recentlycommented.php
'NO_RECENT_COMMENTS_ON_PAGES' => 'No page was recently commented',

// actions/redirect.php
'ERROR_ACTION_REDIRECT' => 'Error action {{redirect ...}}',
'CIRCULAR_REDIRECTION_FROM_PAGE' => 'circular redirection from page',
'CLICK_HERE_TO_EDIT' => 'click ici to edit',
'PRESENCE_OF_REDIRECTION_TO' => 'Presence of redirection to',

// actions/resetpassword.php
'ACTION_RESETPASSWORD' => 'Action {{resetpassword ...}}',
'PASSWORD_UPDATED' => 'Password updated',
'RESETTING_THE_PASSWORD' => 'Resetting the password',
'WIKINAME' => 'WikiName',
'NEW_PASSWORD' => 'New password',
'RESET_PASSWORD' => 'Reset password',
'NO_PERMISSIONS_TO_EXECUTE_THIS_ACTION' => 'you have no permissions to execute this action',

// actions/textsearch.php
'WHAT_YOU_SEARCH' => 'What you search',
'SEARCH' => 'Search',
//'SEARCH' => 'Search database',
'SEARCH_RESULT_OF' => 'Search results for',
'NO_RESULT_FOR' => 'No result for',

// actions/trail.php
'ERROR_ACTION_TRAIL' => 'Error action {{trail ...}}',
'INDICATE_THE_PARAMETER_TOC' => 'Indicate the WikiPage used as table of content, parameter "toc"',

// actions/usersettings.php
'USER_SETTINGS' => 'User settings',
'USER_SIGN_UP' => 'Sign Up',
'YOU_ARE_NOW_DISCONNECTED' => 'You are now disconnected',
'PARAMETERS_SAVED' => 'Parameters saved',
'WRONG_PASSWORD' => 'Wrong password',
'PASSWORD_CHANGED' => 'Password changed',
'GREETINGS' => 'Hello',
'YOUR_EMAIL_ADDRESS' => 'Your email address',
'DOUBLE_CLICK_TO_EDIT' => 'Double click to edit',
'SHOW_COMMENTS_BY_DEFAULT' => 'By default, show the comments',
'MAX_NUMBER_OF_LASTEST_COMMENTS' => 'Maximum number of latest comments',
'MAX_NUMBER_OF_VERSIONS' => 'Maximum number of versions',
'YOUR_MOTTO' => 'Your motto',
'CHANGE_THE_PASSWORD' => 'Change the password',
'YOUR_OLD_PASSWORD' => 'Your former password',
'NEW_PASSWORD' => 'New password',
'CHANGE' => 'Change',
'YOU_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED' => 'You must accept cookies to get connected',
'IF_YOU_ARE_REGISTERED_LOGGIN_HERE' => 'If you are already registered, login here',
'YOUR_WIKINAME' => 'Your WikiName',
'PASSWORD_5_CHARS_MINIMUM' => 'Password (5 characters minimum)',
'REMEMBER_ME' => 'Remember me',
'IDENTIFICATION' => 'Identification',
'FILL_THE_NEXT_FIELDS_IF_YOU_LOGGIN_FOR_THE_FIRST_TIME_AND_REGISTER' => 'Fill the next fields if you register for the first time',
'PASSWORD_CONFIRMATION' => 'Password confirmation',
'NEW_ACCOUNT' => 'New account',
'LOGGED_USERS_ONLY_ACTION' => 'You must be logged in to perform this action',

// actions/wantedpages.php
'NO_PAGE_TO_CREATE' => 'No page to create',

// setup/header.php
'OK' => 'OK',
'FAIL' => 'FAIL',
'END_OF_INSTALLATION_BECAUSE_OF_ERRORS' => 'End of installation because of errors in the configuration',

// setup/default.php
'INSTALLATION_OF_YESWIKI' => 'Installation of YesWiki',
'YOUR_SYSTEM' => 'Your system',
'EXISTENT_SYSTEM_RECOGNISED_AS_VERSION' => 'existant was recognized as version',
'YOU_ARE_UPDATING_YESWIKI_TO_VERSION' => 'You are updating YesWiki to version',
'CHECK_YOUR_CONFIG_INFORMATION_BELOW' => 'Check your configuration\'s information below',
'FILL_THE_FORM_BELOW' => 'Fill the form below',
'DEFAULT_LANGUAGE' => 'Default language',
'DEFAULT_LANGUAGE_INFOS' => 'Language used by default for YesWiki\'s interface, you still can change language for each page created',
'GENERAL_CONFIGURATION' => 'General configuration',
'DATABASE_CONFIGURATION' => 'Database configuration',
'MORE_INFOS' => 'More info',
'MYSQL_SERVER' => 'MySQL server host',
'MYSQL_SERVER_INFOS' => 'IP address or network name of the computer containing your MySQL server',
'MYSQL_DATABASE' => 'MySQL database name',
'MYSQL_DATABASE_INFOS' => 'This database should exist before installation',
'MYSQL_USERNAME' => 'MySQL username',
'MYSQL_USERNAME_INFOS' => 'Required to access your database',
'TABLE_PREFIX' => 'Table prefix',
'TABLE_PREFIX_INFOS' => 'Permits to install several YesWiki on one unique database : every YesWiki installed must have a different table prefix',
'MYSQL_PASSWORD' => 'MySQL password',
'YESWIKI_WEBSITE_CONFIGURATION' => 'Configuration of your YesWiki website',
'YOUR_WEBSITE_NAME' => 'Title of your website',
'YOUR_WEBSITE_NAME_INFOS' => 'This can be a WikiName or any title which will appear on every browser\'s window or tab',
'HOMEPAGE' => 'Homepage',
'HOMEPAGE_INFOS' => 'Home page from your YesWiki. Must be a WikiName',
'KEYWORDS' => 'Keywords',
'KEYWORDS_INFOS' => 'Keywords which will appear in the HTML code (metas)',
'DESCRIPTION' => 'Description',
'DESCRIPTION_INFOS' => 'The description of your website which will appear in the HTML code (metas)',
'CREATION_OF_ADMIN_ACCOUNT' => 'Administrator account',
'ADMIN_ACCOUNT_CAN' => 'Administrator account can',
'MODIFY_AND_DELETE_ANY_PAGE' => 'Modify and delete any page',
'MODIFY_ACCESS_RIGHTS_ON_ANY_PAGE' => 'Modify access rights on any page',
'GENERATE_ACCESS_RIGHTS_ON_ANY_ACTION_OR_HANDLER' => 'Handle access rights on any action or handler',
'GENERATE_GROUPS' => 'Handle groups, add or delete users to the admin group (to have the same rights then him)',
'ALL_ADMIN_TASKS_ARE_DESCRIBED_IN_THE_PAGE' => 'All admin tasks are described in the page "AdministrationDeYesWiki" accessible from the homepage',
'USE_AN_EXISTING_ACCOUNT' => 'Use an existing account',
'NO' => 'No',
'YES' => 'Yes',
'OR_CREATE_NEW_ACCOUNT' => 'Or create a new one',
'ADMIN' => 'Administrator',
'MUST_BE_WIKINAME' => 'must be a WikiName',
'PASSWORD' => 'Password',
'EMAIL_ADDRESS' => 'Email address',
'MORE_OPTIONS' => 'Additional options',
'ADVANCED_CONFIGURATION' => '+ Advanced configuration',
'URL_REDIRECTION' => 'URL redirect',
'NEW_INSTALL_VALUES_CHANGE_ONLY_IF_YOU_KNOW_WHAT_YOU_ARE_DOING' => 'This is a fresh installation. The program will try to find appropriate values automaticaly. Only change them if you know what you are doing',
'PAGENAME_WILL_BE_ADDED_AFTER_CHANGE_JUST_FOR_REDIRECTION' => 'The names of pages will be added after the base URL of your YesWiki website. Delete the "wakka.php?wiki=" only if you use redirection (see below)',
'BASE_URL' => 'Base URL',
'REDIRECTION_SHOULD_BE_ACTIVE_ONLY_IF_USED_IN_YESWIKI' => 'WARNING : only active if nginx or apache are configured to rewrite Urls without the question mark',
'HTML_INSERTION_HELP_TEXT' => 'Give you more feature, like adding videos and iframe for example, but is less secured',
'INDEX_HELP_TEXT' => 'Indicate in the html head metadatas html and in the robots.txt file, if the website is indexed by search engines, or not',
'REDIRECTION_SHOULD_BE_ACTIVE_ONLY_IF_USED_IN_YESWIKI' => 'The "automatic redirection" mode must be select only if you are using YesWiki with redirection of URLs (if you don\'t know what it is, don\'t activate this option)',
'ACTIVATE_REDIRECTION_MODE' => 'Activate the "automatic redirection" mode',
'OTHER_OPTIONS' => 'Other options',
'OBLIGE_TO_PREVIEW_BEFORE_SAVING_PAGE' => 'Oblige to preview before saving a page',
'AUTHORIZE_HTML_INSERTION' => 'Authorize raw HTML insertion',
'AUTHORIZE_INDEX_BY_ROBOTS' => 'Authorize search engine indexation',
'CONTINUE' => 'Continue',

// setup/install.php
'PROBLEM_WHILE_INSTALLING' => 'problem in the installation procedure',
'VERIFICATION_OF_DATAS_AND_DATABASE_INSTALLATION' => 'Verification of the configuration\'s information and installation of the database',
'VERIFY_MYSQL_PASSWORD' => 'Verification of MySQL password',
'INCORRECT_MYSQL_PASSWORD' => 'Incorrect MySQL password',
'TEST_MYSQL_CONNECTION' => 'Test of MySQL connection',
'SEARCH_FOR_DATABASE' => 'Search for database',
'GO_BACK' => 'Go back',
'NO_DATABASE_FOUND_TRY_TO_CREATE' => 'No database found with the name you indicated. We will try to create it',
'TRYING_TO_CREATE_DATABASE' => 'Trying to create database',
'DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY' => 'Database creation impossible. You must create the database manually before installing YesWiki',
'DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT' => 'The choosen database doesn\'t exist, you must create the database manually before installing YesWiki',
'CHECKING_THE_ADMIN_PASSWORD' => 'Checking the administrator\'s password',
'CHECKING_THE_ADMIN_PASSWORD_CONFIRMATION' => 'Checking the administrator\'s password confirmation',
'ADMIN_PASSWORD_ARE_DIFFERENT' => 'The administrator\'s passwords are different',
'DATABASE_INSTALLATION' => 'Installation of the database',
'CREATION_OF_TABLES' => 'Creation of tables, admin user and group',
'SQL_FILE_NOT_FOUND' => 'SQL file not found',
'ALREADY_CREATED' => 'Already created',
'ADMIN_ACCOUNT_CREATION' => 'Creation of administrator\'s account',
'INSERTION_OF_PAGES' => 'Insertion of defaults pages',
'ALREADY_EXISTING' => 'Already existing',
'UPDATING_FROM_WIKINI_0_1' => 'Updating WikiNi 0.1',
'TINY_MODIFICATION_OF_PAGES_TABLE' => 'Tiny modification of table for pages',
'ALREADY_DONE' => 'Already done? Hmm!',
'INSERTION_OF_USER_IN_ADMIN_GROUP' => 'Insertion of user in the admin\'s group',
'NEXT_STEP_WRITE_CONFIGURATION_FILE' => 'The next step will try to create the configuration file ',
'VERIFY_YOU_HAVE_RIGHTS_TO_WRITE_FILE' => 'Verify that le web server has the right for writing in this file, otherwise you must modify it manually',

// setup/writeconfig.php
'WRITING_CONFIGURATION_FILE' => 'Writing the configuration file',
'CREATED' => 'create',
'DONT_CHANGE_YESWIKI_VERSION_MANUALLY' => 'don\'t change yeswiki_version manually',
'WRITING_CONFIGURATION_FILE_WIP' => 'Creating the configuration file',
'FINISHED_CONGRATULATIONS' => 'It\'s finished : congratulations',
'GO_TO_YOUR_NEW_YESWIKI_WEBSITE' => 'Go to your new YesWiki website',
'IT_IS_RECOMMANDED_TO_REMOVE_WRITE_ACCESS_TO_CONFIG_FILE' => 'It is recommanded to remove write access rights to the file',
'THIS_COULD_BE_UNSECURE' => 'this could be a security issue',
'WARNING' => 'WARNING',
'CONFIGURATION_FILE' => 'the configuration file',
'CONFIGURATION_FILE_NOT_CREATED' => 'was not created',
'TRY_CHANGE_ACCESS_RIGHTS_OR_FTP_TRANSFERT' => 'Try to change the write access rights on the file. If not possible, you must create the file and copy those informations inside, then transfert it by FTP on your server in a file ',
'DIRECTLY_IN_THE_YESWIKI_FOLDER' => 'directly in the YesWiki root folder. Once you done it, your YesWiki website should work as expected',
'TRY_AGAIN' => 'Try again',

// API
'USERS' => 'Users',
'GROUPS' => 'Groups',

// YesWiki\User class
'USER_DELETE_QUERY_FAILED' => 'User deletion query failed',
'USER_DELETE_LONE_MEMBER_OF_GROUP' => 'The user you are trying to delete is the only member of a group',
'USER_EMAIL_S_MAXIMUM_LENGTH_IS' => 'User email\'s maximum number of characters is',
'USER_LISTGROUPMEMBERSHIPS_QUERY_FAILED' => 'The query to get the list of groups the user belongs to failed',
'USER_MUST_BE_ADMIN_TO_DELETE' => 'You must be an admin to delete a user',
'USER_NAME_S_MAXIMUM_LENGTH_IS' => 'User name\'s maximum number of characters is',
'USER_NO_SPACES_IN_PASSWORD' => 'No spaces are allowed in password',
'USER_PASSWORD_MINIMUM_NUMBER_OF_CHARACTERS_IS' => 'The minimum number of characters for a user password is',
'USER_PASSWORDS_NOT_IDENTICAL' => 'Both passwords must be identical',
'USER_PASSWORD_TOO_SHORT' => 'Password too short',
'USER_THIS_EMAIL_IS_ALLREADY_USED_ON_THIS_WIKI' => 'The specified email is allready in use on this wiki',
'USER_THIS_IS_NOT_A_VALID_EMAIL' => 'This is not a valid email address',
'USER_UPDATE_QUERY_FAILED' => 'User update query failed',
'USER_YOU_MUST_SPECIFY_A_NAME' => 'Please specify a name for the user',
'USER_YOU_MUST_SPECIFY_AN_EMAIL' => 'Please specify an email address for the user',
'USER_MODIFY' => 'Modify',
'USER_DELETE' => 'Delete',
'USER_USERSTABLE_MISTAKEN_ARGUMENT' => 'Action usertable received an unexpected argument',
'USER_WRONG_PASSWORD' => 'Wrong password',
'USER_INCORRECT_PASSWORD_KEY' => 'Incorrect password validation key',
'USER_PASSWORD_UPDATE_FAILED' => 'Password update failed',
'USER_NOT_LOGGED_IN_CANT_LOG_OUT' => 'log out failed because no one is logged in',
'USER_TRYING_TO_LOG_WRONG_USER_OUT' => 'Trying to log someone else out',
'USER_CREATION_FAILED' => 'User creation failed',
'USER_LOAD_BY_NAME_QUERY_FAILED' => 'The query to retreive the user from the database by its name failed',
'USER_NO_USER_WITH_THAT_NAME' => 'There isn\'t any user with that name',
'USER_LOAD_BY_EMAIL_QUERY_FAILED' => 'The query to retreive the user from the database by its email failed',
'USER_NO_USER_WITH_THAT_EMAIL' => 'There isn\'t any user with that email',
'USER_UPDATE_MISSPELLED_PROPERTIES' => 'The list of fields to update with updateIntoDB certainly contains an error',
'USER_CANT_DELETE_ONESELF' => 'You cannot delete your own account',
'USER_TRYING_TO_MODIFY_AN_INEXISTANT_USER' => 'Trying to modify an user that doesn\'t exist in the database',
'USER_YOU_ARE_NOW_DISCONNECTED' => 'You are now disconnected',
'USER_PARAMETERS_SAVED' => 'Parameters saved',
'USER_DELETED' => 'User deleted',
'USER_PASSWORD_CHANGED' => 'Password changed',
'USER_EMAIL_ADDRESS' => 'Email address',
'USER_DOUBLE_CLICK_TO_EDIT' => 'Double click to edit',
'USER_SHOW_COMMENTS_BY_DEFAULT' => 'By default, show the comments',
'USER_MAX_NUMBER_OF_LASTEST_COMMENTS' => 'Maximum number of latest comments',
'USER_MAX_NUMBER_OF_VERSIONS' => 'Maximum number of versions',
'USER_MOTTO' => 'Your motto',
'USER_UPDATE' => 'Update',
'USER_DISCONNECT' => 'Disconnect',
'USER_CHANGE_THE_PASSWORD' => 'Change the password',
'USER_OLD_PASSWORD' => 'Your former password',
'USER_NEW_PASSWORD' => 'New password',
'USER_CHANGE' => 'Change',
'USER_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED' => 'You must accept cookies to get connected',
'USER_WIKINAME' => 'Your WikiName',
'USER_PASSWORD_CONFIRMATION' => 'Password confirmation',
'USER_NEW_ACCOUNT' => 'New account',
'USER_PASSWORD' => 'Password',

// YesWiki\Database class
'DATABASE_QUERY_FAILED' => 'Database query failed',
'DATABASE_YOU_MUST_FIRST_SET_ARGUMENT' => 'You must set all arguments for object of \YesWiki\Database class',
'DATABASE_MISSING_ARGUMENT' => ' missing',


// YesWiki\Session class
'SESSION_YOU_MUST_FIRST_SET_ARGUMENT' => 'You must set argument for object of \YesWiki\Session class',

// include/services/ThemeManager.php
'THEME_MANAGER_THEME_FOLDER' => 'The theme\'s folder ',
'THEME_MANAGER_SQUELETTE_FILE' => 'The 	skeleton\'s file ',
'THEME_MANAGER_NOT_FOUND' => ' is not found.',
'THEME_MANAGER_ERROR_GETTING_FILE' => 'An error occured while loading the file : ',
'THEME_MANAGER_CLICK_TO_INSTALL' => 'Click to install the theme ',
'THEME_MANAGER_AND_REPAIR' => ' and repair the website',
'THEME_MANAGER_LOGIN_AS_ADMIN' => 'Login as admin to upgrade.',

// actions/EditConfigAction.php
'EDIT_CONFIG_TITLE' => 'Change configuration file',
'EDIT_CONFIG_CURRENT_VALUE' => 'Current value',
'EDIT_CONFIG_SAVE' => 'Configuration saved',
'EDIT_CONFIG_HINT_WAKKA_NAME' => 'Head of the YesWiki website',
'EDIT_CONFIG_HINT_ROOT_PAGE' => 'Welcome page\'s name',
'EDIT_CONFIG_HINT_DEFAULT_WRITE_ACL' => 'Default write pages access control',
'EDIT_CONFIG_HINT_DEFAULT_READ_ACL' => 'Default read pages access control',
'EDIT_CONFIG_HINT_DEBUG' => 'Activate debug mode (\'yes\'/\'no\')',
'EDIT_CONFIG_HINT_DEFAULT_LANGUAGE' => '(\'fr\',\'en\',...)',
'EDIT_CONFIG_HINT_CONTACT_FROM' => 'E-mail address used as sender for messages from entries',
'EDIT_CONFIG_HINT_MAIL_CUSTOM_MESSAGE' => 'Custom message for e-mail sending',
'EDIT_CONFIG_HINT_PASSWORD_FOR_EDITING' => 'Asked password to modify forms (empty = no restriction)',
'EDIT_CONFIG_HINT_PASSWORD_FOR_EDITING_MESSAGE' => 'Message displayed above the password asked password to modify forms',
'EDIT_CONFIG_HINT_ALLOW_DOUBLECLIC' => 'Allow double click to edit menus and special pages',
'EDIT_CONFIG_GROUP_CORE' => 'Main parameters',
'EDIT_CONFIG_GROUP_ACCESS' => "Access rights",
'EDIT_CONFIG_GROUP_EMAIL' => 'Emails',

// handlers/update
'UPDATE_ADMIN_PAGES' => 'Update admin pages',
'UPDATE_ADMIN_PAGES_CONFIRM' => 'Confirm pages\' update : ',
'UPDATE_ADMIN_PAGES_HINT' => 'Update admin pages with latest features. This is reversible.',
'UPDATE_ADMIN_PAGES_ERROR' => 'Not possible to update all admin pages !',

// handlers/revisions
'SUCCESS_RESTORE_REVISION' => "The version was restored",
'TITLE_PAGE_HISTORY' => 'Page history',
'TITLE_ENTRY_HISTORY' => 'Entry history',
'REVISION_VERSION' => 'Revision N°',
'REVISION_ON' => 'on',
'REVISION_BY' => 'by',
'CURRENT_VERSION' => 'Current revision',
'RESTORE_REVISION' => 'Restore this revision',
'DISPLAY_WIKI_CODE' => 'Display Wiki code',
));
