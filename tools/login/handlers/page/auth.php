<?php

/*
auth.php

Copyright 2015  Florian Schmitt <mrflos@gmail.com>
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

include_once 'tools/login/libs/login.functions.php';

header('Content-type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) && (
       $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] == 'POST' ||
       $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] == 'DELETE' ||
       $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] == 'PUT' )) {
             header("Access-Control-Allow-Credentials: true");
             header('Access-Control-Allow-Headers: X-Requested-With');
             header('Access-Control-Allow-Headers: Content-Type');
             header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT');
             header('Access-Control-Max-Age: 86400');
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST)) {
    $_POST = json_decode(file_get_contents('php://input'), true);
}

if (isset($_POST["logout"]) && $_POST["logout"] == '1') {
    // cas de la déconnexion
    if ($user = $this->GetUser()) {
        $this->LogoutUser();
        echo json_encode(array('userlogout' => $user['name']));
    } else {
        echo json_encode(array('error' => _t('LOGIN_NO_CONNECTED_USER')));
    }
} elseif (isset($_POST["name"]) && $_POST["name"] != '' && $existingUser = $this->LoadUser($_POST["name"])) {
    // si l'utilisateur existe, on vérifie son mot de passe
    if ($existingUser["password"] == md5($_POST["password"])) {
        $this->SetUser($existingUser, $_POST["remember"]);
        echo json_encode(array('user' => $this->GetUser()));
    } else {
        header('HTTP/1.1 401 Unauthorized');
        // on affiche une erreur sur le mot de passe sinon
        echo json_encode(array('error' => _t('LOGIN_WRONG_PASSWORD')));
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
            echo json_encode(array('user' => $this->GetUser()));
        } else {
            header('HTTP/1.1 401 Unauthorized');
            // on affiche une erreur sur le mot de passe sinon
            echo json_encode(array('error' => _t('LOGIN_WRONG_PASSWORD')));
        }
    } else {
        header('HTTP/1.1 401 Unauthorized');
        // on affiche une erreur sur le mot de passe sinon
        echo json_encode(array('error' => _t('LOGIN_WRONG_USER')));
    }
}
