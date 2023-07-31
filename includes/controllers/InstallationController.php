<?php

namespace YesWiki\Core\Controller;

class InstallationController
{
    protected $config;
    protected $env;
    protected $step;
    protected $baseUrl;
    protected $twig;
    
    public function __construct() {
        // default lang
        loadpreferredI18n('');
        $this->env = \YesWiki\Init::$env;
        $this->config = \YesWiki\Init::getConfig();
        $this->step = $this->getInstallationStep();
        list($this->baseUrl, ) = explode("?", $_SERVER["REQUEST_URI"]);
        $this->twig = $this->setupTwig();
    }
    
    public function show()
    {
        $output = '';

        // Init twig 
        $charset='UTF-8';
        // TODO is this usefull ?
        if (!defined('YW_CHARSET')) {
            define('YW_CHARSET', $charset);
        }
        header("Content-Type: text/html; charset=$charset");
        
        
        switch ($this->step) {
            case 'default':
                echo $this->twig->render('installation.twig', [
                    'baseUrl' => computeBaseUrl(true),
                    'charset' => $charset,
                    'config' => $this->config,
                    'env' => $this->env,
                    'locale' => $GLOBALS['prefered_language'],
                    'availableLanguages' => $GLOBALS['available_languages'],
                    'languagesList' => $GLOBALS['languages_list'],
                    'template' => 'installation-default.twig',
                    'yeswikiVersion' => ucfirst(YESWIKI_VERSION).' '.YESWIKI_RELEASE,
                ]);
                break;
            
            case 'install':
                echo '<h2>'._t('VERIFICATION_OF_DATAS_AND_DATABASE_INSTALLATION').'</h2>';

                // fetch configuration but environment vars have priority
                $configPosted = array_merge($_POST['config'], $this->env);
                // merge existing (or default) configuration with new one
                $this->config = array_merge($this->config, $configPosted);
                // set version to current version, yay!
                $this->config['wikini_version'] = WIKINI_VERSION;
                $this->config['wakka_version'] = WAKKA_VERSION;
                $this->config['yeswiki_version'] = YESWIKI_VERSION;
                $this->config['yeswiki_release'] = YESWIKI_RELEASE;
                
                // list of tableNames
                $tablesNames = ['pages','links','referrers','nature','triples','users','acls'];

                if (!$version = trim($this->config['wikini_version'])) {
                    $version = '0';
                }

                if ($version) {
                    $this->test(_t('VERIFY_MYSQL_PASSWORD').' ...', isset($configPosted['mysql_password']) && $this->config['mysql_password'] === $configPosted['mysql_password'], _t('INCORRECT_MYSQL_PASSWORD').' !');
                }
                $this->test(_t('TEST_MYSQL_CONNECTION').' ...', $dblink = @mysqli_connect($this->config['mysql_host'], $this->config['mysql_user'], $this->config['mysql_password']));

                $testdb = $this->test(
                    _t('SEARCH_FOR_DATABASE').' ...',
                    @mysqli_select_db($dblink, $this->config['mysql_database']),
                    _t('NO_DATABASE_FOUND_TRY_TO_CREATE').'.',
                    0
                );
                if ($testdb == 1) {
                    $this->test(
                        _t('TRYING_TO_CREATE_DATABASE').' ...',
                        @mysqli_query($dblink, 'CREATE DATABASE '.$this->config['mysql_database']),
                        _t('DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY').' !'
                    );
                    $this->test(
                        _t('SEARCH_FOR_DATABASE').' ...',
                        @mysqli_select_db($dblink, $this->config['mysql_database']),
                        _t('DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT').' !',
                        1
                    );
                }
                $c = $this->config;
                $this->test(
                    _t('CHECK_EXISTING_TABLE_PREFIX').' ...',
                    empty(array_filter($tablesNames, function ($tableName) use ($dblink, $c) {
                        return mysqli_num_rows(@mysqli_query($dblink, "SHOW TABLES LIKE \"{$c['table_prefix']}$tableName\"")) !== 0 ;
                    })),
                    _t('TABLE_PREFIX_ALREADY_USED').' !',
                    1
                );

                if (!$version || empty($_POST['admin_login'])) {
                    $admin_name = $_POST['admin_name'];
                    $admin_email = $_POST['admin_email'];
                    $admin_password = $_POST['admin_password'];
                    $admin_password_conf = $_POST['admin_password_conf'];
                    $this->test(
                        _t('CHECKING_THE_ADMIN_PASSWORD').' ...',
                        strlen($admin_password) >= 5,
                        _t('PASSWORD_TOO_SHORT'),
                        1
                    );
                    $this->test(
                        _t('CHECKING_THE_ADMIN_PASSWORD_CONFIRMATION').' ...',
                        $admin_password === $admin_password_conf,
                        _t('ADMIN_PASSWORD_ARE_DIFFERENT'),
                        1
                    );
                } else {
                    $admin_name = $_POST['admin_login'];
                    unset($admin_password);
                }

                $this->config['root_page'] = trim($this->config['root_page']);
                $this->test(
                    _t('CHECKING_ROOT_PAGE_NAME').' ...',
                    preg_match('/^'.WN_CAMEL_CASE_EVOLVED.'$/', $this->config['root_page']),
                    _t('INCORRECT_ROOT_PAGE_NAME'),
                    1
                );

                // all in utf8mb4
                mysqli_set_charset($dblink, 'utf8mb4');
                mysqli_query($dblink, 'SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $replacements = [
                    'prefix' => $this->config['table_prefix'],
                    'siteTitle' => $this->config['wakka_name'],
                    'WikiName' => $admin_name,
                    'password' => $admin_password,
                    'email' => $admin_email,
                    'rootPage' => $this->config['root_page'],
                    'url' => $this->config['base_url']
                ];

                // tables, admin user and admin group creation
                echo '<br /><b>'._t('DATABASE_INSTALLATION')."</b><br>\n";
                mysqli_begin_transaction($dblink);
                mysqli_autocommit($dblink, false);
                $result = @$this->querySqlFile($dblink, 'setup/sql/create-tables.sql', $replacements);
                if (!$result) {
                    mysqli_rollback($dblink);
                }
                $this->test(
                    _t('CREATION_OF_TABLES').' ...',
                    $result,
                    _t('NOT_POSSIBLE_TO_CREATE_SQL_TABLES').' ?',
                    1
                );

                // Default pages content
                $result = @$this->querySqlFile($dblink, 'setup/sql/default-content.sql', $replacements);
                if (!$result) {
                    mysqli_rollback($dblink);
                    foreach ($tablesNames as $tableName) {
                        try {
                            if (mysqli_num_rows(mysqli_query($dblink, "SHOW TABLES LIKE \"{$this->config['table_prefix']}$tableName\";")) !== 0 // existing table
                                && mysqli_num_rows(mysqli_query($dblink, "SELECT * FROM `{$this->config['table_prefix']}$tableName`;")) === 0) /* empty table */{
                                mysqli_query($dblink, "DROP TABLE IF EXISTS `{$this->config['table_prefix']}$tableName`;");
                            }
                        } catch (\Throwable $th) {
                        }
                    }
                } else {
                    mysqli_commit($dblink);
                }
                $this->test(
                    _t('INSERTION_OF_PAGES').' ...',
                    $result,
                    _t('ALREADY_CREATED').' ?',
                    1
                );
                mysqli_autocommit($dblink, true);

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
                    // save robots.txt file
                    file_put_contents('robots.txt', $robotFile);

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
                    // save robots.txt file
                    file_put_contents('robots.txt', $robotFile);
                }


                if (isset($this->config['allow_robots'])) {
                    // do not save this config because not use by YesWiki
                    unset($this->config['allow_robots']);
                }

                // update some values
                foreach (['allow_raw_html','rewrite_mode'] as $name) {
                    if (isset($this->config[$name])) {
                        $this->config[$name] = (in_array($this->config[$name], ['1',true,'true'])) ? true : false;
                    }
                }

                ?>
                <br />
                <div class="alert alert-info"><?php echo _t('NEXT_STEP_WRITE_CONFIGURATION_FILE'); ?>
                <tt><?php echo \YesWiki\Init::configFile ?></tt>.</br>
                <?php echo _t('VERIFY_YOU_HAVE_RIGHTS_TO_WRITE_FILE'); ?>.  </div>
                <?php
                // convert config array into PHP code
                $configCode = "<?php\n// wakka.config.php "._t('CREATED').' '.date('c')."\n// "._t('DONT_CHANGE_YESWIKI_VERSION_MANUALLY')." !\n\n\$wakkaConfig = ";
                if (function_exists('var_export')) {
                    // var_export gives a better result but was added in php 4.2.0 (wikini asks only php 4.1.0)
                    $configCode .= var_export($this->config, true).";\n?>";
                } else {
                    $configCode .= "array(\n";
                    foreach ($this->config as $k => $v) {
                        // avoid problems with quotes and slashes
                        $entries[] = "\t'".$k."' => '".str_replace(array('\\', "'"), array('\\\\', '\\\''), $v)."'";
                    }
                    $configCode .= implode(",\n", $entries).");\n\n";
                }

                // try to write configuration file
                echo '<b>'._t('WRITING_CONFIGURATION_FILE_WIP')." ...</b><br>\n";
                $this->test(_t('WRITING_CONFIGURATION_FILE').' <tt>'.\YesWiki\Init::configFile.'</tt> ...', $fp = @fopen(\YesWiki\Init::configFile, 'w'), '', 0);

                if ($fp) {
                    fwrite($fp, $configCode);
                    // write
                    fclose($fp);

                    echo    "<br />\n<div class=\"alert alert-success\"><strong>"._t('FINISHED_CONGRATULATIONS').' !</strong><br />'._t('IT_IS_RECOMMANDED_TO_REMOVE_WRITE_ACCESS_TO_CONFIG_FILE').' <tt>wakka.config.php</tt> ('._t('THIS_COULD_BE_UNSECURE').').</div>';
                    echo "<div class=\"form-actions\">\n<a class=\"btn btn-lg btn-primary\" href=\"",$this->config['base_url'].$this->config['root_page'],'">'._t('GO_TO_YOUR_NEW_YESWIKI_WEBSITE')."</a>\n</div>\n";
                    //header('Location: '.$this->config['base_url'].$this->config['root_page']);
                } else {
                    // complain
                    echo    "<br />\n<div class=\"alert alert-danger\"><strong>"._t('WARNING').'</strong> :</span> '._t('CONFIGURATION_FILE').' <tt>', \YesWiki\Init::configFile,'</tt> '._t('CONFIGURATION_FILE_NOT_CREATED').'.<br />'.
                            _t('TRY_CHANGE_ACCESS_RIGHTS_OR_FTP_TRANSFERT').
                            '<tt>wakka.config.php</tt> '._t('DIRECTLY_IN_THE_YESWIKI_FOLDER').".</div>\n";
                    echo "\n<pre><xmp>",$configCode,"</xmp></pre>\n"; ?>
                <form action="<?php echo $this->baseUrl; ?>?installAction=writeconfig" method="POST">
                <input type="hidden" name="config" value="<?php echo  htmlspecialchars(json_encode($configPosted), ENT_COMPAT, YW_CHARSET) ?>">
                <div class="form-actions">
                    <input type="submit" class="btn btn-lg btn-primary" value="<?php echo _t('TRY_AGAIN'); ?>">
                </div>
                </form>
                    <?php
                }
            break;
        }
    }

    private function getInstallationStep($step = 'default'): string
    {
        if (! isset($_REQUEST['installAction']) or ! $installAction = trim($_REQUEST['installAction'])) {
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
     * Communique le resultat d'un test :
     * -- affiche OK si elle l'est
     * -- affiche un message d'erreur dans le cas contraire
     *
     * @param string $text Label du test
     * @param boolean $condition Résultat de la condition testée
     * @param string $errortext Message en cas d'erreur
     * @param string $stopOnError Si positionnée é 1 (par défaut), termine le
     *               script si la condition n'est pas vérifiée
     * @return int 0 si la condition est vraie et 1 si elle est fausse
     */
    private function test($text, $condition, $errorText = "", $stopOnError = 1)
    {
        echo "$text ";
        if ($condition) {
            echo "<span class=\"text-success\">"._t('OK')."</span><br />\n";
            return 0;
        } else {
            echo "<span class=\"text-danger\">"._t('FAIL')."</span>";
            if ($errorText) {
                echo ": ",$errorText;
            }
            echo "<br />\n";
            if ($stopOnError) {
                echo "<br />\n<div class=\"alert alert-danger alert-error\"><strong>"._t('END_OF_INSTALLATION_BECAUSE_OF_ERRORS').".</strong></div>\n";
                echo "<script>
                    document.write('<div class=\"form-actions\"><a class=\"btn btn-large btn-primary revenir\" href=\"javascript:history.go(-1);\">"._t('GO_BACK')."</a></div>');
                    </script>\n";
                echo "</body>\n</html>\n";
                exit;
            }
            return 1;
        }
    }

    /**
     * 
     */
    private function querySqlFile($dblink, $sqlFile, $replacements = [])
    {
        if ($sql = file_get_contents($sqlFile)) {
            foreach ($replacements as $keyword => $replace) {
                $sql = str_replace(
                    '{{'.$keyword.'}}',
                    mysqli_real_escape_string($dblink, $replace),
                    $sql
                );
            }
            # echo '<hr><pre>';var_dump($sql);echo '</pre><hr>'; # DEBUG SQL
            if (!mysqli_multi_query($dblink, $sql)) {
                return false;
            }
            while (mysqli_more_results($dblink)) {
                if (!mysqli_next_result($dblink)) {
                    return false;
                }
            }
            return true;
        } else {
            die(_t('SQL_FILE_NOT_FOUND').' "'.$sqlFile.'".');
        }
    }
}
