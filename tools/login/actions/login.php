<?php
/**
 * login.php
 *
 * parameters (GetParameter):
 * - signupurl
 * - profileurl
 * - incomingurl
 * - userpage
 * - template
 * - class
 * - btnclass
 * $_REQUEST :
 * - action : login|logout|checklogged
 * $_POST :
 * - incomingurl
 * - name
 * - email
 * - password
 * - remember
 *
 * Copyright 2010  Florian SCHMITT
 *
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

include_once 'tools/login/libs/login.functions.php';

// Lecture des parametres de l'action

// url d'inscription
$signupurl = $this->GetParameter('signupurl');
// si pas de pas d'url d'inscription renseignée, on utilise ParametresUtilisateur
if (empty($signupurl) && $signupurl != "0") {
    $signupurl = $this->href("", "ParametresUtilisateur", "");
} else {
    if ($this->IsWikiName($signupurl)) {
        $signupurl = $this->href('', $signupurl);
    }
}

// url du profil
$profileurl = $this->GetParameter('profileurl');

// sauvegarde de l'url d'ou on vient
$incomingurl = $this->GetParameter('incomingurl');
if (empty($incomingurl)) {
    $incomingurl = 'http'.((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
}

$userpage = $this->GetParameter("userpage");
// si pas d'url de page de sortie renseignée, on retourne sur la page courante
if (empty($userpage)) {
    $userpage = $incomingurl;
    // si l'url de sortie contient le passage de parametres de déconnexion, on l'efface
    if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "logout") {
        $userpage = str_replace('&action=logout', '', $userpage);
    }
} else {
    if ($this->IsWikiName($userpage)) {
        $userpage = $this->href('', $userpage);
    }
}

/*
 * Url "Mot de passe perdu"
 */
$lostpasswordurl = $this->GetParameter('lostpasswordurl');
if (!empty($lostpasswordurl)) {
    if ($this->IsWikiName($lostpasswordurl)) {
        $lostpasswordurl = $this->href('', $lostpasswordurl);
    }
} else {
    // TODO : voir pour gerer les pages dans d'autres langues
    $lostpasswordurl = $this->href('', 'MotDePassePerdu');
}


// classe css pour l'action
$class = $this->GetParameter("class");

// classe css pour les boutons
$btnclass = $this->GetParameter("btnclass");
if (empty($btnclass)) {
    $btnclass = 'btn-default';
}
$nobtn = $this->GetParameter("nobtn");

// template par défaut
$template = $this->GetParameter("template");
if (empty($template) || !file_exists('tools/login/presentation/templates/' . $template)) {
    $template = "default.tpl.html";
}

$error = '';
$PageMenuUser = '';

// on initialise la valeur vide si elle n'existe pas
if (!isset($_REQUEST["action"])) {
    $_REQUEST["action"] = '';
}

// cas de la déconnexion
if ($_REQUEST["action"] == "logout") {
    $this->LogoutUser();
    $this->SetMessage(_t('LOGIN_YOU_ARE_NOW_DISCONNECTED'));
    $this->Redirect(str_replace('&action=logout', '', $incomingurl));
    exit;
}

// cas de l'identification
if ($_REQUEST["action"] == "login") {
    // si l'utilisateur existe, on vérifie son mot de passe
    if (isset($_POST["name"]) && $_POST["name"] != '' && $existingUser = $this->LoadUser($_POST["name"])) {
        // si le mot de passe est bon, on créée le cookie et on redirige sur la bonne page
        if ($existingUser["password"] == md5($_POST["password"])) {
            $this->SetUser($existingUser, $_POST["remember"]);

            // si l'on veut utiliser la page d'accueil correspondant au nom d'utilisateur
            if ($userpage == 'user' && $this->LoadPage($_POST["name"])) {
                $this->Redirect($this->href('', $_POST["name"], ''));
            } else {
                // on va sur la page d'ou on s'est identifie sinon
                $this->Redirect($incomingurl);
            }
        } else {
            // on affiche une erreur sur le mot de passe sinon
            $this->SetMessage(_t('LOGIN_WRONG_PASSWORD'));
            $this->Redirect($incomingurl);
        }
    } else {
        // si le nomWiki est un mail
        if (isset($_POST["name"]) && strstr($_POST["name"], '@')) {
            $_POST["email"] = $_POST["name"];
        }
        if (isset($_POST["email"]) && $_POST["email"] != '' && $existingUser = loadUserbyEmail($_POST["email"])) {
            // si le mot de passe est bon, on créée le cookie et on redirige sur la bonne page
            if ($existingUser["password"] == md5($_POST["password"])) {
                $this->SetUser($existingUser, $_POST["remember"]);

                // si l'on veut utiliser la page d'accueil correspondant au nom d'utilisateur
                if ($userpage == 'user' && $this->LoadPage($existingUser["name"])) {
                    $this->Redirect($this->href('', $existingUser["name"], ''));
                } else {
                    // on va sur la page d'ou on s'est identifie sinon
                    $this->Redirect($incomingurl);
                }
            } else {
                // on affiche une erreur sur le mot de passe sinon
                $this->SetMessage(_t('LOGIN_WRONG_PASSWORD'));
                $this->Redirect($incomingurl);
            }
        } else {
            // on affiche une erreur sur le NomWiki sinon
            $this->SetMessage(_t('LOGIN_WRONG_USER'));
            $this->Redirect($incomingurl);
        }
    }
}

// cas d'une personne connectée déjà
if ($user = $this->GetUser()) {
    $connected = true;
    if ($this->LoadPage("PageMenuUser")) {
        $PageMenuUser.= $this->Format("{{include page=\"PageMenuUser\"}}");
    }

    // si pas de pas d'url de profil renseignée, on utilise ParametresUtilisateur
    if (empty($profileurl)) {
        $profileurl = $this->href("", "ParametresUtilisateur", "");
    } elseif ($profileurl == 'WikiName') {
        $profileurl = $this->href("edit", $user['name'], "");
    } else {
        if ($this->IsWikiName($profileurl)) {
            $profileurl = $this->href('', $profileurl);
        }
    }
} else {
    // cas d'une personne non connectée
    $connected = false;

    // si l'authentification passe mais la session n'est pas créée, on a un problème de cookie
    if ($_REQUEST['action'] == 'checklogged') {
        $error = 'Vous devez accepter les cookies pour pouvoir vous connecter.';
    }
}

//
// on affiche le template
//

include_once('tools/libs/squelettephp.class.php');

// on cherche un template personnalise dans le repertoire themes/tools/bazar/templates
$templatetoload = 'themes/tools/login/templates/' . $template;

if (!is_file($templatetoload)) {
    $templatetoload = 'tools/login/presentation/templates/' . $template;
}

$squel = new SquelettePhp($templatetoload);
$squel->set(
    array(
        "connected" => $connected,
        "user" => ((isset($user["name"])) ? $user["name"] : ((isset($_POST["name"])) ? $_POST["name"] : '')),
        "email" => ((isset($user["email"])) ? $user["email"] : ((isset($_POST["email"])) ? $_POST["email"] : '')),
        "incomingurl" => $incomingurl,
        "signupurl" => $signupurl,
        'lostpasswordurl' => $lostpasswordurl,
        "profileurl" => $profileurl,
        "userpage" => $userpage,
        "PageMenuUser" => $PageMenuUser,
        "btnclass" => $btnclass,
        "nobtn" => $nobtn,
        "error" => $error
    )
);

$output = (!empty($class)) ? '<div class="'.$class.'">'."\n".$squel->analyser()."\n".'</div>'."\n" : $squel->analyser();

echo $output;
