<?php

use YesWIki\Core\Controller\AuthController;
use YesWIki\Core\Service\UserManager;

// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

header('Content-type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) && (
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] == 'POST' ||
       $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] == 'DELETE' ||
       $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] == 'PUT'
    )) {
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: X-Requested-With');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT');
        header('Access-Control-Max-Age: 86400');
    }
    $this->exit();
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST)) {
    $_POST = json_decode(file_get_contents('php://input'), true) ?? [];
}

$authController = $this->services->get(AuthController::class);
$userManager = $this->services->get(UserManager::class);
if (isset($_POST['logout']) && $_POST['logout'] == '1') {
    // cas de la déconnexion
    if ($user = $authController->getLoggedUser()) {
        $authController->logout();
        echo json_encode(['userlogout' => $user['name']]);
    } else {
        echo json_encode(['error' => _t('LOGIN_NO_CONNECTED_USER')]);
    }
} elseif (isset($_POST['name']) && $_POST['name'] != '' && $existingUser = $userManager->getOneByName($_POST['name'])) {
    // si l'utilisateur existe, on vérifie son mot de passe
    if ($authController->checkPassword($_POST['password'], $existingUser)) {
        $authController->login($existingUser, $_POST['remember']);
        echo json_encode(['user' => $authController->getLoggedUser()]);
    } else {
        header('HTTP/1.1 401 Unauthorized');
        // on affiche une erreur sur le mot de passe sinon
        echo json_encode(['error' => _t('LOGIN_WRONG_PASSWORD')]);
    }
} else {
    // si le nomWiki est un mail
    if (isset($_POST['name']) && strstr($_POST['name'], '@')) {
        $_POST['email'] = $_POST['name'];
    }
    if (isset($_POST['email']) && $_POST['email'] != '' && $existingUser = $userManager->getOneByEmail($_POST['email'])) {
        // si le mot de passe est bon, on créée le cookie et on redirige sur la bonne page
        if ($authController->checkPassword($_POST['password'], $existingUser)) {
            $authController->login($existingUser, $_POST['remember']);
            echo json_encode(['user' => $authController->getLoggedUser()]);
        } else {
            header('HTTP/1.1 401 Unauthorized');
            // on affiche une erreur sur le mot de passe sinon
            echo json_encode(['error' => _t('LOGIN_WRONG_PASSWORD')]);
        }
    } else {
        header('HTTP/1.1 401 Unauthorized');
        // on affiche une erreur sur le mot de passe sinon
        echo json_encode(['error' => _t('LOGIN_WRONG_USER')]);
    }
}
