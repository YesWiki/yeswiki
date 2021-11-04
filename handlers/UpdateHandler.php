<?php

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\LinkTracker;
use YesWiki\Core\Service\PageManager;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Core\YesWikiHandler;

class UpdateHandler extends YesWikiHandler
{
    public function run()
    {
        if ($this->getService(SecurityController::class)->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        };

        $output = '';
        if (empty($this->wiki->config['is_cli']) || $this->wiki->config['is_cli'] !== true) {
            $output = $this->wiki->header();
        }

        if ($this->wiki->UserIsAdmin()) {
            $output .= '<strong>YesWiki core</strong><br />';
            $dblink = $this->getService(DbService::class)->getLink();

            // drop old nature table fields
            $result = $this->wiki->Query("SHOW COLUMNS FROM ".$this->wiki->config['table_prefix']."nature WHERE FIELD IN ('bn_ce_id_menu' ,'bn_commentaire' , 'bn_appropriation' , 'bn_image_titre' , 'bn_image_logo' , 'bn_couleur_calendrier' , 'bn_picto_calendrier' , 'bn_type_fiche' , 'bn_label_class')");
            if (@mysqli_num_rows($result) > 0) {
                $output .= 'ℹ️ Removing old fields from ' . $this->wiki->config['table_prefix'].'nature table.<br />';

                // don't show output because it can be an error if column doesn't exists
                @$this->wiki->Query("ALTER TABLE ".$this->wiki->config['table_prefix']."nature DROP `bn_ce_id_menu`;");
                @$this->wiki->Query("ALTER TABLE ".$this->wiki->config['table_prefix']."nature DROP `bn_commentaire`;");
                @$this->wiki->Query("ALTER TABLE ".$this->wiki->config['table_prefix']."nature DROP `bn_appropriation`;");
                @$this->wiki->Query("ALTER TABLE ".$this->wiki->config['table_prefix']."nature DROP `bn_image_titre`;");
                @$this->wiki->Query("ALTER TABLE ".$this->wiki->config['table_prefix']."nature DROP `bn_image_logo`;");
                @$this->wiki->Query("ALTER TABLE ".$this->wiki->config['table_prefix']."nature DROP `bn_couleur_calendrier`;");
                @$this->wiki->Query("ALTER TABLE ".$this->wiki->config['table_prefix']."nature DROP `bn_picto_calendrier`;");
                @$this->wiki->Query("ALTER TABLE ".$this->wiki->config['table_prefix']."nature DROP `bn_type_fiche`;");
                @$this->wiki->Query("ALTER TABLE ".$this->wiki->config['table_prefix']."nature DROP `bn_label_class`;");
                @$this->wiki->Query("ALTER TABLE ".$this->wiki->config['table_prefix']."nature MODIFY COLUMN bn_ce_i18n VARCHAR(5) NOT NULL DEFAULT ''");
                $output .= '✅ Done !<br />';
            } else {
                $output .= '✅ The table '.$this->wiki->config['table_prefix'].'nature is already cleaned up from old tables !<br />';
            }

            // remove createur field
            $entryManager = $this->getService(EntryManager::class);
            if (method_exists(EntryManager::class, 'removeAttributes')) {
                if ($entryManager->removeAttributes([], ['createur'], true)) {
                    $output .= 'ℹ️ Removing createur field from bazar entries in ' . $this->wiki->config['table_prefix'].'pages table.<br />';
                    $output .= '✅ Done !<br />';
                } else {
                    $output .= '✅ The table '.$this->wiki->config['table_prefix'].'pages is already free of createur fields in bazar entries !<br />';
                }
            } else {
                $output .= '! Not possible to remove createur field from bazar entries in ' . $this->wiki->config['table_prefix'].'pages table.<br />';
            }

            // add semantic bazar fields
            $result = $this->wiki->Query("SHOW COLUMNS FROM ".$this->wiki->config['table_prefix']."nature LIKE 'bn_sem_context'");
            if (@mysqli_num_rows($result) === 0) {
                $output .= 'ℹ️ Adding fields bn_sem_context, bn_sem_type and bn_sem_use_template to ' . $this->wiki->config['table_prefix'].'nature table.<br />';

                $this->wiki->Query("ALTER TABLE ".$this->wiki->config['table_prefix']."nature ADD COLUMN bn_sem_context text COLLATE utf8mb4_unicode_ci AFTER bn_condition");
                $this->wiki->Query("ALTER TABLE ".$this->wiki->config['table_prefix']."nature ADD COLUMN bn_sem_type varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER bn_sem_context");
                $this->wiki->Query("ALTER TABLE ".$this->wiki->config['table_prefix']."nature ADD COLUMN bn_sem_use_template tinyint(1) NOT NULL DEFAULT 1 AFTER bn_sem_type");

                $output .= '✅ Done !<br />';
            } else {
                $output .= '✅ The table '.$this->wiki->config['table_prefix'].'nature is already up-to-date with semantic fields!<br />';
            }

            // propose to update content of admin's pages
            $output .= $this->frontUpdateAdminPages();
        } else {
            $output .= '<div class="alert alert-danger">'._t('ACLS_RESERVED_FOR_ADMINS').'</div>';
        }
        $output .= '<hr />';

        // BE CAREFULL this comment is used for extensions to add content above, don't delete it!
        $output .= '<!-- end handler /update -->';


        if (empty($this->wiki->config['is_cli']) || $this->wiki->config['is_cli'] !== true) {
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
                        $output .= (!empty($output) ? ', ':'')._t('NO_RIGHT_TO_WRITE_IN_THIS_PAGE').$page;
                    } else {
                        // save links
                        $linkTracker->registerLinks($this->getService(PageManager::class)->getOne($page));
                    }
                }
            } else {
                $output .= (!empty($output) ? ', ':'').str_replace('{{page}}', $page, _t('UPDATE_PAGE_NOT_FOUND_IN_DEFAULT_SQL'));
            }
        }
        return [empty($output),$output];
    }
}
