<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

if (empty($_POST['config'])) {
    header('Location: ' . myLocation());
    exit(_t('PROBLEM_WHILE_INSTALLING'));
}
?>

<?php

echo '<h2>' . _t('VERIFICATION_OF_DATAS_AND_DATABASE_INSTALLATION') . '</h2>';

// fetch configuration
$config = $config2 = $_POST['config'];
// merge existing (or default) configuration with new one
$config = array_merge($wakkaConfig, $config);
// set version to current version, yay!
$config['wikini_version'] = WIKINI_VERSION;
$config['wakka_version'] = WAKKA_VERSION;
$config['yeswiki_version'] = YESWIKI_VERSION;
$config['yeswiki_release'] = YESWIKI_RELEASE;
// default var
$config['htmlPurifierActivated'] = true; // TODO ectoplasme remove this line
// list of tableNames
$tablesNames = ['pages', 'links', 'referrers', 'nature', 'triples', 'users', 'acls'];

if (!$version = trim($wakkaConfig['wikini_version'])) {
    $version = '0';
}

if ($version) {
    test(_t('VERIFY_MYSQL_PASSWORD') . ' ...', isset($config2['mysql_password']) && $wakkaConfig['mysql_password'] === $config2['mysql_password'], _t('INCORRECT_MYSQL_PASSWORD') . ' !');
}
test(_t('TEST_MYSQL_CONNECTION') . ' ...', $dblink = @mysqli_connect($config['mysql_host'], $config['mysql_user'], $config['mysql_password']));

$testdb = test(
    _t('SEARCH_FOR_DATABASE') . ' ...',
    @mysqli_select_db($dblink, $config['mysql_database']),
    _t('NO_DATABASE_FOUND_TRY_TO_CREATE') . '.',
    0
);
if ($testdb == 1) {
    test(
        _t('TRYING_TO_CREATE_DATABASE') . ' ...',
        @mysqli_query($dblink, 'CREATE DATABASE ' . $config['mysql_database']),
        _t('DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY') . ' !'
    );
    test(
        _t('SEARCH_FOR_DATABASE') . ' ...',
        @mysqli_select_db($dblink, $config['mysql_database']),
        _t('DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT') . ' !',
        1
    );
}
test(
    _t('CHECK_EXISTING_TABLE_PREFIX') . ' ...',
    empty(array_filter($tablesNames, function ($tableName) use ($dblink, $config) {
        return mysqli_num_rows(@mysqli_query($dblink, "SHOW TABLES LIKE \"{$config['table_prefix']}$tableName\"")) !== 0;
    })),
    _t('TABLE_PREFIX_ALREADY_USED') . ' !',
    1
);

