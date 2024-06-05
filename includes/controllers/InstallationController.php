<?php

namespace YesWiki\Core\Controller;

class InstallationController
{
    protected $config;
    protected $configPosted;
    protected $env;
    protected $step;
    protected $baseUrl;
    protected $twig;
    protected $dbLink;
    protected $tableNames;

    public function __construct()
    {
        // default lang
        loadpreferredI18n('');
        $this->env = \YesWiki\Init::$env;
        $this->config = \YesWiki\Init::getConfig();
        // set version to current version, yay!
        $this->config['wikini_version'] = WIKINI_VERSION;
        $this->config['wakka_version'] = WAKKA_VERSION;
        $this->config['yeswiki_version'] = YESWIKI_VERSION;
        $this->config['yeswiki_release'] = YESWIKI_RELEASE;
        if (isset($_POST['config']) && is_string($_POST["config"])) {
            $_POST['config'] = json_decode(html_entity_decode($_POST["config"]), true);
        }
        //environment vars have priority
        $this->configPosted = array_merge($_POST['config'] ?? [], $this->env);

        $this->step = $this->getInstallationStep();
        list($this->baseUrl,) = explode("?", $_SERVER["REQUEST_URI"]);
        $this->twig = $this->setupTwig();
        // list of tableNames
        $this->tableNames = [
            'acls',
            'links',
            'nature',
            'pages',
            'referrers',
            'triples',
            'users',
        ];
    }

    public function show()
    {
        $charset = 'UTF-8';
        header("Content-Type: text/html; charset=$charset");
        // merge existing (or default) configuration with posted one
        $this->config = array_merge($this->config, $this->configPosted);
        $options = [
            'baseUrl' => computeBaseUrl(true),
            'charset' => $charset,
            'config' => $this->config,
            'env' => $this->env,
            'locale' => $GLOBALS['prefered_language'],
            'availableLanguages' => $GLOBALS['available_languages'],
            'languagesList' => $GLOBALS['languages_list'],
            'yeswikiVersion' => ucfirst(YESWIKI_VERSION) . ' ' . YESWIKI_RELEASE,
            'contentSQL' => $_POST['contentSQL'] ?? file_exists('private/backups/content.sql') ? 'private/backups/content.sql' : 'default',
            'WikiName' => $_POST['admin_name'] ?? '',
            'password' => $_POST['admin_password'] ?? '',
            'email' => $_POST['admin_email'] ?? '',
            'createAdminGroupAndUser' => true,
        ];
        $output = '';
        switch ($this->step) {
            case 'default':
                $options['template'] = 'installation-form.twig';
                $options['pattern'] = WN_CAMEL_CASE_EVOLVED;
                $output = $this->twig->render('installation.twig', $options);
                break;

            case 'install':
                // init vars used by twig
                $messages = [];
                try {
                    $messages['VERIFY_MYSQL_PASSWORD'] = $this->check(_t('VERIFY_MYSQL_PASSWORD'));
                    $messages['TEST_MYSQL_CONNECTION'] = $this->check(_t('TEST_MYSQL_CONNECTION'));
                    $messages['SEARCH_FOR_DATABASE'] = $this->check(_t('SEARCH_FOR_DATABASE'));
                    if ($messages['SEARCH_FOR_DATABASE']['result'] == 'warning') {
                        $messages['TRYING_TO_CREATE_DATABASE'] = $this->check(_t('TRYING_TO_CREATE_DATABASE'));
                        $messages['SEARCH_AGAIN_FOR_DATABASE'] = $this->check(_t('SEARCH_AGAIN_FOR_DATABASE'));
                    }

                    // at this point we can use the right database and set all in utf8mb4
                    mysqli_select_db($this->dbLink, $this->config['mysql_database']);
                    mysqli_set_charset($this->dbLink, 'utf8mb4');
                    mysqli_query($this->dbLink, 'SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');

                    $messages['CHECK_EXISTING_TABLE_PREFIX'] = $this->check(_t('CHECK_EXISTING_TABLE_PREFIX'));
                    if ($options['contentSQL'] === 'private/backups/content.sql') {
                        $messages['IMPORT_DB_BACKUP'] = $this->check(_t('IMPORT_DB_BACKUP'), $options);
                    } else {
                        $messages['CREATION_OF_TABLES'] = $this->check(_t('CREATION_OF_TABLES'), $options);
                        $messages['INSERTION_OF_PAGES'] = $this->check(_t('INSERTION_OF_PAGES'), $options);
                    }
                    $messages['WRITE_ROBOT_TXT'] = $this->check(_t('WRITE_ROBOT_TXT'));
                    $messages['WRITE_CONFIG'] = $this->check(_t('WRITE_CONFIG'));
                    // INSTALLATION SUCCESSFULL !
                    header('Location: ' . $options['config']['base_url'] . $options['config']['root_page']);
                    exit;
                } catch (\Exception $ex) {
                    $options['template'] = 'installation-database.twig';
                    $options['messages'] = $messages;
                    $options['error'] = $ex->getMessage();
                    $options['configPosted'] = htmlspecialchars(
                        json_encode($this->configPosted),
                        ENT_COMPAT,
                        YW_CHARSET
                    );
                    $output = $this->twig->render('installation.twig', $options);
                }
                break;
        }
        echo $output;
    }

