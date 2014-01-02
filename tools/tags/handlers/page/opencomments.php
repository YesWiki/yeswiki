<?php
// Verification de securite
if (!defined('WIKINI_VERSION')) {
	die ('acc&egrave;s direct interdit');
}
if (($this->UserIsOwner()) || ($this->UserIsAdmin()))
{
	// on efface l'existant
	$this->DeleteTriple($this->GetPageTag(), 'http://outils-reseaux.org/_vocabulary/comments', null, '', '');
	// on ouvre les commentaires
	$this->InsertTriple($this->GetPageTag(), 'http://outils-reseaux.org/_vocabulary/comments', 1, '', '');
	$this->SetMessage(_t('TAGS_COMMENTS_ACTIVATED'));
}
else
{
	$this->SetMessage(_t('TAGS_ONLY_FOR_ADMIN_AND_OWNER'));
}

$this->redirect($this->href());
