<?php
/*
install.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Patrick PAUL
Copyright  2003  Eric FELDSTEIN
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

if (empty($_POST['config'])) {
    header('Location: '.myLocation());
    die(_t('PROBLEM_WHILE_INSTALLING'));
}
?>

<?php

echo '<h2>'._t('VERIFICATION_OF_DATAS_AND_DATABASE_INSTALLATION').'</h2>';

// fetch configuration
$config = $config2 = $_POST['config'];
// merge existing (or default) configuration with new one
$config = array_merge($wakkaConfig, $config);
// set version to current version, yay!
$config['wikini_version'] = WIKINI_VERSION;
$config['wakka_version'] = WAKKA_VERSION;
$config['yeswiki_version'] = YESWIKI_VERSION;
$config['yeswiki_release'] = YESWIKI_RELEASE;

if (!$version = trim($wakkaConfig['wikini_version'])) {
    $version = '0';
}

if ($version) {
    test(_t('VERIFY_MYSQL_PASSWORD').' ...', isset($config2['mysql_password']) && $wakkaConfig['mysql_password'] === $config2['mysql_password'], _t('INCORRECT_MYSQL_PASSWORD').' !');
}
test(_t('TEST_MYSQL_CONNECTION').' ...', $dblink = @mysqli_connect($config['mysql_host'], $config['mysql_user'], $config['mysql_password']));

$testdb = test(
    _t('SEARCH_FOR_DATABASE').' ...',
    @mysqli_select_db($dblink, $config['mysql_database']),
    _t('NO_DATABASE_FOUND_TRY_TO_CREATE').'.',
    0
);
if ($testdb == 1) {
    test(
        _t('TRYING_TO_CREATE_DATABASE').' ...',
        @mysqli_query($dblink, 'CREATE DATABASE '.$config['mysql_database']),
        _t('DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY').' !'
    );
    test(
        _t('SEARCH_FOR_DATABASE').' ...',
        @mysqli_select_db($dblink, $config['mysql_database']),
        _t('DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT').' !',
        1
    );
}
test(
    _t('CHECK_EXISTING_TABLE_PREFIX').' ...',
    (mysqli_num_rows(@mysqli_query($dblink, 'SHOW TABLES LIKE \''.$config['table_prefix'].'pages\'')) === 0),
    _t('TABLE_PREFIX_ALREADY_USED').' !',
    1
);

if (!$version || empty($_POST['admin_login'])) {
    $admin_name = $_POST['admin_name'];
    $admin_email = $_POST['admin_email'];
    $admin_password = $_POST['admin_password'];
    $admin_password_conf = $_POST['admin_password_conf'];
    test(
        _t('CHECKING_THE_ADMIN_PASSWORD').' ...',
        strlen($admin_password) >= 5,
        _t('PASSWORD_TOO_SHORT'),
        1
    );
    test(
        _t('CHECKING_THE_ADMIN_PASSWORD_CONFIRMATION').' ...',
        $admin_password === $admin_password_conf,
        _t('ADMIN_PASSWORD_ARE_DIFFERENT'),
        1
    );
} else {
    $admin_name = $_POST['admin_login'];
    unset($admin_password);
}

$config['root_page'] = trim($config['root_page']);
test(
    _t('CHECKING_ROOT_PAGE_NAME').' ...',
    preg_match('/^'.WN_CAMEL_CASE_EVOLVED.'$/', $config['root_page']),
    _t('INCORRECT_ROOT_PAGE_NAME'),
    1
);

// all in utf8mb4
mysqli_set_charset($dblink, 'utf8mb4');
mysqli_query($dblink, 'SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
$replacements = [
    'prefix' => $config['table_prefix'],
    'siteTitle' => $config['wakka_name'],
    'WikiName' => $admin_name,
    'password' => $admin_password,
    'email' => $admin_email,
    'rootPage' => $config['root_page'],
    'url' => $config['base_url']
];

// tables, admin user and admin group creation
echo '<br /><b>'._t('DATABASE_INSTALLATION')."</b><br>\n";
test(
    _t('CREATION_OF_TABLES').' ...',
    @querySqlFile($dblink, 'setup/sql/create-tables.sql', $replacements),
    _t('NOT_POSSIBLE_TO_CREATE_SQL_TABLES').' ?',
    1
);

// Default pages content
test(
    _t('INSERTION_OF_PAGES').' ...',
    @querySqlFile($dblink, 'setup/sql/default-content.sql', $replacements),
    _t('ALREADY_CREATED').' ?',
    0
);

// Config indexation by robots
if (!isset($config['allow_robots']) || $config['allow_robots'] != '1') {
    // update robots.txt file
    if (file_exists('robots.txt')) {
        $robotFile = file_get_contents('robots.txt');
        // Append User-agent
        $strToAppend = 'User-agent: *';
        $endLine = "\n";
        if (strpos($strToAppend."\r\n", $robotFile) != false) {
            $endLine = "\r\n";
        }

        $robotFile = str_replace(
            $strToAppend.$endLine,
            $strToAppend.$endLine.
            'Disallow: /'.$endLine,
            $robotFile
        );
    } else {
        $robotFile .= "User-agent: *\r\n";
        $robotFile .= "Disallow: /\r\n";
    }
    // save robots.txt file
    file_put_contents('robots.txt', $robotFile);

    // set meta
    $config['meta'] = array_merge(
        $config['meta'] ?? [],
        ['robots' => 'noindex,nofollow,max-image-preview:none,noarchive,noimageindex']
    );
}


if (isset($config['allow_robots'])) {
    // do not save this config because not use by YesWiki
    unset($config['allow_robots']);
}

// update some values
foreach (['allow_raw_html','rewrite_mode'] as $name) {
    if (isset($config[$name])) {
        $config[$name] = (in_array($config[$name], ['1',true,'true'])) ? true : false;
    }
}

?>
<br />
<div class="alert alert-info"><?php echo _t('NEXT_STEP_WRITE_CONFIGURATION_FILE'); ?>
<tt><?php echo  $wakkaConfigLocation ?></tt>.</br>
<?php echo _t('VERIFY_YOU_HAVE_RIGHTS_TO_WRITE_FILE'); ?>.  </div>
<?php
$_POST['config'] = json_encode($config);
require_once 'setup/writeconfig.php';
