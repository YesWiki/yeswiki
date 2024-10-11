<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}
if (empty($_POST['config'])) {
    header('Location: ' . myLocation());
    exit(_t('PROBLEM_WHILE_INSTALLING'));
}
?>
    <h2><?php echo _t('WRITING_CONFIGURATION_FILE'); ?></h2>
    <?php
// fetch config
$config = $config2 = json_decode($_POST['config'], true);

// merge existing configuration with new one
$config = array_merge($wakkaConfig, $config);

// set version to current version, yay!
$config['wikini_version'] = WIKINI_VERSION;
$config['wakka_version'] = WAKKA_VERSION;
$config['yeswiki_version'] = YESWIKI_VERSION;
$config['yeswiki_release'] = YESWIKI_RELEASE;

// set database encoding : for new installs it's utf8mb4, older wiki will launch an db conversion
$config['db_charset'] = 'utf8mb4';

// convert config array into PHP code
$configCode = "<?php\n// wakka.config.php " . _t('CREATED') . ' ' . date('c') . "\n// " . _t('DONT_CHANGE_YESWIKI_VERSION_MANUALLY') . " !\n\n\$wakkaConfig = ";
if (function_exists('var_export')) {
    // var_export gives a better result but was added in php 4.2.0 (wikini asks only php 4.1.0)
    $configCode .= var_export($config, true) . ";\n?>";
} else {
    $configCode .= "array(\n";
    foreach ($config as $k => $v) {
        // avoid problems with quotes and slashes
        $entries[] = "\t'" . $k . "' => '" . str_replace(['\\', "'"], ['\\\\', '\\\''], $v) . "'";
    }
    $configCode .= implode(",\n", $entries) . ");\n\n";
}

// try to write configuration file
echo '<b>' . _t('WRITING_CONFIGURATION_FILE_WIP') . " ...</b><br>\n";
test(_t('WRITING_CONFIGURATION_FILE') . ' <tt>' . $wakkaConfigLocation . '</tt> ...', $fp = @fopen($wakkaConfigLocation, 'w'), '', 0);

if ($fp) {
    fwrite($fp, $configCode);
    // write
    fclose($fp);

    echo "<br />\n<div class=\"alert alert-success\"><strong>" . _t('FINISHED_CONGRATULATIONS') . ' !</strong><br />' . _t('IT_IS_RECOMMANDED_TO_REMOVE_WRITE_ACCESS_TO_CONFIG_FILE') . ' <tt>wakka.config.php</tt> (' . _t('THIS_COULD_BE_UNSECURE') . ').</div>';
    echo "<div class=\"form-actions\">\n<a class=\"btn btn-lg btn-primary\" href=\"",$config['base_url'] . $config['root_page'],'">' . _t('GO_TO_YOUR_NEW_YESWIKI_WEBSITE') . "</a>\n</div>\n";
//header('Location: '.$config['base_url'].$config['root_page']);
} else {
    // complain
    echo "<br />\n<div class=\"alert alert-danger\"><strong>" . _t('WARNING') . '</strong> :</span> ' . _t('CONFIGURATION_FILE') . ' <tt>',$wakkaConfigLocation,'</tt> ' . _t('CONFIGURATION_FILE_NOT_CREATED') . '.<br />' .
            _t('TRY_CHANGE_ACCESS_RIGHTS_OR_FTP_TRANSFERT') .
            '<tt>wakka.config.php</tt> ' . _t('DIRECTLY_IN_THE_YESWIKI_FOLDER') . ".</div>\n";
    echo "\n<pre><xmp>",$configCode,"</xmp></pre>\n"; ?>
  <form action="<?php echo myLocation(); ?>?installAction=writeconfig" method="POST">
  <input type="hidden" name="config" value="<?php echo htmlspecialchars(json_encode($config2), ENT_COMPAT, YW_CHARSET); ?>">
  <div class="form-actions">
    <input type="submit" class="btn btn-lg btn-primary" value="<?php echo _t('TRY_AGAIN'); ?>">
  </div>
  </form>
    <?php
}
?>
