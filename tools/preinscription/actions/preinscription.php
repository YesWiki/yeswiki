<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

$max = $this->GetParameter('max');
if (empty($max)) die("Action preinscription param&egrave;te max obligatoire.");

$page = $this->GetParameter('page');
if (empty($page)) die("Action preinscription param&egrave;te page obligatoire.");

$mailadmin = $this->GetParameter('mailadmin');
if (empty($mailadmin)) die("Action preinscription param&egrave;te mailadmin obligatoire.");

$pageinscription = $this->GetParameter('pageinscription');


if (empty($pageinscription) || (!empty($pageinscription) && $this->tag==$pageinscription) ) 
{
	$sql = 'SELECT value FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/preinscription" AND resource="'.$page.'"';
	$result = $this->LoadAll($sql);
	$total = 0;
	if (is_array($result))
	{
		$tableau = array();	
		foreach ($result as $tab)
		{
			$resultat = explode('|',trim($tab["value"]));
			$tableau[$resultat[0]]['prenom'] = $resultat[1];
			$tableau[$resultat[0]]['nom'] = $resultat[2];
			$tableau[$resultat[0]]['tarif'] = $resultat[3];	
			$total = $total + $resultat[3];	
		}
	}
	
	//echo $this->formOpen('preinscription',$this->tag);
	if (!class_exists('HTML_QuickForm')) {
		set_include_path('tools/preinscription/libs/'.PATH_SEPARATOR.get_include_path());
		require_once 'tools/preinscription/libs/HTML/QuickForm.php' ;
	}
	$res='';
	$res .= '<div class="boite_preinscription"><h2>Se pr&eacute;-inscrire par mail</h2>'."\n";
	if ($total > 0)
	{
		$pourcentage= round($total/$max*100) ;
		if ($pourcentage>100) $pourcentage = 100;
		$res .= '<div class="jauge" style="font:10px verdana;">Remplissage :
	<img src="tools/preinscription/presentation/compteur.php?pc='.$pourcentage.'" />
	</div>';
		$res .= '<p style="font:10px verdana;">A 100% de remplissage, un mail sera envoy&eacute; &agrave l\'ensemble des pr&eacute;-inscrits pour fixer la date de la formation.</p>';
	}
	$form_contact = new HTML_QuickForm('preinscription', 'post', $this->config['base_url'].$page.'/preinscription');
	$squelette =& $form_contact->defaultRenderer();
	$squelette->setFormTemplate("\n".'<form {attributes}>'."\n".'{content}'."\n".'</form>'."\n");
	$squelette->setElementTemplate( '<label style="display:block;float:left;clear:both;width:75px;text-align:right;margin:2px;font:10px verdana;">{label}&nbsp;</label>'."\n".'{element}'."\n".
	 '<!-- BEGIN required --><span style="color:red;font:10px verdana;">*</span><!-- END required -->'."\n".'<br />'."\n");
	$squelette->setRequiredNoteTemplate("\n".'<span style="color:red;font:10px verdana;float:right;">* {requiredNote}</span>'."\n");
	$option=array('style'=>'width:100px;border:1px solid black;font:10px verdana;margin:2px;', 'maxlength'=>100);
	$form_contact->setRequiredNote('champs obligatoire') ;
	$form_contact->setJsWarnings('erreur de saisie','corrigez les erreurs suivantes');
	$form_contact->addElement('hidden', 'mailadmin', $mailadmin);
	$form_contact->addElement('hidden', 'pagerenvoi', $this->tag);
	$form_contact->addElement('text', 'Prenom', 'Pr&eacute;nom', $option);
	$form_contact->addRule('Prenom', 'Prenom requis', 'required','', 'client') ;
	$form_contact->addElement('text', 'Nom', 'Nom', $option);
	$form_contact->addRule('Nom', 'Nom requis', 'required','', 'client') ;
	$form_contact->addElement('text', 'Expediteur', 'Adresse mail', $option);
	$form_contact->addRule('Expediteur', 'Adresse mail requise', 'required','', 'client') ;
	$form_contact->addRule('Expediteur', 'L\'adresse mail doit etre de la forme nom@domaine.ext', 'email', '', 'client') ;
	$tarifs = array('100' => '100 euros (OPCA)', '25' => '25 euros (ch&ocirc;meurs, étudiants)');
	$form_contact->addElement('select', 'Tarif', 'Tarif', $tarifs, $option);
	
	//sécurité: il faut que javascript soit activé (passage par le template
	$option = array('class' => 'bouton_antispam');
	$form_contact->addElement('hidden', 'jsenabled', 'N', $option);
	$option = array('style'=>'width:100px;border:1px solid black;font:10px verdana;margin:2px;','onclick' =>
	'$(".bouton_antispam").val(\'Y\');return true;');
	$form_contact->addElement('submit', 'Envoyer', 'Envoyer', $option);
	$res .= $form_contact->toHTML().'</div>';
}
else
{
	$res = '';
}
echo $res ;
?>