    protected function writeRobotsTxtFile()
    {
        // Config indexation by robots
        if (!isset($this->config['allow_robots']) || $this->config['allow_robots'] != '1') {
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
                        "User-agent: *$1Disallow: /$1",
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

            // set meta
            $this->config['meta'] = array_merge(
                $this->config['meta'] ?? [],
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
                        "User-agent: *$1Allow: /$1",
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
        }

        // do not save this config because not use by YesWiki
        if (isset($this->config['allow_robots'])) {
            unset($this->config['allow_robots']);
        }

        // save robots.txt file
        return file_put_contents('robots.txt', $robotFile);
    }

    protected function writeConfigFile()
    {
        // convert to boolean some values that should be booleans
        foreach (['allow_raw_html', 'rewrite_mode'] as $name) {
            if (isset($this->config[$name])) {
                $this->config[$name] = (in_array($this->config[$name], ['1', true, 'true'])) ? true : false;
            }
        }

        // convert config array into PHP code
        $configCode = "<?php\n// wakka.config.php " . _t('CREATED') . ' ' . date('c') . "\n
          // " . _t('DONT_CHANGE_YESWIKI_VERSION_MANUALLY') . " !\n\n\$wakkaConfig = ";
        if (function_exists('var_export')) {
            // var_export gives a better result but was added in php 4.2.0 (wikini asks only php 4.1.0)
            $configCode .= var_export($this->config, true) . ";\n?>";
        } else {
            $configCode .= "array(\n";
            foreach ($this->config as $k => $v) {
                // avoid problems with quotes and slashes
                $entries[] = "\t'" . $k . "' => '" . str_replace(array('\\', "'"), array('\\\\', '\\\''), $v) . "'";
            }
            $configCode .= implode(",\n", $entries) . ");\n\n";
        }
        try {
            $fp = fopen(\YesWiki\Init::configFile, 'w');
            fwrite($fp, $configCode);
            fclose($fp);
        } catch (\Throwable $th) {
            throw new \Exception(_t('CONFIGURATION_FILE_NOT_CREATED') . '<br />'
                . _t('ERROR') . ' "' . $th->getMessage() . '"');
        }
        // we return true so the result is considered a success
        return true;
    }

    private function getInstallationStep(): string
    {
        if (!isset($_REQUEST['installAction']) || !$installAction = trim($_REQUEST['installAction'])) {
            $installAction = 'default';
        }
        return $installAction;
    }

    private function setupTwig()
    {
        // Set up twig
        $twigLoader = new \Twig\Loader\FilesystemLoader('./');
        $twigLoader->addPath('templates');
        $debugMode = $this->env['debug'] ?? $this->config['debug'] ?? false;
        $twig = new \Twig\Environment($twigLoader, [
            'debug' => $debugMode == 'yes' || $debugMode == true,
            'cache' => 'cache/templates/',
            'auto_reload' => true
        ]);
        if ($debugMode) {
            $twig->addExtension(new \Twig\Extension\DebugExtension());
        }
        $translateFunc = new \Twig\TwigFunction('_t', function ($key, $params = []) {
            return html_entity_decode(_t($key, $params));
        });
        $twig->addFunction($translateFunc);
        return $twig;
    }

    /**
     * Checks different steps in installation and return result and messages
     *
     * @param string $step Step's description
     * @param array $option extra parameters if needed
     * @return array $message explanation text and status
     */
    private function check($step, $options = [])
    {
        $message = [];
        switch ($step) {
            case _t('VERIFY_MYSQL_PASSWORD'):
                if (
                    isset($this->configPosted['mysql_password'])
                    && isset($this->configPosted['mysql_password'])
                    && $this->config['mysql_password'] === $this->configPosted['mysql_password']
                ) {
                    $message = [
                        'result' => 'success',
                        'output' => $step,
                    ];
                } else {
                    throw new \Exception($step . ' :<br />' . _t('INCORRECT_MYSQL_PASSWORD'));
                }
                break;

            case _t('TEST_MYSQL_CONNECTION'):
                try {
                    $this->dbLink = mysqli_connect(
                        $this->config['mysql_host'],
                        $this->config['mysql_user'],
                        $this->config['mysql_password']
                    );
                    $message = [
                        'result' => 'success',
                        'output' => $step,
                    ];
                } catch (\Throwable $th) {
                    throw new \Exception($step . ' :<br />'
                        . _t('INCORRECT_MYSQL_HOST_PASSWORD_OR_USER') . '<br />'
                        . _t('ERROR') . ' "' . $th->getMessage() . '"');
                }
                break;

            case _t('SEARCH_FOR_DATABASE'):
                $sql = 'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = "'
                    . $this->config['mysql_database'] . '"';
                $result = mysqli_query($this->dbLink, $sql);
                $findDB = $result->fetch_array(MYSQLI_ASSOC);
                if ($findDB) {
                    $message = [
                        'result' => 'success',
                        'output' => $step . ' "' . $this->config['mysql_database'] . '"',
                    ];
                } else {
                    $message = [
                        'result' => 'warning',
                        'output' => $step . ' "' . $this->config['mysql_database'] . '"' . ' :<br />'
                            . _t('NOT_POSSIBLE_TO_FIND_DATABASE') . ' "' . $this->config['mysql_database'] . '"',
                    ];
                }
                break;

            case _t('TRYING_TO_CREATE_DATABASE'):
                try {
                    @mysqli_query($this->dbLink, 'CREATE DATABASE ' . $this->config['mysql_database']);
                    $message = [
                        'result' => 'success',
                        'output' => $step . ' "' . $this->config['mysql_database'] . '"',
                    ];
                } catch (\Throwable $th) {
                    throw new \Exception($step . ' "' . $this->config['mysql_database'] . '"'
                        . ' :<br />' . _t('DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY')
                        . ' "' . $this->config['mysql_database'] . '"');
                }
                break;

            case _t('SEARCH_AGAIN_FOR_DATABASE'):
                $sql = 'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = "'
                    . $this->config['mysql_database'] . '"';
                $result = mysqli_query($this->dbLink, $sql);
                $findDB = $result->fetch_array(MYSQLI_ASSOC);
                if ($findDB) {
                    $message = [
                        'result' => 'success',
                        'output' => $step . ' "' . $this->config['mysql_database'] . '"',
                    ];
                } else {
                    throw new \Exception($step . ' "' . $this->config['mysql_database'] . '"'
                        . ' :<br />' . _t('DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY'));
                }
                break;

            case _t('CHECK_EXISTING_TABLE_PREFIX'):
                $existingTables = array_filter($this->tableNames, function ($tableName) {
                    $res = @mysqli_query($this->dbLink, "SHOW TABLES LIKE \"{$this->config['table_prefix']}$tableName\"");
                    return mysqli_num_rows($res) !== 0;
                });
                if (empty($existingTables)) {
                    $message = [
                        'result' => 'success',
                        'output' => $step,
                    ];
                } else {
                    throw new \Exception($step . ' :<br />' . _t('TABLE_PREFIX_ALREADY_USED') . '"'
                        . $this->config['table_prefix'] . '". ' . _t('FIND_NEW_ONE'));
                }
                break;

            case _t('IMPORT_DB_BACKUP'):
                mysqli_begin_transaction($this->dbLink);
                mysqli_autocommit($this->dbLink, false);
                $sql = file_get_contents('private/backups/content.sql');
                // find and replace prefix
                preg_match_all('/`(.*)acls`/m', $sql, $matches, PREG_SET_ORDER, 0);
                $prefix = $matches[0][1] ?? null;
                if ($prefix && $prefix !== $options['config']['table_prefix']) {
                    $sql = str_replace($prefix, $options['config']['table_prefix'], $sql);
                } else {
                    throw new \Exception($step . ' :<br />' . _t('NOT_POSSIBLE_TO_IMPORT_BACKUP_SQL'));
                }
                try {
                    $templine = '';
                    $result = false;
                    // we allow big values to import
                    mysqli_query($this->dbLink, "set global net_buffer_length=1000000;");
                    mysqli_query($this->dbLink, "set global max_allowed_packet=1000000000;");
                    foreach (explode("\n", $sql) as $line) {
                        if (substr($line, 0, 2) == '--' || $line == '') {
                            continue;
                        }
                        $templine .= $line;
                        if (substr(trim($line), -1, 1) == ';') {
                            mysqli_query($this->dbLink, $templine);
                            $templine = '';
                        }
                    }
                    $result = true;
                } catch (\Throwable $th) {
                    throw new \Exception($step . ' :<br />' . _t('NOT_POSSIBLE_TO_IMPORT_BACKUP_SQL') . '<br />'
                        . _t('ERROR') . ' "' . $th->getMessage() . '"');
                }
                if ($result) {
                    mysqli_commit($this->dbLink);
                    $message = [
                        'result' => 'success',
                        'output' => $step,
                    ];
                } else {
                    mysqli_rollback($this->dbLink);
                    throw new \Exception($step . ' :<br />' . _t('NOT_POSSIBLE_TO_IMPORT_BACKUP_SQL'));
                }
                mysqli_autocommit($this->dbLink, true);
                break;

            case _t('CREATION_OF_TABLES'):
                mysqli_begin_transaction($this->dbLink);
                mysqli_autocommit($this->dbLink, false);
                $sql = $this->twig->render('installation-database-create-tables.sql.twig', $options);
                $result = $this->querySql($this->dbLink, $sql);
                if ($result) {
                    mysqli_commit($this->dbLink);
                    $message = [
                        'result' => 'success',
                        'output' => $step,
                    ];
                } else {
                    mysqli_rollback($this->dbLink);
                    throw new \Exception($step . ' :<br />' . _t('NOT_POSSIBLE_TO_CREATE_SQL_TABLES'));
                }
                mysqli_autocommit($this->dbLink, true);
                break;

            case _t('INSERTION_OF_PAGES'):
                mysqli_begin_transaction($this->dbLink);
                mysqli_autocommit($this->dbLink, false);
                // Default pages content
                $sql = $this->twig->render('installation-database-minimal-content.sql.twig', $options);
                $result = $this->querySql($this->dbLink, $sql);
                if ($result) {
                    mysqli_commit($this->dbLink);
                    $message = [
                        'result' => 'success',
                        'output' => $step,
                    ];
                } else {
                    mysqli_rollback($this->dbLink);
                    throw new \Exception($step . ' :<br />' . _t('ALREADY_CREATED') . ' ?');
                }
                mysqli_autocommit($this->dbLink, true);
                break;

            case _t('WRITE_ROBOT_TXT'):
                $result = $this->writeRobotsTxtFile();
                if ($result) {
                    $message = [
                        'result' => 'success',
                        'output' => $step,
                    ];
                } else {
                    throw new \Exception($step . ' :<br />' . _t('ROBOT_TXT_NOT_WRITABLE') . ' ?');
                }
                break;

            case _t('WRITE_CONFIG'):
                $result = $this->writeConfigFile();
                if ($result) {
                    $message = [
                        'result' => 'success',
                        'output' => $step,
                    ];
                } else {
                    throw new \Exception($step . ' :<br />' . _t('CONFIGURATION_FILE_NOT_WRITABLE') . '.');
                }
                break;
        }
        return $message;
    }

    /**
     * Query a multiline SQL query
     *
     * @param mixed $dbLink Database link
     * @param string $sql SQL query
     *
     * @return boolean true if success, false if failure
     */
    private function querySql($dbLink, $sql)
    {
        if (!mysqli_multi_query($dbLink, $sql)) {
            return false;
        }
        while (mysqli_more_results($dbLink)) {
            if (!mysqli_next_result($dbLink)) {
                return false;
            }
        }
        return true;
    }
}
