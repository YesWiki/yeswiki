<?php

use YesWiki\Bazar\Field\CalcField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\ArchiveService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\LinkTracker;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\PasswordHasherFactory;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Wiki;

class UpdateHandler extends YesWikiHandler
{
    public function run()
    {
        if ($this->getService(SecurityController::class)->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        };

        $output = '';

        if ($this->wiki->UserIsAdmin()) {
            $output .= '<strong>YesWiki core</strong><br />';
            $dbService = $this->getService(DbService::class);
            $dblink = $dbService->getLink();

            // drop old nature table fields
            $result = $this->wiki->Query("SHOW COLUMNS FROM {$dbService->prefixTable("nature")} WHERE FIELD IN ('bn_ce_id_menu' ,'bn_commentaire' , 'bn_appropriation' , 'bn_image_titre' , 'bn_image_logo' , 'bn_couleur_calendrier' , 'bn_picto_calendrier' , 'bn_type_fiche' , 'bn_label_class')");
            if (@mysqli_num_rows($result) > 0) {
                $output .= "ℹ️ Removing old fields from {$dbService->prefixTable("nature")}table.<br />";

                // don't show output because it can be an error if column doesn't exists
                @$this->wiki->Query("ALTER TABLE {$dbService->prefixTable("nature")} DROP `bn_ce_id_menu`;");
                @$this->wiki->Query("ALTER TABLE {$dbService->prefixTable("nature")} DROP `bn_commentaire`;");
                @$this->wiki->Query("ALTER TABLE {$dbService->prefixTable("nature")} DROP `bn_appropriation`;");
                @$this->wiki->Query("ALTER TABLE {$dbService->prefixTable("nature")} DROP `bn_image_titre`;");
                @$this->wiki->Query("ALTER TABLE {$dbService->prefixTable("nature")} DROP `bn_image_logo`;");
                @$this->wiki->Query("ALTER TABLE {$dbService->prefixTable("nature")} DROP `bn_couleur_calendrier`;");
                @$this->wiki->Query("ALTER TABLE {$dbService->prefixTable("nature")} DROP `bn_picto_calendrier`;");
                @$this->wiki->Query("ALTER TABLE {$dbService->prefixTable("nature")} DROP `bn_type_fiche`;");
                @$this->wiki->Query("ALTER TABLE {$dbService->prefixTable("nature")} DROP `bn_label_class`;");
                @$this->wiki->Query("ALTER TABLE {$dbService->prefixTable("nature")} MODIFY COLUMN bn_ce_i18n VARCHAR(5) NOT NULL DEFAULT ''");
                $output .= '✅ Done !<br />';
            } else {
                $output .= "✅ The table {$dbService->prefixTable("nature")}is already cleaned up from old tables !<br />";
            }

            // remove createur field
            $entryManager = $this->getService(EntryManager::class);
            if (method_exists(EntryManager::class, 'removeAttributes')) {
                if ($entryManager->removeAttributes([], ['createur'], true)) {
                    $output .= "ℹ️ Removing createur field from bazar entries in {$dbService->prefixTable("pages")}table.<br />";
                    $output .= '✅ Done !<br />';
                } else {
                    $output .= "✅ The table {$dbService->prefixTable("pages")}is already free of createur fields in bazar entries !<br />";
                }
            } else {
                $output .= "! Not possible to remove createur field from bazar entries in {$dbService->prefixTable("pages")}table.<br />";
            }

            // add semantic bazar fields
            $result = $this->wiki->Query("SHOW COLUMNS FROM {$dbService->prefixTable("nature")} LIKE 'bn_sem_context'");
            if (@mysqli_num_rows($result) === 0) {
                $output .= "ℹ️ Adding fields bn_sem_context, bn_sem_type and bn_sem_use_template to {$dbService->prefixTable("nature")}table.<br />";

                $this->wiki->Query("ALTER TABLE {$dbService->prefixTable("nature")} ADD COLUMN bn_sem_context text COLLATE utf8mb4_unicode_ci AFTER bn_condition");
                $this->wiki->Query("ALTER TABLE {$dbService->prefixTable("nature")} ADD COLUMN bn_sem_type varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER bn_sem_context");
                $this->wiki->Query("ALTER TABLE {$dbService->prefixTable("nature")} ADD COLUMN bn_sem_use_template tinyint(1) NOT NULL DEFAULT 1 AFTER bn_sem_type");

                $output .= '✅ Done !<br />';
            } else {
                $output .= "✅ The table {$dbService->prefixTable("nature")}is already up-to-date with semantic fields!<br />";
            }

            // add bn_only_one_entry bazar fields
            $formManager = $this->getService(FormManager::class);
            if (method_exists(FormManager::class, 'isAvailableOnlyOneEntryOption')) {
                if (!$formManager->isAvailableOnlyOneEntryOption()) {
                    $output .= "ℹ️ Adding 'bn_only_one_entry' to {$dbService->prefixTable("nature")}table.<br />";

                    $this->wiki->Query("ALTER TABLE {$dbService->prefixTable("nature")} ADD COLUMN `bn_only_one_entry` enum('Y','N') NOT NULL DEFAULT 'N' COLLATE utf8mb4_unicode_ci;");

                    $output .= '✅ Done !<br />';
                } else {
                    $output .= "✅ The table {$dbService->prefixTable("nature")}is already up-to-date with 'bn_only_one_entry' field!<br />";
                }

                // add isAvailableOnlyOneEntryMessage bazar fields
                if (!$formManager->isAvailableOnlyOneEntryMessage()) {
                    $output .= "ℹ️ Adding 'bn_only_one_entry_message' to {$dbService->prefixTable("nature")}table.<br />";

                    $this->wiki->Query("ALTER TABLE {$dbService->prefixTable("nature")} ADD COLUMN `bn_only_one_entry_message` text DEFAULT NULL COLLATE utf8mb4_unicode_ci;");

                    $output .= '✅ Done !<br />';
                } else {
                    $output .= "✅ The table {$dbService->prefixTable("nature")}is already up-to-date with 'bn_only_one_entry_message' field!<br />";
                }
            } else {
                $output .= "<span class=\"label label-warning\">! Not possible to update {$dbService->prefixTable("nature")}table because FormManager is not up to date.</span><br />";
                $output .= "<span class=\"label label-warning\">Disconnect then connect a new time and force a new install of yeswiki to resolve this.</span><br />";
            }

            // update comment acls
            if (!$this->params->has('default_comment_acl_updated') || !$this->params->get('default_comment_acl_updated')) {
                $output .= $this->updateDefaultCommentsAcls();
            } else {
                $output .= "ℹ️ Comment acls already reset!<br />";
            }
            $output .= $this->fixDefaultCommentsAcls();

            // check if SQL tables are well defined
            $output .= $this->checkSQLTablesThenFixThem($dbService);

            // update user table to increase size of password
            if ($this->wiki->services->has(PasswordHasherFactory::class)) {
                $passwordHasherFactory = $this->getService(PasswordHasherFactory::class);
                if (!$passwordHasherFactory->newModeIsActivated()) {
                    $output .= "ℹ️ Increasing 'password' column size for {$dbService->prefixTable("users")}table.<br />";
                    $passwordHasherFactory->activateNewMode();
                    $output .=  '✅ Done !<br />';
                } else {
                    $output .= "✅ The table {$dbService->prefixTable("users")}is already up-to-date with right 'password' column size!<br />";
                }
            }

            // replace CalcField value by string
            $output .= $this->calcFieldToString();

            // adding GererSauvegardes page
            $output .= 'ℹ️ Adding GererSauvegardes pages.... ';
            $page = $this->getService(PageManager::class)->getOne('GererSauvegardes');
            if (empty($page)) {
                list($updatePagesState, $message) = $this->updateAdminPages(['GererSauvegardes']);
                if ($updatePagesState) {
                    $output .= '✅ Done !<br />';
                } else {
                    $output .= '<span class="label label-warning">! '._t('UPDATE_ADMIN_PAGES_ERROR').'</span>'.'<br />'.$message;
                }
            } else {
                $output .= '✅ Done !<br />';
            }

            // updating folder 'private'
            $output .= 'ℹ️ Updating folder \'private\'.... ';
            if ((file_exists('private') && !is_dir('private')) || (!file_exists('private') && !mkdir('private'))) {
                $output .= "❌ Not possible to update the folder 'private' !<br/>";
            } elseif ((file_exists('private/.htaccess') &&
                    !is_file('private/.htaccess')) || // do not udpate the content if existing but not a file
                    (!file_exists('private/.htaccess') && !file_put_contents('private/.htaccess', "DENY FROM ALL\n"))
            ) {
                $output .= "❌ Not possible to create the file 'private/.htaccess' !<br/>";
            } elseif ((file_exists('private/backups') && !is_dir('private/backups')) || (!file_exists('private/backups') && !mkdir('private/backups'))) {
                $output .= "❌ Not possible to update the folder 'private/backups' !<br/>";
            } elseif ((file_exists('private/backups/.htaccess') &&
                    !is_file('private/backups/.htaccess')) || // do not udpate the content if existing but not a file
                    (!file_exists('private/backups/.htaccess') && !file_put_contents('private/backups/.htaccess', "DENY FROM ALL\n"))
            ) {
                $output .= "❌ Not possible to create the file 'private/backups/.htaccess' !<br/>";
            } elseif ((file_exists('private/backups/README.md') &&
                    !is_file('private/backups/README.md')) || // do not udpate the content if existing but not a file
                    (!file_exists('private/backups/README.md') &&
                    !file_put_contents('private/backups/README.md', ArchiveService::PRIVATE_FOLDER_README_DEFAULT_CONTENT))
            ) {
                $output .= "❌ Not possible to create the file 'private/backups/README.md' !<br/>";
            } else {
                $output .= '✅ Done !<br />';
            }

            // propose to update content of admin's pages
            $output .= $this->frontUpdateAdminPages();
        } else {
            $output .= '<div class="alert alert-danger">'._t('ACLS_RESERVED_FOR_ADMINS').'</div>';
        }
        $output .= '<hr />';

        // BE CAREFULL this comment is used for extensions to add content above, don't delete it!
        $output .= '<!-- end handler /update -->';

        if (!method_exists(Wiki::class, 'isCli') || !$this->wiki->isCli()) {
            $output = $this->wiki->header().$output;
            // add button to return to previous page
            $output .= '<div>
                <a class="btn btn-sm btn-secondary-1" href="'.$this->wiki->Href().'">
                    <i class="fas fa-arrow-left"></i>' . _t('GO_BACK') . '
                </a>
            </div>';
            $output .= $this->wiki->footer();
        }
        return $output;
    }

