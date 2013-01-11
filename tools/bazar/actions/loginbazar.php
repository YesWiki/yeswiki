

<?php
/*
loginbazar.php
Copyright (c) 2009, Florian SCHMITT <florian@outils-reseaux.org>
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

//Lecture des parametres de l'action
$urllogin = $this->GetParameter("page");
if (empty($urllogin)) {
    $urllogin=$this->href("", "BazaR", "");
} else {
    $urllogin=$this->href("", $urllogin, "");
}

if (!isset($_REQUEST["action"])) $_REQUEST["action"] = '';
if ($_REQUEST["action"] == "logout") {
    $this->LogoutUser();
    $this->SetMessage("Vous &ecirc;tes maintenant d&eacute;connect&eacute; !");
    $this->Redirect($this->href());
}

if ($user = $this->GetUser()) {
    $sql= "SELECT bf_id_fiche FROM '.BAZ_PREFIXE.'fiche WHERE bf_ce_nature IN (9,15) AND bf_mail ='".mysql_escape_string($user['email'])."' LIMIT 1";
    $bazar = $this->LoadSingle($sql);
    if ($bazar) $id= $bazar['bf_id_fiche'];

    // user is logged in; display config form
    include_once 'tools/bazar/libs/squelettephp.class.php';
    $squel = new SquelettePhp('tools/bazar/presentation/squelettes/iden_loggue.tpl.html');
    $squel->set(array(
        "id"=>$id,
        "nom"=>$user['name'],
        "urllogin"=>$urllogin,
        "urldepart"=>$this->href()
    ));
    echo $squel->analyser();
} else {
    // user is not logged in

    // is user trying to log in or register?
    if ($_REQUEST["action"] == "login") {
        // if user name already exists, check password
        if ($existingUser = $this->LoadUser($_POST["name"])) {
            // check password
            if ($existingUser["password"] == md5($_POST["password"])) {
                $this->SetUser($existingUser, 0);
                SetCookie("name", $existingUser["name"],0, $this->CookiePath);
                SetCookie("password", $existingUser["password"],0, $this->CookiePath);
                $sql= "SELECT bf_id_fiche FROM '.BAZ_PREFIXE.'fiche WHERE bf_ce_nature IN (9,15) AND bf_mail ='".mysql_escape_string($existingUser['email'])."' LIMIT 1";
                $bazar = $this->LoadSingle($sql);
                if ($bazar) $id= $bazar['bf_id_fiche'];
                $this->Redirect($urllogin.'&id_fiche='.$id.'&action=voir_fiche');
            } else {
                $this->SetMessage("Erreur identification : mauvais mot de passe.");
                $this->Redirect($this->href());
            }
        } else {
            $this->SetMessage("Erreur identification : NomWiki inconnu.");
            $this->Redirect($this->href());
        }
    } elseif ($_REQUEST['action'] == 'checklogged') {
        $this->SetMessage("Erreur identification : vous devez accepter les cookies pour pouvoir vous connecter.");
        $this->Redirect($this->href());
    }

    include_once 'tools/bazar/libs/squelettephp.class.php';
    $squel = new SquelettePhp('tools/bazar/presentation/squelettes/iden_form.tpl.html');
    $squel->set(array(
        "urllogin"=>$urllogin,
        "urldepart"=>$this->href(),
        "name"=>isset($_POST["name"])?$_POST["name"]:''
    ));
    echo $squel->analyser();
}
?>
