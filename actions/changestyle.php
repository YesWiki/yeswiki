<?php

// Action changesstyle.php version 0.2 du 16/03/2004
// pour WikiNi 0.4.1rc (=> � la version du 200403xx) et sup�rieurs
// Par Charles N�pote (c) 2004
// Licence GPL


// Cette action permet de s�lectionner une feuille de stye CSS
// alternative � la feuille "wakka.css".
//
// Le nom de la feuille de style (sp�cifi� dans le param�tre "link")
// peut contenir des lettres et des chiffres, mais pas de point. Il
// doit �tre sp�cifi� sans l'extension finale ".css".
//
// Ins�r�e dans une page, cette action affichera un lien permettant de
// basculer vers le th�me sp�cifi�.
//
// Fonctionnement
//
// . si ce nom n'est pas constitu� uniquement de caract�res alphanum�riques,
//   une erreur est retourn�e
// . si ce nom est valide et que la feuille de style existe :
//   . on change le cookie utilisateur
//   . on redirige dans la foul�e l'utilisateur vers la page en cours ;
//     cela permet de ne pas avoir � lui demander d'actualiser
//     lui-m�me la page


// Usage :
//
// -- {{changestyle link="BeauThemeBleu"}}
//    donne le lien suivant :
//    Feuille de style BeauThemeBleu
//
// -- {{changestyle link="BeauThemeBleu" title="Ouragan"}}
//    donne le lien suivant :
//    Ouragan


// Fonctionnalit�s restant � ajouter :
//
// -- {{changestyle}}
//    donne un formulaire :
//    Entrer l'adresse de la feuille de style d�sir�e : [     ]
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
	$this->SetMessage("Le nom '".htmlspecialchars($set, ENT_COMPAT, YW_CHARSET)."' n'est pas conforme � la r&egrave;gle de nommage impos&eacute;e par l'action ChangeStyle. Reportez-vous &agrave; la documentation de cette action pour plus de pr&eacute;cisions.");
}
?>
