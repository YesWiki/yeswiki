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
*/

if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\Service\UserManager;
use YesWiki\Login\Exception\LoginException;

// Lecture des parametres de l'action

// NOTE: à mettre dans la classe ?
// url d'inscription
$signupurl = $this->GetParameter('signupurl');
// si pas d'url d'inscription renseignée, on utilise ParametresUtilisateur
if (empty($signupurl) || $signupurl === "0") {
    $signupurl = $this->href("", "ParametresUtilisateur", "");
} else {
    if ($this->IsWikiName($signupurl, WN_CAMEL_CASE_EVOLVED)) {
        $signupurl = $this->href('', $signupurl);
    }
}

// url du profil
$profileurl = $this->GetParameter('profileurl');

// sauvegarde de l'url d'ou on vient
$incomingurl = $this->GetParameter('incomingurl');
if (empty($incomingurl)) {
    $url = explode('?', $_SERVER['REQUEST_URI']);
    $d = dirname($url[0].'?');
    $t = ($d != '/' ? str_replace($d, '', $_SERVER['REQUEST_URI']) : $_SERVER['REQUEST_URI']);
    $incomingurl = $this->getBaseUrl().$t;
}

$userpage = $this->GetParameter("userpage");
// si pas d'url de page de sortie renseignée, on retourne sur la page courante
if (empty($userpage)) {
    $userpage = $incomingurl;
    // si l'url de sortie contient le passage de parametres de déconnexion, on l'efface
    if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "logout") {
        $userpage = preg_replace('/(&|\\\?)action=logout(&)?/', '$1', $userpage);
        $userpage = preg_replace('/(&|\\\?)$/m', '', $userpage);
    }
} else {
    if ($this->IsWikiName($userpage, WN_CAMEL_CASE_EVOLVED)) {
        $userpage = $this->href('', $userpage);
    }
}

/*
 * Url "Mot de passe perdu"
 */
$lostpasswordurl = $this->GetParameter('lostpasswordurl');
if (!empty($lostpasswordurl)) {
    if ($this->IsWikiName($lostpasswordurl, WN_CAMEL_CASE_EVOLVED)) {
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
    $btnclass = '';
}
$nobtn = $this->GetParameter("nobtn");

// template par défaut
$template = $this->GetParameter("template");
$template = $this->services->get(TemplateEngine::class)->hasTemplate("@login/$template") ? $template : '';
if (empty($template)) {
    $template = 'default.tpl.html';
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
    $this->Redirect(preg_replace('/(&|\\\?)$/m', '', preg_replace('/(&|\\\?)action=logout(&)?/', '$1', $incomingurl)));
    $this->exit();
}

// cas de l'identification
if ($_REQUEST["action"] == "login") {
    // si l'utilisateur existe, on vérifie son mot de passe
    try {
        $userManager = $this->services->get(UserManager::class);
        if (!empty($_POST["name"])) {
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
            if (empty($name)) {
                throw new LoginException(_t('LOGIN_WRONG_USER'));
            }
            if (strpos($name, '@') !== false) {
                // si le nomWiki est un mail
                $user = $userManager->getOneByEmail($name);
            } else {
                $user = $userManager->getOneByName($name);
            }
        } else {
            if (empty($_POST["email"])) {
                throw new LoginException(_t('LOGIN_WRONG_USER'));
            }
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_STRING);
            if (empty($email)) {
                throw new LoginException(_t('LOGIN_WRONG_USER'));
            }
            $user = $userManager->getOneByEmail($email);
        }
        if (empty($user)) {
            throw new LoginException(_t('LOGIN_WRONG_USER'));
        }
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
        $authController = $this->services->get(AuthController::class);
        if (!$authController->checkPassword($password, $user)) {
            throw new LoginException(_t('LOGIN_WRONG_PASSWORD'));
        }
        $remember = filter_input(INPUT_POST, 'remember', FILTER_VALIDATE_BOOL);
        $userManager->login($user, $remember);
        
        // si l'on veut utiliser la page d'accueil correspondant au nom d'utilisateur
        if ($userpage == 'user' && $this->LoadPage($user["name"])) {
            $this->Redirect($this->href('', $user["name"], ''));
        } else {
            // on va sur la page d'ou on s'est identifie sinon
            $this->Redirect($incomingurl);
        }
    } catch (LoginException $ex) {
        // on affiche une erreur sur le NomWiki sinon
        $this->SetMessage($ex->getMessage());
        $this->Redirect($incomingurl);
    } catch (Exception $ex) {
        // error error
        flash($ex->getMessage(), 'error');
        $this->Redirect($incomingurl);
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
        if ($this->IsWikiName($profileurl, WN_CAMEL_CASE_EVOLVED)) {
            $profileurl = $this->href('', $profileurl);
        }
    }
} else {
    // cas d'une personne non connectée
    $connected = false;

    // si l'authentification passe mais la session n'est pas créée, on a un problème de cookie
    if ($_REQUEST['action'] == 'checklogged') {
        $error = _t('LOGIN_COOKIES_ERROR');
    }
}

// on affiche le template
$content = $this->render("@login/$template", [
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
]);

$output = (!empty($class)) ? '<div class="'.$class.'">'."\n".$content."\n".'</div>'."\n" : $content;

echo $output;
