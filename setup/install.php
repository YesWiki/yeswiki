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
		<div class="jumbotron">
			<h1><?php echo _t('INSTALLATION_OF_YESWIKI'); ?></h1>
			<h4>(<?php echo YESWIKI_VERSION.' - '.YESWIKI_RELEASE; ?>)</h4>
			<p><?php echo _t('VERIFICATION_OF_DATAS_AND_DATABASE_INSTALLATION'); ?></p>
		</div>
<?php

// fetch configuration
$config = $config2 = $_POST['config'];

// merge existing (or default) configuration with new one
$config = array_merge($wakkaConfig, $config);

if (!$version = trim($wakkaConfig['wikini_version'])) {
    $version = '0';
}

if ($version) {
    test(_t('VERIFY_MYSQL_PASSWORD').' ...', isset($config2['mysql_password']) && $wakkaConfig['mysql_password'] === $config2['mysql_password'], _t('INCORRECT_MYSQL_PASSWORD').' !');
}
test(_t('TEST_MYSQL_CONNECTION').' ...', $dblink = mysqli_connect($config['mysql_host'], $config['mysql_user'], $config['mysql_password']));
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
        $admin_password == $admin_password_conf,
        _t('ADMIN_PASSWORD_ARE_DIFFERENT'),
        1
    );
} else {
    $admin_name = $_POST['admin_login'];
    unset($admin_password);
}

