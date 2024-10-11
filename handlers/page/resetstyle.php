<?php

// Fonctionnement
//
// Cet handler permet à l'utilisateur de revenir é la feuille de style par défaut du site.
// Techniquement :

// Usage :
// http://example.org/PageTest/resetstyle

// A compléter (peut-étre un jour) :
//
// -- détecter le fichier par défaut via une variable de configuration
//

// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$this->SetPersistentCookie('sitestyle', 'wakka', 1);
header('Location: ' . $this->href());