    /**
     * method to display text and button to update admin pages
     * @return string
     */
    private function frontUpdateAdminPages(): string
    {
        $output = 'ℹ️ Updating admins pages. ';
        $adminPagesToUpdate = $this->wiki->config['admin_pages_to_update'] ?? [];
        $adminPagesList = implode(', ', $adminPagesToUpdate);
        if ($_GET['updateAdminPages'] ?? false) {
            list($updatePagesState, $message) = $this->updateAdminPages($adminPagesToUpdate);
            if ($updatePagesState) {
                $output .= '✅ Done !<br />';
            } else {
                $output .= '<span class="label label-warning">! '._t('UPDATE_ADMIN_PAGES_ERROR').'</span>'.'<br />'.$message;
            }
        } else {
            $output .= '<a href="'.$this->wiki->Href('update', '', ['updateAdminPages'=>true]).'" '.
                'class="btn-primary btn-xs btn"'.
                'onclick="return confirm(\''._t('UPDATE_ADMIN_PAGES_CONFIRM').$adminPagesList.' !\');"'. // TODO modal + bootstrap for confirm box
                '>'.
                '<i class="fas fa-sync-alt"></i> '
                ._t('UPDATE_ADMIN_PAGES')
                .'</a><br />';
            $output .= '<span class="update-hint">'. _t('UPDATE_ADMIN_PAGES_HINT').'</span><br />';
        }

        return $output;
    }

