<?php

namespace YesWiki\AutoUpdate\Service;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\PasswordHasherFactory;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

// This is a simple mecanism to perform migrations
// Create a new private method at the bottom of this class, it will be run after the wiki gets updated
// TODO: name method uniquely, and see if they are run in order
// TODO: handle extensions migrations
class MigrationService
{
    public const TRIPLES_MIGRATION_ID = 'migration';
    private $wiki;

    public function __construct(Wiki $wiki)
    {
        $this->wiki = $wiki;
    }

    function run()
    {
        if ($this->wiki->services->get(SecurityController::class)->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        }

        // All private methods are considered as migrations
        $reflection = new ReflectionClass($this);
        $migrationsMethods = $reflection->getMethods(ReflectionMethod::IS_PRIVATE);
        $tripleStore = $this->wiki->services->get(TripleStore::class);

        $messages = [];
        foreach ($migrationsMethods as $method) {
            $methodName = $method->getName();
            try {
                // Check if migration is already done
                if (!$tripleStore->exist($methodName, TripleStore::TYPE_URI, self::TRIPLES_MIGRATION_ID, '', '')) {
                    // peform migration by calling the method
                    $this->$methodName();
                    // Mark the migration as done by writing a new triple in the DB
                    $tripleStore->create($methodName, TripleStore::TYPE_URI, self::TRIPLES_MIGRATION_ID, '', '');

                    $messages[] = ['status' => _t('AU_OK'), 'text' => "Migration $methodName"];
                }
            } catch (Exception $e) {
                $messages[] = ['status' => _t('AU_ERROR'), 'text' => "Migration $methodName failed with error {$e->getMessage()}"];
            }
        }
        return $messages;
    }

    // All private methods below are considered as migrations
    // Add new migration on the bottom

    // private function oldMigration() {}
    // private function newMigration() {}

    private function addYeswikiReleaseConf()
    {
        $params = $this->wiki->services->getParameterBag();
        $releaseInConfig = $params->get('yeswiki_release');
        if ($releaseInConfig == _t('AU_UNKNOW') || !preg_match("/^\d{1,4}[.-].*/", $releaseInConfig)) {
            $config = $this->wiki->services->get(ConfigurationService::class)->getConfiguration('wakka.config.php');
            $config->load();
            $config['yeswiki_release'] = YESWIKI_RELEASE;
            $config->write();
        }
    }
    private function cercopitequePostInstall()
    {
        if (isset($_GET['previous_version']) && $_GET['previous_version'] == 'cercopitheque') {

            $config = $this->wiki->services->get(ConfigurationService::class)->getConfiguration('wakka.config.php');
            $config->load();

            // check favorite_theme
            // If default theme was used, install new yeswikicerco extension to keep same look and feel
            $favoriteThemefromFile = $config['favorite_theme'] ?? '';
            if (empty($favoriteThemefromFile) || $favoriteThemefromFile == 'yeswiki') {
                $this->wiki->services->get(AutoUpdateService::class)->upgrade('yeswikicerco');

                $config['favorite_theme'] = 'yeswikicerco';
                $config['favorite_style'] = $config['favorite_style'] ?? 'gray.css';
                $config['favorite_squelette'] = $config['favorite_squelette'] ?? 'responsive-1col.tpl.html';
                $config->write();
            }
        }
    }