// do installation stuff
switch ($version) {
    // new installation
    case '0':
        echo '<br /><b>'._t('DATABASE_INSTALLATION')."</b><br>\n";
        test(
            _t('CREATION_OF_TABLE').' '.$config['table_prefix'].'pages ...',
            @mysqli_query(
                $dblink,
                'CREATE TABLE '.$config['table_prefix'].'pages ('.
                'id int(10) unsigned NOT NULL auto_increment,'.
                "tag varchar(50) NOT NULL default '',".
                "time datetime NOT NULL,".
                'body longtext NOT NULL,'.
                'body_r text NOT NULL,'.
                "owner varchar(50) NOT NULL default '',".
                "user varchar(50) NOT NULL default '',".
                "latest enum('Y','N') NOT NULL default 'N',".
                "handler varchar(30) NOT NULL default 'page',".
                "comment_on varchar(50) NOT NULL default '',".
                'PRIMARY KEY  (id),'.
                'FULLTEXT KEY tag (tag,body),'.
                'KEY idx_tag (tag),'.
                'KEY idx_time (time),'.
                'KEY idx_latest (latest),'.
                'KEY idx_comment_on (comment_on)'.
                ') ENGINE=MyISAM;'
            ),
            _t('ALREADY_CREATED').' ?',
            0
        );
        test(
            _t('CREATION_OF_TABLE').' '.$config['table_prefix'].'acls ...',
            @mysqli_query(
                $dblink,
                'CREATE TABLE '.$config['table_prefix'].'acls ('.
                "page_tag varchar(50) NOT NULL default '',".
                "privilege varchar(20) NOT NULL default '',".
                'list text NOT NULL,'.
                'PRIMARY KEY  (page_tag,privilege)'.
                ') ENGINE=MyISAM'
            ),
            _t('ALREADY_CREATED').' ?',
            0
        );
        test(
            _t('CREATION_OF_TABLE').' '.$config['table_prefix'].'links ...',
            @mysqli_query(
                $dblink,
                'CREATE TABLE '.$config['table_prefix'].'links ('.
                "from_tag char(50) NOT NULL default '',".
                "to_tag char(50) NOT NULL default '',".
                'UNIQUE KEY from_tag (from_tag,to_tag),'.
                'KEY idx_from (from_tag),'.
                'KEY idx_to (to_tag)'.
                ') ENGINE=MyISAM'
            ),
            _t('ALREADY_CREATED').' ?',
            0
        );
        test(
            _t('CREATION_OF_TABLE').' '.$config['table_prefix'].'referrers ...',
            @mysqli_query(
                $dblink,
                'CREATE TABLE '.$config['table_prefix'].'referrers ('.
                "page_tag char(50) NOT NULL default '',".
                "referrer char(150) NOT NULL default '',".
                "time datetime NOT NULL,".
                'KEY idx_page_tag (page_tag),'.
                'KEY idx_time (time)'.
                ') ENGINE=MyISAM'
            ),
            _t('ALREADY_CREATED').' ?',
            0
        );
        test(
            _t('CREATION_OF_TABLE').' '.$config['table_prefix'].'users ...',
            @mysqli_query(
                $dblink,
                'CREATE TABLE '.$config['table_prefix'].'users ('.
                "name varchar(80) NOT NULL default '',".
                "password varchar(32) NOT NULL default '',".
                "email varchar(50) NOT NULL default '',".
                'motto text,'.
                "revisioncount int(10) unsigned NOT NULL default '20',".
                "changescount int(10) unsigned NOT NULL default '50',".
                "doubleclickedit enum('Y','N') NOT NULL default 'Y',".
                "signuptime datetime NOT NULL,".
                "show_comments enum('Y','N') NOT NULL default 'N',".
                'PRIMARY KEY  (name),'.
                'KEY idx_name (name),'.
                'KEY idx_signuptime (signuptime)'.
                ') ENGINE=MyISAM'
            ),
            _t('ALREADY_CREATED').' ?',
            0
        );
        test(
            _t('CREATION_OF_TABLE').' '.$config['table_prefix'].'triples ...',
            @mysqli_query(
                $dblink,
                'CREATE TABLE `'.$config['table_prefix'].'triples` ('.
                '  `id` int(10) unsigned NOT NULL auto_increment,'.
                '  `resource` varchar(255) NOT NULL default \'\','.
                '  `property` varchar(255) NOT NULL default \'\','.
                '  `value` text NOT NULL,'.
                '  PRIMARY KEY  (`id`),'.
                '  KEY `resource` (`resource`),'.
                '  KEY `property` (`property`)'.
                ') ENGINE=MyISAM'
            ),
            _t('ALREADY_CREATED').' ?',
            0
        );
        test(
            _t('ADMIN_ACCOUNT_CREATION').' ...',
            @mysqli_query(
                $dblink,
                'insert into '.$config['table_prefix'].'users set '.
                'signuptime = now(), '.
                "name = '".mysqli_real_escape_string($dblink, $admin_name)."', ".
                "email = '".mysqli_real_escape_string($dblink, $admin_email)."', ".
                "motto = '', ".
                "password = md5('".mysqli_real_escape_string($dblink, $admin_password)."')"
            ),
            _t('ALREADY_EXISTING').'.',
            0
        );
        $wiki = new Wiki($config);
        $wiki->SetGroupACL('admins', $admin_name);

        //insertion des pages de documentation et des pages standards
        $d = dir('setup/doc/');
        while ($doc = $d->read()) {
            if (is_dir($doc) || substr($doc, -4) != '.txt') {
                continue;
            }
            $pagecontent = str_replace('{{root_page}}', $config['root_page'], implode('', file("setup/doc/$doc")));
            if ($doc == '_root_page.txt') {
                $pagename = $config['root_page'];
            } else {
                $pagename = substr($doc, 0, strpos($doc, '.txt'));
            }

            $sql = 'Select tag from '.$config['table_prefix']."pages where tag='$pagename'";

            // Insert documentation page if not present (a previous failed installation ?)
            if (($r = @mysqli_query($dblink, $sql)) && (mysqli_num_rows($r) == 0)) {
                $sql = 'Insert into '.$config['table_prefix'].'pages '.
                    "set tag = '$pagename', ".
                    "body = '".mysqli_real_escape_string($dblink, $pagecontent)."', ".
                    "body_r = '',".
                    "user = '".mysqli_real_escape_string($dblink, $admin_name)."', ".
                    "owner = '".mysqli_real_escape_string($dblink, $admin_name)."', ".
                    'time = now(), '.
                    "latest = 'Y'";
                test(_t('INSERTION_OF_PAGE')." $pagename ...", @mysqli_query($dblink, $sql), '?', 0);

                // update table_links
                $wiki->SetPage($wiki->LoadPage($pagename, '', 0));
                $wiki->ClearLinkTable();
                $wiki->StartLinkTracking();
                $wiki->TrackLinkTo($pagename);
                $dummy = $wiki->Header();
                $dummy .= $wiki->Format($pagecontent);
                $dummy .= $wiki->Footer();
                $wiki->StopLinkTracking();
                $wiki->WriteLinkTable();
                $wiki->ClearLinkTable();
            } else {
                test(_t('INSERTION_OF_PAGE')." $pagename ...", 0, _t('ALREADY_EXISTING').'.', 0);
            }
        }
        break;

    // The funny upgrading stuff. Make sure these are in order! //
    case '0.1':
    case '0.4.0':
    case '0.4.1':
    case '0.4.2':
    case '0.4.3':
    case '0.4.4':
    case '0.5.0':
        test(
            _t('CREATION_OF_TABLE').' '.$config['table_prefix'].'triples ...',
            @mysqli_query(
                $dblink,
                'CREATE TABLE `'.$config['table_prefix'].'triples` ('.
                '  `id` int(10) unsigned NOT NULL auto_increment,'.
                '  `resource` varchar(255) NOT NULL default \'\','.
                '  `property` varchar(255) NOT NULL default \'\','.
                '  `value` text NOT NULL,'.
                '  PRIMARY KEY  (`id`),'.
                '  KEY `resource` (`resource`),'.
                '  KEY `property` (`property`)'.
                ') ENGINE=MyISAM'
            ),
            _t('ALREADY_CREATED').' ?',
            0
        );
        if (!empty($admin_password)) {
            test(
                _t('ADMIN_ACCOUNT_CREATION').' ...',
                @mysqli_query(
                    $dblink,
                    'insert into '.$config['table_prefix'].'users set '.
                    'signuptime = now(), '.
                    "name = '".mysqli_real_escape_string($dblink, $admin_name)."', ".
                    "email = '".mysqli_real_escape_string($dblink, $admin_email)."', ".
                    "motto = '', ".
                    "password = md5('".mysqli_real_escape_string($dblink, $admin_password)."')"
                ),
                0
            );
        }
        $wiki = new Wiki($config);
        test(_t('INSERTION_OF_USER_IN_ADMIN_GROUP').' ...', !$wiki->SetGroupACL('admins', $admin_name), 0);
}

?>
<br />
<div class="alert alert-info"><?php echo _t('NEXT_STEP_WRITE_CONFIGURATION_FILE'); ?>
<tt><?php echo  $wakkaConfigLocation ?></tt>.</br>
<?php echo _t('VERIFY_YOU_HAVE_RIGHTS_TO_WRITE_FILE'); ?>.  </div>

<form action="<?php echo  myLocation(); ?>?installAction=writeconfig" method="POST">
<input type="hidden" name="config" value="<?php echo  htmlspecialchars(serialize($config), ENT_COMPAT, 'ISO-8859-1') ?>">
<div class="form-actions">
	<input class="btn btn-large btn-primary continuer" type="submit" value="<?php echo _t('CONTINUE'); ?>" />
</div>
</form>
