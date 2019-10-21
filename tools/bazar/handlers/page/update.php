<?php

// Verification de securite
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$result = $this->Query("SHOW COLUMNS FROM ".$this->config['table_prefix']."nature LIKE 'bn_sem_context'");

if( @mysqli_num_rows($result) === 0) {
    echo('Adding fields bn_sem_context and bn_sem_type to ' . $this->config['table_prefix'].'nature table...</br>');

    $this->Query("ALTER TABLE ".$this->config['table_prefix']."nature ADD COLUMN bn_sem_context text COLLATE utf8mb4_unicode_ci AFTER bn_condition");
    $this->Query("ALTER TABLE ".$this->config['table_prefix']."nature ADD COLUMN bn_sem_type varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER bn_sem_context");

    echo('Done !');
} else {
    echo('The table'.$this->config['table_prefix'].'nature is already up-to-date !');
}
