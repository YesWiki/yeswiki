<?php

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\DbService;
use YesWiki\Security\Controller\SecurityController;

// Verification de securite
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if ($this->services->get(SecurityController::class)->isWikiHibernated()) {
    throw new \Exception(_t('WIKI_IN_HIBERNATION'));
};

$output = '';
if (empty($this->config['is_cli']) || $this->config['is_cli'] !== true) {
    $output = $this->header();
}

if ($this->UserIsAdmin()) {
    $output .= '<strong>YesWiki core</strong><br />';
    $dblink = $this->services->get(DbService::class)->getLink();

    // drop old nature table fields
    $result = $this->Query("SHOW COLUMNS FROM ".$this->config['table_prefix']."nature WHERE FIELD IN ('bn_ce_id_menu' ,'bn_commentaire' , 'bn_appropriation' , 'bn_image_titre' , 'bn_image_logo' , 'bn_couleur_calendrier' , 'bn_picto_calendrier' , 'bn_type_fiche' , 'bn_label_class')");
    if (@mysqli_num_rows($result) > 0) {
        $output .= 'ℹ️ Removing old fields from ' . $this->config['table_prefix'].'nature table.<br />';

        // don't show output because it can be an error if column doesn't exists
        @$this->Query("ALTER TABLE ".$this->config['table_prefix']."nature DROP `bn_ce_id_menu`;");
        @$this->Query("ALTER TABLE ".$this->config['table_prefix']."nature DROP `bn_commentaire`;");
        @$this->Query("ALTER TABLE ".$this->config['table_prefix']."nature DROP `bn_appropriation`;");
        @$this->Query("ALTER TABLE ".$this->config['table_prefix']."nature DROP `bn_image_titre`;");
        @$this->Query("ALTER TABLE ".$this->config['table_prefix']."nature DROP `bn_image_logo`;");
        @$this->Query("ALTER TABLE ".$this->config['table_prefix']."nature DROP `bn_couleur_calendrier`;");
        @$this->Query("ALTER TABLE ".$this->config['table_prefix']."nature DROP `bn_picto_calendrier`;");
        @$this->Query("ALTER TABLE ".$this->config['table_prefix']."nature DROP `bn_type_fiche`;");
        @$this->Query("ALTER TABLE ".$this->config['table_prefix']."nature DROP `bn_label_class`;");
        @$this->Query("ALTER TABLE ".$this->config['table_prefix']."nature MODIFY COLUMN bn_ce_i18n VARCHAR(5) NOT NULL DEFAULT ''");
        $output .= '✅ Done !<br />';
    } else {
        $output .= '✅ The table '.$this->config['table_prefix'].'nature is already cleaned up from old tables !<br />';
    }

    // remove createur field
    $entryManager = $this->services->get(EntryManager::class);
    if (method_exists(EntryManager::class, 'removeAttributes')) {
        if ($entryManager->removeAttributes([], ['createur'], true)) {
            $output .= 'ℹ️ Removing createur field from bazar entries in ' . $this->config['table_prefix'].'pages table.<br />';
            $output .= '✅ Done !<br />';
        } else {
            $output .= '✅ The table '.$this->config['table_prefix'].'pages is already free of createur fields in bazar entries !<br />';
        }
    } else {
        $output .= '! Not possible to remove createur field from bazar entries in ' . $this->config['table_prefix'].'pages table.<br />';
    }

    // add semantic bazar fields
    $result = $this->Query("SHOW COLUMNS FROM ".$this->config['table_prefix']."nature LIKE 'bn_sem_context'");
    if (@mysqli_num_rows($result) === 0) {
        $output .= 'ℹ️ Adding fields bn_sem_context, bn_sem_type and bn_sem_use_template to ' . $this->config['table_prefix'].'nature table.<br />';

        $this->Query("ALTER TABLE ".$this->config['table_prefix']."nature ADD COLUMN bn_sem_context text COLLATE utf8mb4_unicode_ci AFTER bn_condition");
        $this->Query("ALTER TABLE ".$this->config['table_prefix']."nature ADD COLUMN bn_sem_type varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER bn_sem_context");
        $this->Query("ALTER TABLE ".$this->config['table_prefix']."nature ADD COLUMN bn_sem_use_template tinyint(1) NOT NULL DEFAULT 1 AFTER bn_sem_type");

        $output .= '✅ Done !<br />';
    } else {
        $output .= '✅ The table '.$this->config['table_prefix'].'nature is already up-to-date with semantic fields!<br />';
    }
} else {
    $output .= '<div class="alert alert-danger">'._t('ACLS_RESERVED_FOR_ADMINS').'</div>';
}
$output .= '<hr />';

// BE CAREFULL this comment is used for extensions to add content above, don't delete it!
$output .= '<!-- end handler /update -->';


if (empty($this->config['is_cli']) || $this->config['is_cli'] !== true) {
    // add button to return to previous page
    $output .= '<div>
        <a class="btn btn-sm btn-secondary-1" href="'.$this->Href().'">
            <i class="fas fa-arrow-left"></i>' . _t('GO_BACK') . '
        </a>
    </div>';
    $output .= $this->footer();
}
echo $output;
