<?php

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
    $existingPassword = $wakkaConfig['db_password'] ?? $wakkaConfig['mysql_password'] ?? '';
    $newPassword = $config2['db_password'] ?? $config2['mysql_password'] ?? '';
    test(_t('VERIFY_MYSQL_PASSWORD') . ' ...', $existingPassword === $newPassword, _t('INCORRECT_MYSQL_PASSWORD') . ' !');
}

// Get database driver
$dbDriver = $config['db_driver'] ?? 'mysql';

$dblink = null;
$connectionSuccess = false;

// Handle connection based on driver type
if ($dbDriver === 'sqlite') {
    // SQLite: use fixed path in private directory
    $dbPath = 'private/yeswiki.db';
    $config['db_database'] = $dbPath;
    // Ensure private directory exists
    if (!is_dir('private')) {
        @mkdir('private', 0755, true);
    }
    $dsn = 'sqlite:' . $dbPath;
    try {
        $dblink = new \PDO($dsn, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $connectionSuccess = true;
    } catch (\PDOException $e) {
        $connectionSuccess = false;
    }
    test(_t('TEST_DATABASE_CONNECTION') . ' ...', $connectionSuccess);
} else {
    // MySQL or PostgreSQL: connect to server first
    if ($dbDriver === 'pgsql') {
        $dsnWithoutDb = 'pgsql:host=' . $config['db_host'];
        if (!empty($config['db_port'])) {
            $dsnWithoutDb .= ';port=' . $config['db_port'];
        }
    } else {
        $dsnWithoutDb = 'mysql:host=' . $config['db_host'];
        if (!empty($config['db_port'])) {
            $dsnWithoutDb .= ';port=' . $config['db_port'];
        }
    }

    try {
        $dblink = new \PDO($dsnWithoutDb, $config['db_user'], $config['db_password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $connectionSuccess = true;
    } catch (\PDOException $e) {
        $connectionSuccess = false;
    }
    test(_t('TEST_DATABASE_CONNECTION') . ' ...', $connectionSuccess);

    // Try to select/create database
    $dbExists = false;
    if ($dbDriver === 'pgsql') {
        // PostgreSQL: check if database exists
        try {
            $stmt = $dblink->query("SELECT 1 FROM pg_database WHERE datname = " . $dblink->quote($config['db_database']));
            $dbExists = $stmt->fetchColumn() !== false;
        } catch (\PDOException $e) {
            $dbExists = false;
        }
    } else {
        // MySQL: try to USE the database
        try {
            $dblink->exec('USE `' . $config['db_database'] . '`');
            $dbExists = true;
        } catch (\PDOException $e) {
            $dbExists = false;
        }
    }

    $testdb = test(
        _t('SEARCH_FOR_DATABASE') . ' ...',
        $dbExists,
        _t('NO_DATABASE_FOUND_TRY_TO_CREATE') . '.',
        0
    );

    if ($testdb == 1) {
        $createSuccess = false;
        try {
            if ($dbDriver === 'pgsql') {
                $dblink->exec('CREATE DATABASE ' . $config['db_database']);
            } else {
                $dblink->exec('CREATE DATABASE `' . $config['db_database'] . '`');
            }
            $createSuccess = true;
        } catch (\PDOException $e) {
            $createSuccess = false;
        }
        test(
            _t('TRYING_TO_CREATE_DATABASE') . ' ...',
            $createSuccess,
            _t('DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY') . ' !'
        );
    }

    // Reconnect with database selected
    try {
        if ($dbDriver === 'pgsql') {
            $dsnWithDb = 'pgsql:host=' . $config['db_host'] . ';dbname=' . $config['db_database'];
            if (!empty($config['db_port'])) {
                $dsnWithDb .= ';port=' . $config['db_port'];
            }
        } else {
            $dsnWithDb = 'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_database'];
            if (!empty($config['db_port'])) {
                $dsnWithDb .= ';port=' . $config['db_port'];
            }
        }
        $dblink = new \PDO($dsnWithDb, $config['db_user'], $config['db_password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (\PDOException $e) {
        test(
            _t('SEARCH_FOR_DATABASE') . ' ...',
            false,
            _t('DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT') . ' !',
            1
        );
    }
}

// Check for existing tables with the same prefix
$tableCheckQuery = '';
if ($dbDriver === 'sqlite') {
    $tableCheckQuery = "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE '{$config['table_prefix']}%'";
} elseif ($dbDriver === 'pgsql') {
    $tableCheckQuery = "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE '{$config['table_prefix']}%'";
} else {
    $tableCheckQuery = "SHOW TABLES LIKE '{$config['table_prefix']}%'";
}

$existingTables = [];
try {
    $stmt = $dblink->query($tableCheckQuery);
    $existingTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
} catch (\PDOException $e) {
    $existingTables = [];
}

test(
    _t('CHECK_EXISTING_TABLE_PREFIX') . ' ...',
    empty($existingTables),
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

// Check the admin name based on the function sanitizeName() in UserController
test(
    _t('CHECKING_THE_ADMIN_NAME') . ' ...',
    !empty($admin_name) && is_string($admin_name) && strlen($admin_name) <= 80 && preg_match('/^[^!#@<>\\\\\/][^<>\\\\\/]{2,}$/', $admin_name),
    _t('USER_THIS_IS_NOT_A_VALID_NAME'),
    1
);

$config['root_page'] = trim($config['root_page']);
test(
    _t('CHECKING_ROOT_PAGE_NAME') . ' ...',
    preg_match('/^' . WN_CAMEL_CASE_EVOLVED . '$/', $config['root_page']),
    _t('INCORRECT_ROOT_PAGE_NAME'),
    1
);

// Set charset based on driver
if ($dbDriver === 'mysql') {
    $dblink->exec('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
} elseif ($dbDriver === 'pgsql') {
    $dblink->exec("SET client_encoding TO 'UTF8'");
} elseif ($dbDriver === 'sqlite') {
    $dblink->exec('PRAGMA foreign_keys = ON');
}

$replacements = [
    'prefix' => $config['table_prefix'],
    'siteTitle' => $config['wakka_name'],
    'WikiName' => $admin_name,
    'password' => md5($admin_password),  // Hash password in PHP for all database types
    'email' => $admin_email,
    'rootPage' => $config['root_page'],
    'url' => $config['base_url'],
];

// Determine which SQL file to use based on driver
$sqlFileBase = 'setup/sql/create-tables';
$sqlFile = $sqlFileBase . '.sql';
if ($dbDriver !== 'mysql' && file_exists($sqlFileBase . '-' . $dbDriver . '.sql')) {
    $sqlFile = $sqlFileBase . '-' . $dbDriver . '.sql';
}

// tables, admin user and admin group creation
echo '<br /><b>' . _t('DATABASE_INSTALLATION') . "</b><br>\n";
$dblink->beginTransaction();
$result = @querySqlFile($dblink, $sqlFile, $replacements);
if (!$result) {
    $dblink->rollBack();
}
test(
    _t('CREATION_OF_TABLES') . ' ...',
    $result,
    _t('NOT_POSSIBLE_TO_CREATE_SQL_TABLES') . ' ?',
    1
);

// Default pages content
$sqlFileBase = 'setup/sql/default-content';
$sqlFile = $sqlFileBase . '.sql';
if ($dbDriver !== 'mysql' && file_exists($sqlFileBase . '-' . $dbDriver . '.sql')) {
    $sqlFile = $sqlFileBase . '-' . $dbDriver . '.sql';
}

$result = @querySqlFile($dblink, $sqlFile, $replacements);
if (!$result) {
    $dblink->rollBack();
    foreach ($tablesNames as $tableName) {
        try {
            // Check if table exists (driver-specific)
            $tableExists = false;
            $fullTableName = $config['table_prefix'] . $tableName;
            if ($dbDriver === 'sqlite') {
                $stmt = $dblink->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$fullTableName'");
                $tableExists = $stmt->fetchColumn() !== false;
            } elseif ($dbDriver === 'pgsql') {
                $stmt = $dblink->query("SELECT tablename FROM pg_tables WHERE schemaname='public' AND tablename='$fullTableName'");
                $tableExists = $stmt->fetchColumn() !== false;
            } else {
                $stmt = $dblink->query("SHOW TABLES LIKE '$fullTableName'");
                $tableExists = $stmt->rowCount() !== 0;
            }

            if ($tableExists) {
                $countStmt = $dblink->query("SELECT COUNT(*) FROM " . ($dbDriver === 'mysql' ? "`$fullTableName`" : "\"$fullTableName\""));
                if ($countStmt->fetchColumn() === 0) { /* empty table */
                    $dblink->exec("DROP TABLE IF EXISTS " . ($dbDriver === 'mysql' ? "`$fullTableName`" : "\"$fullTableName\""));
                }
            }
        } catch (\Throwable $th) {
        }
    }
} else {
    $dblink->commit();
}
test(
    _t('INSERTION_OF_PAGES') . ' ...',
    $result,
    _t('ALREADY_CREATED') . ' ?',
    1
);

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
    <?php echo _t('VERIFY_YOU_HAVE_RIGHTS_TO_WRITE_FILE'); ?>.
</div>
<?php
$_POST['config'] = json_encode($config);
require_once 'setup/writeconfig.php';