    private function dropColumnsFromNature()
    {
        $dbService = $this->wiki->services->get(DbService::class);

        // drop old nature table fields
        $dbService->dropColumn("nature", "bn_ce_id_menu");
        $dbService->dropColumn("nature", "bn_commentaire");
        $dbService->dropColumn("nature", "bn_appropriation");
        $dbService->dropColumn("nature", "bn_image_titre");
        $dbService->dropColumn("nature", "bn_image_logo");
        $dbService->dropColumn("nature", "bn_couleur_calendrier");
        $dbService->dropColumn("nature", "bn_picto_calendrier");
        $dbService->dropColumn("nature", "bn_type_fiche");
        $dbService->dropColumn("nature", "bn_label_class");
        $dbService->query("ALTER TABLE {$dbService->prefixTable("nature")} MODIFY COLUMN bn_ce_i18n VARCHAR(5) NOT NULL DEFAULT ''");

        // add semantic bazar fields
        if (!$dbService->columnExists("nature", "bn_sem_context")) {
            $dbService->query("ALTER TABLE {$dbService->prefixTable("nature")} ADD COLUMN bn_sem_context text COLLATE utf8mb4_unicode_ci AFTER bn_condition");
            $dbService->query("ALTER TABLE {$dbService->prefixTable("nature")} ADD COLUMN bn_sem_type varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER bn_sem_context");
            $dbService->query("ALTER TABLE {$dbService->prefixTable("nature")} ADD COLUMN bn_sem_use_template tinyint(1) NOT NULL DEFAULT 1 AFTER bn_sem_type");
        }

        // TODO: What is this??? seems sooo weird
        $formManager = $this->wiki->services->get(FormManager::class);
        if (!$formManager->isAvailableOnlyOneEntryOption()) {
            $dbService->query("ALTER TABLE {$dbService->prefixTable("nature")} ADD COLUMN `bn_only_one_entry` enum('Y','N') NOT NULL DEFAULT 'N' COLLATE utf8mb4_unicode_ci;");
        }
        if (!$formManager->isAvailableOnlyOneEntryMessage()) {
            $dbService->query("ALTER TABLE {$dbService->prefixTable("nature")} ADD COLUMN `bn_only_one_entry_message` text DEFAULT NULL COLLATE utf8mb4_unicode_ci;");
        }
    }

    private function removeAttributesFromEntries()
    {
        // TODO: This one will load each Entry and update it. Could be very long for some wiki...
        // shall we drop it?
        $entryManager = $this->wiki->services->get(EntryManager::class);
        $entryManager->removeAttributes([], ['createur'], true);
    }

    private function updatePasswordSize()
    {
        // update user table to increase size of password
        $passwordHasherFactory = $this->wiki->services->get(PasswordHasherFactory::class);
        if (!$passwordHasherFactory->newModeIsActivated()) {
            $passwordHasherFactory->activateNewMode();
        }
    }

    private function introduceArchiveMecanism()
    {
        $page = $this->wiki->services->get(PageManager::class)->getOne('GererSauvegardes');
        if (empty($page)) {
            $this->wiki->services->get(UpdateAdminPagesService::class)->update(['GererSauvegardes']);
        }
    }

    // check if SQL tables are well defined
    private function checkSQLTablesThenFixThem()
    {
        $dbService = $this->wiki->services->get(DbService::class);

        foreach ([['pages', 'id', 'int(10) unsigned NOT NULL AUTO_INCREMENT'], ['links', 'id', 'int(10) unsigned NOT NULL AUTO_INCREMENT'], ['nature', 'bn_id_nature', 'int(10) UNSIGNED NOT NULL AUTO_INCREMENT'], ['referrers', 'id', 'int(10) unsigned NOT NULL AUTO_INCREMENT'], ['triples', 'id', 'int(10) unsigned NOT NULL AUTO_INCREMENT'],] as $data) {
            $this->checkThenUpdateColumnAutoincrement($dbService, $data[0], $data[1], $data[2]);
        }
        foreach ([['pages', 'id', ['id']], ['links', 'id', ['id']], ['nature', 'bn_id_nature', ['bn_id_nature']], ['referrers', 'id', ['id']], ['triples', 'id', ['id']], ['users', 'name', ['name']], ['acls', 'page_tag', ['page_tag', 'privilege']], ['acls', 'privilege', ['page_tag', 'privilege']],] as $data) {
            $this->checkThenUpdateColumnPrimary($dbService, $data[0], $data[1], $data[2]);
        }

    }

