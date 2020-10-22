<?php

// Verification de securite
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}
$output = $this->header();
if ($this->UserIsAdmin()) {

    // drop old nature table fields
    $result = $this->Query("SHOW COLUMNS FROM ".$this->config['table_prefix']."nature WHERE FIELD IN ('bn_ce_id_menu' ,'bn_commentaire' , 'bn_appropriation' , 'bn_image_titre' , 'bn_image_logo' , 'bn_couleur_calendrier' , 'bn_picto_calendrier' , 'bn_type_fiche' , 'bn_label_class')");   
    if( @mysqli_num_rows($result) > 0) {
        $output .= 'ℹ️ Removing old fields from ' . $this->config['table_prefix'].'nature table.<br />';
        echo('Adding fields bn_sem_context, bn_sem_type and bn_sem_use_template to ' . $this->config['table_prefix'].'nature table...</br>');
    
        $this->Query("ALTER TABLE ".$this->config['table_prefix']."nature  DROP IF EXISTS `bn_ce_id_menu`,
        DROP IF EXISTS `bn_commentaire`,
        DROP IF EXISTS `bn_appropriation`,
        DROP IF EXISTS `bn_image_titre`,
        DROP IF EXISTS `bn_image_logo`,
        DROP IF EXISTS `bn_couleur_calendrier`,
        DROP IF EXISTS `bn_picto_calendrier`,
        DROP IF EXISTS `bn_type_fiche`,
        DROP IF EXISTS `bn_label_class`,
        MODIFY COLUMN bn_ce_i18n VARCHAR(5) NOT NULL DEFAULT ''");
        $output .= '✅Done !<br /><hr />';
    } else {
        $output .= '✅The table '.$this->config['table_prefix'].'nature is already cleaned up from old tables !<hr />';
    }
  
    // add semantic bazar fields
    $result = $this->Query("SHOW COLUMNS FROM ".$this->config['table_prefix']."nature LIKE 'bn_sem_context'");   
    if( @mysqli_num_rows($result) === 0) {
        $output .= 'ℹ️ Adding fields bn_sem_context, bn_sem_type and bn_sem_use_template to ' . $this->config['table_prefix'].'nature table.<br />';
    
        $this->Query("ALTER TABLE ".$this->config['table_prefix']."nature ADD COLUMN bn_sem_context text COLLATE utf8mb4_unicode_ci AFTER bn_condition");
        $this->Query("ALTER TABLE ".$this->config['table_prefix']."nature ADD COLUMN bn_sem_type varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER bn_sem_context");
        $this->Query("ALTER TABLE ".$this->config['table_prefix']."nature ADD COLUMN bn_sem_use_template tinyint(1) NOT NULL DEFAULT 1 AFTER bn_sem_type");
    
        $output .= '✅Done !<br /><hr />';
    } else {
        $output .= '✅The table '.$this->config['table_prefix'].'nature is already up-to-date with semantic fields!<hr />';
    }

} else {
    $output .= '<div class="alert alert-danger">'._t('ACLS_RESERVED_FOR_ADMINS').'</div>';
}
echo $output.'<!-- end handler /update -->'.$this->footer(); // ATTENTION <!-- end handler /update --> va etre utilisé pour que les extensions puissent ajouter du contenu juste au dessus!
