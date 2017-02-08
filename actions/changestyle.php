<?php

// Action changesstyle.php version 0.2 du 16/03/2004
// pour WikiNi 0.4.1rc (=> é la version du 200403xx) et supérieurs
// Par Charles Népote (c) 2004
// Licence GPL


// Cette action permet de sélectionner une feuille de stye CSS
// alternative é la feuille "wakka.css".
//
// Le nom de la feuille de style (spécifié dans le paramétre "link")
// peut contenir des lettres et des chiffres, mais pas de point. Il
// doit étre spécifié sans l'extension finale ".css".
//
// Insérée dans une page, cette action affichera un lien permettant de
// basculer vers le théme spécifié.
//
// Fonctionnement
//
// . si ce nom n'est pas constitué uniquement de caractéres alphanumériques,
//   une erreur est retournée
// . si ce nom est valide et que la feuille de style existe :
//   . on change le cookie utilisateur
//   . on redirige dans la foulée l'utilisateur vers la page en cours ;
//     cela permet de ne pas avoir é lui demander d'actualiser
//     lui-méme la page


// Usage :
//
// -- {{changestyle link="BeauThemeBleu"}}
//    donne le lien suivant :
//    Feuille de style BeauThemeBleu
//
// -- {{changestyle link="BeauThemeBleu" title="Ouragan"}}
//    donne le lien suivant :
//    Ouragan


// Fonctionnalités restant é ajouter :
//
// -- {{changestyle}}
//    donne un formulaire :
//    Entrer l'adresse de la feuille de style désirée : [     ]
//
// -- {{changestyle choice="Toto;Titi;Tata"}}
//	[] Feuille de style Toto
//	[] Feuille de style Titi
//	[] Feuille de style Tata


$set = isset($_GET["set"]) ? $_GET["set"] : '';


if ($this->GetParameter("link"))
{
	echo	"<a href=\"".$this->href()."&set=".$this->GetParameter("link")."\">";
	echo	(!$this->GetParameter("title")) ? "Feuille de style ".$this->GetParameter("link") : $this->GetParameter("title");
	echo	"</a>";
}


// Do it.
if (preg_match("/^[[:alnum:]][[:alnum:]]+$/", $set))
{
	$this->SetPersistentCookie('sitestyle', $set, 1);
	header("Location: ".$this->href());
}
else if ($set)
{
	$this->SetMessage("Le nom '".htmlspecialchars($set, ENT_COMPAT, YW_CHARSET)."' n'est pas conforme é la r&egrave;gle de nommage impos&eacute;e par l'action ChangeStyle. Reportez-vous &agrave; la documentation de cette action pour plus de pr&eacute;cisions.");
}
?>
