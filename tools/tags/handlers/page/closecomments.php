<?php
// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
	die ('acc&egrave;s direct interdit');
}
if (($this->UserIsOwner()) || ($this->UserIsAdmin()))
{
	//on efface l'existant
	$this->DeleteTriple($this->GetPageTag(), 'http://outils-reseaux.org/_vocabulary/comments', null, '', '');
	//on ouvre les commentaires
	$this->InsertTriple($this->GetPageTag(), 'http://outils-reseaux.org/_vocabulary/comments', 0, '', '');
	$this->SetMessage("Les commentaires de cette page ont &eacute;t&eacute; d&eacute;sactiv&eacute;s.");
}
else
{
	$this->SetMessage("Vous devez &ecirc;tre propri&eacute;taire de la page ou membre du groupe admins pour faire cette op&eacute;ration.");
}

$this->redirect($this->href());
?>
