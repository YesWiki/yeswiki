<?php

if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}

if (isset($_POST["action"]) && $_POST["action"] == 'addcomment') {

	if (!isset($_SESSION['nospam']))
		die("Vous avez �t� trop long.");

	$nospam = $_SESSION['nospam'];

	if (!array_key_exists($nospam['nospam1'],$_POST)) {
		die("NoSpam : Commentaire refus�");
	} else if ($_POST[$nospam['nospam1']] != "") {
		die("NoSpam : Commentaire refus�");
	} else if (!array_key_exists($nospam['nospam2'],$_POST)) {
		die("NoSpam : Commentaire refus�");
	} else if ($_POST[$nospam['nospam2']] != $nospam['nospam2-val']) {
		die("NoSpam : Commentaire refus�");
	} else if (!array_key_exists('nxts',$_POST) || !array_key_exists('nxts_signed',$_POST)) {
		die("NoSpam : Commentaire refus�");
	} else if (sha1($_POST['nxts'] . $nospam['salt']) != $_POST['nxts_signed']) {
		die("NoSpam : Commentaire refus�");
	} else if (time() < $_POST['nxts'] + 15) {
		die("NoSpam : trop rapide");
	}

}

?>