    /**
     * method to update admin pages
     * @param array $adminPagesToUpdate ['BazaR',GererSite', ...]
     * @return array [bool true/false, string|null errorMessage]
     */
    private function updateAdminPages(array $adminPagesToUpdate): array
    {
        $defaultSQL = file_get_contents('setup/sql/default-content.sql');
        $defaultSQLSplittedByBlock  = explode("INSERT INTO", $defaultSQL);
        $blocks = [];
        for ($i=1; $i < count($defaultSQLSplittedByBlock); $i++) {
            $block = $defaultSQLSplittedByBlock[$i];
            if (substr($block, 0, 1) !== '#' &&
                    substr($defaultSQLSplittedByBlock[$i-1], 0, strlen('# YesWiki pages')) === '# YesWiki pages') { // only working for pages
                $typeBlock = explode('`', substr($block, strlen(' `{{prefix}}')), 2);
                if ($typeBlock[0] == 'pages') {
                    $blocks[] = $typeBlock[1];
                }
            }
        }

        $defaultSQLSplitted = [];
        foreach ($blocks as $block) {
            $splittedBlock = explode("VALUES\n('", $block, 2);
            if (count($splittedBlock) < 2) {
                $splittedBlock = explode("VALUES\r\n('", $block, 2);
                $separator = "\r\n";
            } else {
                $separator = "\n";
            }
            $splittedBlock = explode("),".$separator."('", $splittedBlock[1]);
            foreach ($splittedBlock as $extract) {
                $tag = explode('\'', $extract)[0];
                $defaultSQLSplitted[$tag] = $extract;
            }
        }
        $output = '';
        $linkTracker = $this->getService(LinkTracker::class);
        foreach ($adminPagesToUpdate as $page) {
            if (isset($defaultSQLSplitted[$page])) {
                if (preg_match('/'.$page.'\',\s*(?:now\(\))?\s*,\s*\'([\S\s]*)\',\s*\'\'\s*,\s*\'{{WikiName}}\',\s*\'{{WikiName}}\', \'(?:Y|N)\', \'page\', \'\'/U', $defaultSQLSplitted[$page], $matches)) {
                    $pageContent = str_replace('\\"', '"', $matches[1]);
                    $pageContent = str_replace('\\\'', '\'', $pageContent);
                    $pageContent = str_replace('{{rootPage}}', $this->params->get('root_page'), $pageContent);
                    $pageContent = str_replace('{{url}}', $this->params->get('base_url'), $pageContent);
                    if ($this->getService(PageManager::class)->save($page, $pageContent) !== 0) {
                        $output .= (!empty($output) ? ', ' : '')._t('NO_RIGHT_TO_WRITE_IN_THIS_PAGE').$page;
                    } else {
                        // save links
                        $linkTracker->registerLinks($this->getService(PageManager::class)->getOne($page));
                    }
                }
            } else {
                $output .= (!empty($output) ? ', ' : '').str_replace('{{page}}', $page, _t('UPDATE_PAGE_NOT_FOUND_IN_DEFAULT_SQL'));
            }
        }
        return [empty($output),$output];
    }

