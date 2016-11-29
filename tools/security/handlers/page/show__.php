<?php

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if ($this->config['use_nospam']) {
    if ($this->HasAccess("comment") && !$this->page['comment_on']) {
        $time = time();
        $nospam = array(
          'nospam1'    => substr(sha1(uniqid(rand(), true)), rand(8, 32)),
          'nospam2'    => substr(sha1(uniqid(rand(), true)), rand(8, 32)),
          'nospam2-val'  => substr(sha1(uniqid(rand(), true)), rand(8, 32)),
          'salt'       => substr(sha1(uniqid(rand(), true)), rand(8, 32)),
        );

        $str_to_replace = '<textarea name="body" required="required"'
                .'class="textarea-comment" rows="3" placeholder="Ecrire '
                .'votre commentaire ici..."></textarea>';

        $text_form  = "<textarea name=\"body\" required=\"required\" class=\"textarea-comment\" rows=\"3\" placeholder=\"Ecrire votre commentaire ici...\"></textarea>\n";
        $text_form .= "\t<p style=\"display:none;\">\n";
        $text_form .= "\t\t<input type=\"text\" name=\"nxts\" value=\"".$time."\" />\n";
        $text_form .= "\t\t<input type=\"text\" name=\"nxts_signed\" value=\"".sha1($time . $nospam["salt"])."\" />\n";
        if (rand(1, 2) == 1) {
            $text_form .= "\t\t<input type=\"text\" name=\"".$nospam['nospam1']."\" value=\"\" />\n";
            $text_form .= "\t\t<input type=\"text\" name=\"".$nospam['nospam2']."\" value=\"".$nospam['nospam2-val']."\" />\n";
        } else {
            $text_form .= "\t\t<input type=\"text\" name=\"".$nospam['nospam2']."\" value=\"".$nospam['nospam2-val']."\" />\n";
            $text_form .= "\t\t<input type=\"text\" name=\"".$nospam['nospam1']."\" value=\"\" />\n";
        }
        $text_form .=  "\t</p>\n";

        $_SESSION['nospam'] = $nospam;

        // Ajoute l'ID ACEditor au formulaire de commentaire.
        $plugin_output_new =
          str_replace(
              $str_to_replace,
              $text_form,
              $plugin_output_new
          );
    }
}
