<?php

// Handler resetstyle.php version 0.3 du 05/07/2004
// pour WikiNi 0.4.1rc (=> é la version du 200403xx) et supérieurs
// Par Charles Népote (c) 2004
// Licence GPL


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
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$this->SetPersistentCookie('sitestyle', 'wakka', 1);
header("Location: ".$this->href());
