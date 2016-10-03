<?php
/*
writeconfig.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Patrick PAUL
Copyright  2003  Jean-Pascal MILCENT
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}
if (empty($_POST['config'])) {
    header('Location: '.myLocation());
    die(_t('PROBLEM_WHILE_INSTALLING'));
}
?>
    <div class="jumbotron">
      <h1><?php echo _t('INSTALLATION_OF_YESWIKI'); ?></h1>
      <h4>(<?php echo YESWIKI_VERSION.' - '.YESWIKI_RELEASE; ?>)</h4>
      <p><?php echo _t('WRITING_CONFIGURATION_FILE'); ?></p>
    </div>
<?php
// fetch config
$config = $config2 = unserialize($_POST['config']);

// merge existing configuration with new one
$config = array_merge($wakkaConfig, $config);

// set version to current version, yay!
$config['wikini_version'] = WIKINI_VERSION;
$config['wakka_version'] = WAKKA_VERSION;
$config['yeswiki_version'] = YESWIKI_VERSION;
$config['yeswiki_release'] = YESWIKI_RELEASE;

// convert config array into PHP code
$configCode = "<?php\n// wakka.config.php "._t('CREATED').' '.strftime('%c')."\n// "._t('DONT_CHANGE_YESWIKI_VERSION_MANUALLY')." !\n\n\$wakkaConfig = ";
if (function_exists('var_export')) {
    // var_export gives a better result but was added in php 4.2.0 (wikini asks only php 4.1.0)
    $configCode .= var_export($config, true).";\n?>";
} else {
    $configCode .= "array(\n";
    foreach ($config as $k => $v) {
        // avoid problems with quotes and slashes
        $entries[] = "\t'".$k."' => '".str_replace(array('\\', "'"), array('\\\\', '\\\''), $v)."'";
    }
    $configCode .= implode(",\n", $entries).");\n\n";
}

// try to write configuration file
echo '<b>'._t('WRITING_CONFIGURATION_FILE_WIP')." ...</b><br>\n";
test(_t('WRITING_CONFIGURATION_FILE').' <tt>'.$wakkaConfigLocation.'</tt> ...', $fp = @fopen($wakkaConfigLocation, 'w'), '', 0);

if ($fp) {
    fwrite($fp, $configCode);
    // write
    fclose($fp);

    echo    "<br />\n<div class=\"alert alert-success\"><strong>"._t('FINISHED_CONGRATULATIONS').' !</strong><br />'._t('IT_IS_RECOMMANDED_TO_REMOVE_WRITE_ACCESS_TO_CONFIG_FILE').' <tt>wakka.config.php</tt> ('._t('THIS_COULD_BE_UNSECURE').').</div>';
    echo "<div class=\"form-actions\">\n<a class=\"btn btn-primary btn-large continuer\" href=\"",$config['base_url'],'">'._t('GO_TO_YOUR_NEW_YESWIKI_WEBSITE')."</a>\n</div>\n";
} else {
    // complain
    echo    "<br />\n<div class=\"alert alert-danger\"><strong>"._t('WARNING').'</strong> :</span> '._t('CONFIGURATION_FILE').' <tt>',$wakkaConfigLocation,'</tt> '._t('CONFIGURATION_FILE_NOT_CREATED').'.<br />'.
            _t('TRY_CHANGE_ACCESS_RIGHTS_OR_FTP_TRANSFERT').
            '<tt>wakka.config.php</tt> '._t('DIRECTLY_IN_THE_YESWIKI_FOLDER').".</div>\n";
    echo "\n<pre><xmp>",$configCode,"</xmp></pre>\n";
    ?>
  <form action="<?php echo  myLocation() ?>?installAction=writeconfig" method="POST">
  <input type="hidden" name="config" value="<?php echo  htmlspecialchars(serialize($config2), ENT_COMPAT, YW_CHARSET) ?>">
  <div class="form-actions">
    <input type="submit" class="btn btn-large btn-primary continuer" value="<?php echo _t('TRY_AGAIN'); ?>">
  </div>
  </form>
    <?php
}
?>