if (!$version || empty($_POST['admin_login'])) {
    $admin_name = $_POST['admin_name'];
    $admin_email = $_POST['admin_email'];
    $admin_password = $_POST['admin_password'];
    $admin_password_conf = $_POST['admin_password_conf'];
    test(
        _t('CHECKING_THE_ADMIN_PASSWORD') . ' ...',
        strlen($admin_password) >= 5,
        _t('PASSWORD_TOO_SHORT'),
        1
    );
    test(
        _t('CHECKING_THE_ADMIN_PASSWORD_CONFIRMATION') . ' ...',
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
    _t('CHECKING_ROOT_PAGE_NAME') . ' ...',
    preg_match('/^' . WN_CAMEL_CASE_EVOLVED . '$/', $config['root_page']),
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
    'url' => $config['base_url'],
];

// tables, admin user and admin group creation
echo '<br /><b>' . _t('DATABASE_INSTALLATION') . "</b><br>\n";
mysqli_begin_transaction($dblink);
mysqli_autocommit($dblink, false);
$result = @querySqlFile($dblink, 'setup/sql/create-tables.sql', $replacements);
if (!$result) {
    mysqli_rollback($dblink);
}
test(
    _t('CREATION_OF_TABLES') . ' ...',
    $result,
    _t('NOT_POSSIBLE_TO_CREATE_SQL_TABLES') . ' ?',
    1
);

// Default pages content
$result = @querySqlFile($dblink, 'setup/sql/default-content.sql', $replacements);
if (!$result) {
    mysqli_rollback($dblink);
    foreach ($tablesNames as $tableName) {
        try {
            if (mysqli_num_rows(mysqli_query($dblink, "SHOW TABLES LIKE \"{$config['table_prefix']}$tableName\";")) !== 0 // existing table
                && mysqli_num_rows(mysqli_query($dblink, "SELECT * FROM `{$config['table_prefix']}$tableName`;")) === 0) { /* empty table */
                mysqli_query($dblink, "DROP TABLE IF EXISTS `{$config['table_prefix']}$tableName`;");
            }
        } catch (\Throwable $th) {
        }
    }
} else {
    mysqli_commit($dblink);
}
test(
    _t('INSERTION_OF_PAGES') . ' ...',
    $result,
    _t('ALREADY_CREATED') . ' ?',
    1
);
mysqli_autocommit($dblink, true);

// Config indexation by robots
if (!isset($config['allow_robots']) || $config['allow_robots'] != '1') {
    // update robots.txt file
    if (file_exists('robots.txt')) {
        $robotFile = file_get_contents('robots.txt');
        // replace text
        if (preg_match(
            "/User-agent: \*(\r?\n?)(?:\s*(?:Disa|A)llow:\s*\/\s*)?/",
            $robotFile,
            $matches
        )) {
            $robotFile = preg_replace(
                "/User-agent: \*(\r?\n?)(?:\s*(?:Disa|A)llow:\s*\/\s*)?/",
                'User-agent: *$1Disallow: /$1',
                $robotFile
            );
        } else {
            $robotFile .= "\nUser-agent: *\n";
            $robotFile .= "Disallow: /\n";
        }
    } else {
        $robotFile = "User-agent: *\n";
        $robotFile .= "Disallow: /\n";
    }
    // save robots.txt file
    file_put_contents('robots.txt', $robotFile);

    // set meta
    $config['meta'] = array_merge(
        $config['meta'] ?? [],
        ['robots' => 'noindex,nofollow,max-image-preview:none,noarchive,noimageindex']
    );
} else {
    if (file_exists('robots.txt')) {
        $robotFile = file_get_contents('robots.txt');
        // replace text
        if (preg_match(
            "/User-agent: \*(\r?\n?)(?:\s*(?:Disa|A)llow:\s*\/\s*)?/",
            $robotFile,
            $matches
        )) {
            $robotFile = preg_replace(
                "/User-agent: \*(\r?\n?)(?:\s*(?:Disa|A)llow:\s*\/\s*)?/",
                'User-agent: *$1Allow: /$1',
                $robotFile
            );
        } else {
            $robotFile .= "\nUser-agent: *\n";
            $robotFile .= "Allow: /\n";
        }
    } else {
        $robotFile = "User-agent: *\n";
        $robotFile .= "Allow: /\n";
    }
    // save robots.txt file
    file_put_contents('robots.txt', $robotFile);
}

if (isset($config['allow_robots'])) {
    // do not save this config because not use by YesWiki
    unset($config['allow_robots']);
}

// update some values
foreach (['allow_raw_html', 'rewrite_mode'] as $name) {
    if (isset($config[$name])) {
        $config[$name] = (in_array($config[$name], ['1', true, 'true'])) ? true : false;
    }
}

?>
<br />
<div class="alert alert-info"><?php echo _t('NEXT_STEP_WRITE_CONFIGURATION_FILE'); ?>
<tt><?php echo $wakkaConfigLocation; ?></tt>.</br>
<?php echo _t('VERIFY_YOU_HAVE_RIGHTS_TO_WRITE_FILE'); ?>.  </div>
<?php
$_POST['config'] = json_encode($config);
require_once 'setup/writeconfig.php';