    // helper method, set public
    public function checkThenUpdateColumnAutoincrement(
        $dbService,
        string $tableName,
        string $columnName,
        string $SQL_columnDef
    ) {
        try {
            $data = $this->getColumnInfo($dbService, $tableName, $columnName);
        } catch (Exception $ex) {
            if ($ex->getCode() != 1) {
                throw $ex;
            }
            $data = [];
        }
        if (empty($data['Extra']) || (is_string($data['Extra']) && strstr($data['Extra'], 'auto_increment') === false)) {
            if (empty($data)) {
                $dataIndex = $this->getColumnInfo($dbService, $tableName, 'index');
                if (
                    !empty(array_filter($dataIndex, function ($keyData) {
                        return !empty ($keyData['Key_name']) && $keyData['Key_name'] == 'PRIMARY';
                    }))
                ) {
                    $dbService->query("ALTER TABLE {$dbService->prefixTable($tableName)} DROP PRIMARY KEY;");
                }
                $dbService->query("ALTER TABLE {$dbService->prefixTable($tableName)} ADD COLUMN `$columnName` $SQL_columnDef FIRST, ADD PRIMARY KEY(`$columnName`);");
            }
            $dbService->query("ALTER TABLE {$dbService->prefixTable($tableName)} MODIFY COLUMN `$columnName` $SQL_columnDef;");
            $data = $this->getColumnInfo($dbService, $tableName, $columnName);
            if (empty($data['Extra']) || (is_string($data['Extra']) && strstr($data['Extra'], 'auto_increment') === false)) {
                throw new Exception("tables `$tableName`, column `$columnName` not updated !", 1);
            }
        }
    }

    // helper method, set public
    public function checkThenUpdateColumnPrimary($dbService, string $tableName, string $columnName, array $newKeys)
    {
        $data = $this->getColumnInfo($dbService, $tableName, $columnName);
        if (empty($data['Key']) || $data['Key'] !== "PRI") {
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
                if (
                    !empty(array_filter($data, function ($keyData) {
                        return !empty ($keyData['Key_name']) && $keyData['Key_name'] == 'PRIMARY';
                    }))
                ) {
                    $dbService->query("ALTER TABLE {$dbService->prefixTable($tableName)} DROP PRIMARY KEY;");
                }
                $dbService->query("ALTER TABLE {$dbService->prefixTable($tableName)} ADD PRIMARY KEY($newKeysFormatted);");
            }
            $data = $this->getColumnInfo($dbService, $tableName, $columnName);
            if (empty($data['Key']) || $data['Key'] !== "PRI") {
                throw new Exception("tables `$tableName`, column `$columnName` key not updated !", 1);
            }
        }
    }

    // helper method, set public
    public function getColumnInfo($dbService, string $tableName, string $columnName): array
    {
        if ($columnName == 'index') {
            $result = $dbService->query("SHOW INDEX FROM {$dbService->prefixTable($tableName)};");
            if (@mysqli_num_rows($result) === 0) {
                return [];
            }
        } else {
            $result = $dbService->query("SHOW COLUMNS FROM {$dbService->prefixTable($tableName)} LIKE '$columnName';");
            if (@mysqli_num_rows($result) === 0) {
                throw new Exception("tables `$tableName` not verified because error while getting `$columnName` column !", 1);
            }
        }
        $data = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        return $data;
    }

    // replace CalcField value by string
    private function calcFieldToString()
    {
        $dbService = $this->wiki->services->get(DbService::class);
        $formManager = $this->wiki->services->get(FormManager::class);

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
                        $sql = <<<SQL
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
                                        $replaceSQL = <<<SQL
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
    }

    // If default_comment_acl is not yet defined, update all pages with comments-closed ACL
    private function fixDefaultCommentsAcls()
    {
        $config = $this->wiki->services->get(ConfigurationService::class)->getConfiguration('wakka.config.php');
        $config->load();
        if (empty($config['default_comment_acl'])) {
            $config['default_comment_acl'] = 'comments-closed';
            $config->write();

            // Update all pages with new ACL
            $pageManager = $this->wiki->services->get(PageManager::class);
            $aclService = $this->wiki->services->get(AclService::class);

            $pages = $pageManager->getAll();
            foreach ($pages as $page) {
                $pageCommentAcl = $aclService->load($page['tag'], 'comment', false)['list'] ?? '';
                if (!empty($pageCommentAcl) && preg_match("/comment-closed\s*/", strval($pageCommentAcl))) {
                    dump($page['tag']);
                    $aclService->save($page['tag'], 'comment', "comments-closed");
                }
            }
        }
    }
}