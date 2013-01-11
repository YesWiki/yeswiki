<?php
/*
changepassword.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Charles NEPOTE
Copyright 2002  Patrick PAUL
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
if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}
if ($user = $this->GetUser()) {
    if ($_REQUEST["action"] == "changepass") {
            // check password
            $password = $_POST["password"];
            if (preg_match("/ /", $password)) $error = "Les espaces ne sont pas permis dans les mots de passe.";
            else if (strlen($password) < 5) $error = "Mot de passe trop court.";
            else if ($user["password"] != md5($_POST["oldpass"])) $error = "Mauvais mot de passe.";
            else {
                $this->Query("update ".$this->config["table_prefix"]."users set "."password = md5('".mysql_escape_string($password)."') "."where name = '".$user["name"]."'");
                $this->SetMessage("Mot de passe chang&eacute; !");
                $user["password"]=md5($password);
                $this->SetUser($user);
                //envoi mail nouveau mot de passe
                $lien = str_replace("/wakka.php?wiki=","",$this->config["base_url"]);
                $objetmail = '['.str_replace("http://","",$lien).'] Vos nouveaux identifiants sur le site '.$this->config["wakka_name"];
                $messagemail = "Bonjour!\n\nVotre inscription sur le site a été modifiée, dorénavant vous pouvez vous identifier avec les informations suivantes :\n\nVotre identifiant NomWiki : ".$user["name"]."\nVotre mot de passe : ". $password . "\n\nA très bientôt !\n\nSylvie Vernet, webmestre";
                $headers =   'From: '.BAZ_ADRESSE_MAIL_ADMIN . "\r\n" .
                     'Reply-To: '.BAZ_ADRESSE_MAIL_ADMIN . "\r\n" .
                     'X-Mailer: PHP/' . phpversion();
                mail($user["email"], remove_accents($objetmail), $messagemail, $headers);
                $this->Redirect($this->href());
            }
    }
    // user is logged in; display config form
    echo $this->FormOpen();
    ?>
    <input type="hidden" name="action" value="update" />
    <?php echo $this->Format("======Changement de mot de passe======"); ?>
    <table border="0">
        <tr>
            <td align="right"></td>
            <td>Bonjour, <?php echo $this->Link($user["name"]) ?>&nbsp;!</td>
        </tr>
    </table>

    <?php
    echo $this->FormClose();

    echo $this->FormOpen();
    ?>
    <input type="hidden" name="action" value="changepass" />
    <table border="0">
        <?php
        if (isset($error)) {
            echo "<tr><td></td><td><div class=\"error\">", $this->Format($error), "</div></td></tr>\n";
        }
        ?>
        <tr>
            <td align="right">Votre ancien mot de passe&nbsp;:</td>
            <td><input type="password" name="oldpass" size="40" /></td>
        </tr>
        <tr>
            <td align="right">Nouveau mot de passe&nbsp;:</td>
            <td><input type="password" name="password" size="40" /></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" value="Changer" size="40" /></td>
        </tr>
    </table>
    <?php
    echo $this->FormClose();

} else {
    echo $this->Format("//Vous devez être identifiés pour changer de mot de passe...//")."\n";
}