    private function updateDefaultCommentsAcls(): string
    {
        $output = "ℹ️ Resetting comment acls<br />";

        // default acls in wakka.config.php
        $config = $this->getService(ConfigurationService::class)->getConfiguration('wakka.config.php');
        $config->load();

        $baseKey = 'default_comment_acl';
        $config->$baseKey = 'comments-closed';
        $baseKey = 'default_comment_acl_updated';
        $config->$baseKey = true;
        $config->write();
        unset($config);

        // remove all comment acl
        $pageManager = $this->getService(PageManager::class);
        $aclService = $this->getService(AclService::class);

        $pages = $pageManager->getAll();
        foreach ($pages as $page) {
            $aclService->delete($page['tag'], ['comment']);
        }
        $output .= '✅ Done !<br />';

        return $output;
    }
    private function fixDefaultCommentsAcls(): string
    {
        $output = "ℹ️ Fix comment acls... ";

        // default acls in wakka.config.php
        include_once 'tools/templates/libs/Configuration.php';
        $config = new Configuration('wakka.config.php');
        $config->load();

        $baseKey = 'default_comment_acl';
        try {
            $valueFromConfig = $config->$baseKey;
        } catch (Exception $th) {
            $config->$baseKey = 'comments-closed';
            $config->write();
        }
        if ($config->$baseKey == "comment-closed") {
            $config->$baseKey = 'comments-closed';
            $config->write();
        }
        unset($config);

        // remove all comment acl
        $pageManager = $this->getService(PageManager::class);
        $aclService = $this->getService(AclService::class);

        $pages = $pageManager->getAll();
        foreach ($pages as $page) {
            $pageCommentAcl = $aclService->load($page['tag'], 'comment', false)['list'] ?? '';
            if (!empty($pageCommentAcl) && preg_match("/comment-closed\s*/", strval($pageCommentAcl))) {
                $aclService->save($page['tag'], 'comment', "comments-closed");
            }
        }
        $output .= '✅ Done !<br />';

        return $output;
    }

    private function calcFieldToString(): string
    {
        $output = "ℹ️ CalcField value to string... ";

        // get Services
        $dbService = $this->getService(DbService::class);
        $entryManager = $this->getService(EntryManager::class);
        $formManager = $this->getService(FormManager::class);

        // find CalcField in forms
        $forms = $formManager->getAll();
        if (!empty($forms)) {
            $fields = [];
            foreach ($forms as $form) {
                $formId = $form['bn_id_nature'];
                if (!empty($form['prepared'])) {
                    foreach ($form['prepared'] as $field) {
                        if ($field instanceof CalcField) {
                            // init array for this form, if needed
                            if (empty($fields[$formId])) {
                                $fields[$formId] = [];
                            }
                            // append propertyName if not already present
                            if (!empty($field->getPropertyName()) && !in_array($field->getPropertyName(), $fields[$formId])) {
                                $fields[$formId][] = $field->getPropertyName();
                            }
                        }
                    }
                }
            }

            if (!empty($fields)) {
                foreach ($fields as $formId => $fieldNames) {
                    if (!empty($fieldNames)) {
                        // prepare SQL to select concerned entries (EntryManager->search does not manage int)
                        $fieldsNamesList = implode('|', $fieldNames);
                        $sql =
                            <<<SQL
                            SELECT DISTINCT * FROM {$dbService->prefixTable('pages')}
                            WHERE `comment_on` = ''
                            AND `body` LIKE '%"id_typeannonce":"{$dbService->escape(strval($formId))}"%'
                            AND `tag` IN (
                                SELECT DISTINCT `resource` FROM {$dbService->prefixTable('triples')}
                                WHERE `value` = "fiche_bazar" AND `property` = "http://outils-reseaux.org/_vocabulary/type"
                                ORDER BY `resource` ASC
                            )
                            AND `body` REGEXP '"($fieldsNamesList)":-?[0-9]'
                            SQL;
                        $results = $dbService->loadAll($sql);
                        if (!empty($results)) {
                            foreach ($results as $page) {
                                if (preg_match_all("/\"($fieldsNamesList)\":(-?[0-9\.]*),/", $page['body'], $matches)) {
                                    foreach ($matches[0] as $index => $match) {
                                        $fieldName = $matches[1][$index];
                                        $oldValue = $matches[2][$index];
                                        $newValue = strval($oldValue);
                                        $replaceSQL =
                                        <<<SQL
                                        UPDATE {$dbService->prefixTable('pages')} 
                                        SET `body` = replace(`body`,'"$fieldName":$oldValue,','"$fieldName":"$newValue",')
                                        WHERE `id` = '{$dbService->escape($page['id'])}'
                                        SQL;
                                        // replace
                                        $dbService->query($replaceSQL);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $output .= '✅ Done !<br />';

        return $output;
    }

    /**
     * @param DbService $dbService
     * @return string $output
     */
    private function checkSQLTablesThenFixThem($dbService): string
    {
        $output = "ℹ️ Checking SQL table structure... ";
        try {
            foreach ([
                ['pages','id','int(10) unsigned NOT NULL AUTO_INCREMENT'],
                ['links','id','int(10) unsigned NOT NULL AUTO_INCREMENT'],
                ['nature','bn_id_nature','int(10) UNSIGNED NOT NULL AUTO_INCREMENT'],
                ['referrers','id','int(10) unsigned NOT NULL AUTO_INCREMENT'],
                ['triples','id','int(10) unsigned NOT NULL AUTO_INCREMENT'],
            ] as $data) {
                $output .= $this->checkThenUpdateColumnAutoincrement($dbService, $data[0], $data[1], $data[2]);
            }
            foreach ([
                ['pages','id',['id']],
                ['links','id',['id']],
                ['nature','bn_id_nature',['bn_id_nature']],
                ['referrers','id',['id']],
                ['triples','id',['id']],
                ['users','name',['name']],
                ['acls','page_tag',['page_tag','privilege']],
                ['acls','privilege',['page_tag','privilege']],
            ] as $data) {
                $output .= $this->checkThenUpdateColumnPrimary($dbService, $data[0], $data[1], $data[2]);
            }
            $output .= "✅ All is right !<br/>";
        } catch (\Throwable $th) {
            if ($th->getCode() ===1) {
                $output .= "{$th->getMessage()} <br/>";
            } else {
                $output .= "❌ Not checked because of error during tests : {$th->getMessage()} (file : '{$th->getFile()}' - line : ('{$th->getLine()}')! <br/>";
            }
        }

        return $output;
    }

    /**
     * check if a column has auto_increment then try to update it
     * @param $dbService
     * @param string $tableName
     * @param string $columnName
     * @param string $SQL_columnDef
     * @return string $output
     * @throws \Exception
     */
    private function checkThenUpdateColumnAutoincrement($dbService, string $tableName, string $columnName, string $SQL_columnDef): string
    {
        $output = "";
        try {
            $data = $this->getColumnInfo($dbService, $tableName, $columnName);
        } catch (Exception $ex) {
            if ($ex->getCode() != 1) {
                throw $ex;
            }
            $data = [];
        }
        if (empty($data['Extra']) || (is_string($data['Extra']) && strstr($data['Extra'], 'auto_increment') === false)) {
            $output .= "<br/>  Updating `$columnName` in `$tableName`... ";
            if (empty($data)) {
                $dataIndex = $this->getColumnInfo($dbService, $tableName, 'index');
                if (!empty(array_filter($dataIndex, function ($keyData) {
                    return !empty($keyData['Key_name']) && $keyData['Key_name'] == 'PRIMARY';
                }))) {
                    $dbService->query("ALTER TABLE {$dbService->prefixTable($tableName)} DROP PRIMARY KEY;");
                }
                $dbService->query("ALTER TABLE {$dbService->prefixTable($tableName)} ADD COLUMN `$columnName` $SQL_columnDef FIRST, ADD PRIMARY KEY(`$columnName`);");
            }
            $dbService->query("ALTER TABLE {$dbService->prefixTable($tableName)} MODIFY COLUMN `$columnName` $SQL_columnDef;");
            $data = $this->getColumnInfo($dbService, $tableName, $columnName);
            if (empty($data['Extra']) || (is_string($data['Extra']) && strstr($data['Extra'], 'auto_increment') === false)) {
                throw new \Exception("❌ tables `$tableName`, column `$columnName` not updated !", 1);
            }
        }
        return $output;
    }

    /**
     * check if a column is primary then try to update it
     * @param $dbService
     * @param string $tableName
     * @param string $columnName
     * @param array $newKeys
     * @return string $output
     * @throws \Exception
     */
    private function checkThenUpdateColumnPrimary($dbService, string $tableName, string $columnName, array $newKeys): string
    {
        $output = "";
        $data = $this->getColumnInfo($dbService, $tableName, $columnName);
        if (empty($data['Key']) || $data['Key'] !== "PRI") {
            $output .= "<br/>  Updating key for `$columnName` in `$tableName`... ";
            $newKeysFormatted = implode(
                ',',
                array_map(
                    function ($key) use ($dbService) {
                        return "`{$dbService->escape($key)}`";
                    },
                    array_filter($newKeys)
                )
            );
            if (!empty($newKeysFormatted)) {
                $data = $this->getColumnInfo($dbService, $tableName, 'index');
                if (!empty(array_filter($data, function ($keyData) {
                    return !empty($keyData['Key_name']) && $keyData['Key_name'] == 'PRIMARY';
                }))) {
                    $dbService->query("ALTER TABLE {$dbService->prefixTable($tableName)} DROP PRIMARY KEY;");
                }
                $dbService->query("ALTER TABLE {$dbService->prefixTable($tableName)} ADD PRIMARY KEY($newKeysFormatted);");
            }
            $data = $this->getColumnInfo($dbService, $tableName, $columnName);
            if (empty($data['Key']) || $data['Key'] !== "PRI") {
                throw new \Exception("❌ tables `$tableName`, column `$columnName` key not updated !", 1);
            }
        }
        return $output;
    }

    /**
     * get columnInfo from a table
     * @param DbService $dbService
     * @param string $tableName
     * @param string $columnName
     * @return array $data
     * @throws \Exception
     */
    private function getColumnInfo($dbService, string $tableName, string $columnName): array
    {
        if ($columnName == 'index') {
            $result = $dbService->query("SHOW INDEX FROM {$dbService->prefixTable($tableName)};");
            if (@mysqli_num_rows($result) === 0) {
                return [];
            }
        } else {
            $result = $dbService->query("SHOW COLUMNS FROM {$dbService->prefixTable($tableName)} LIKE '$columnName';");
            if (@mysqli_num_rows($result) === 0) {
                throw new \Exception("❌ tables `$tableName` not verified because error while getting `$columnName` column !", 1);
            }
        }
        $data = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        return $data;
    }
}
