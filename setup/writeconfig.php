<?php
/*
writeconfig.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Patrick PAUL
Copyright  2003  Jean-Pascal MILCENT
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
if (!defined('WIKINI_VERSION'))
{
	die ("acc&egrave;s direct interdit");
}
if (empty($_POST['config']))
{
	header('Location: ' . myLocation());
	die ('probl&egrave;me dans la proc&eacute;dure d\'installation');
}

// fetch config
$config = $config2 = unserialize($_POST["config"]);

// merge existing configuration with new one
$config = array_merge($wakkaConfig, $config);

// set version to current version, yay!
$config["wikini_version"] = WIKINI_VERSION;
$config["wakka_version"] = WAKKA_VERSION;

// convert config array into PHP code
$configCode = "<?php\n// wakka.config.php cr&eacute;&eacute;e ".strftime("%c")."\n// ne changez pas la wikini_version manuellement!\n\n\$wakkaConfig = ";
if (function_exists('var_export'))
{
	// var_export gives a better result but was added in php 4.2.0 (wikini asks only php 4.1.0)
	$configCode .= var_export($config, true) . ";\n?>";
}
else
{
	$configCode .= "array(\n";
	foreach ($config as $k => $v)
	{
		// avoid problems with quotes and slashes
		$entries[] = "\t'".$k."' => '" . str_replace(array('\\', "'"), array('\\\\', '\\\''), $v) . "'";
	}
	$configCode .= implode(",\n", $entries).");\n?>";
}

// try to write configuration file
echo "<b>Cr&eacute;ation du fichier de configuration en cours...</b><br>\n";
test("&Eacute;criture du fichier de configuration <tt>".$wakkaConfigLocation."</tt>...", $fp = @fopen($wakkaConfigLocation, "w"), "", 0);

if ($fp)
{
	fwrite($fp, $configCode);
	// write
	fclose($fp);
	
	echo	"<p>Voila c'est termin&eacute; ! Vous pouvez " .
			"<a href=\"",$config["base_url"],"\">retourner sur votre " .
			"site WikiNi</a>. Il est conseill&eacute; de retirer " .
			"l'acc&egrave;s en &eacute;criture au fichier " .
			"<tt>wakka.config.php</tt>. Ceci peut &ecirc;tre une faille " .
			"dans la s&eacute;curit&eacute;.</p>";
}
else
{
	// complain
	echo	"<p><span class=\"failed\">AVERTISSEMENT:</span> Le " .
			"fichier de configuration <tt>",$wakkaConfigLocation,"</tt> " .
			"n'a pu &ecirc;tre cr&eacute;&eacute;. " .
			"Veuillez vous assurez que votre serveur a les droits " .
			"d'acc&egrave;s en &eacute;criture pour ce fichier. Si pour " .
			"une raison quelconque vous ne pouvez pas faire &ccedil;a, vous " .
			"devez copier les informations suivantes dans un fichier et " .
			"les transf&eacute;rer au moyen d'un logiciel de transfert de " .
			"fichier (ftp) sur le serveur dans un fichier " .
			"<tt>wakka.config.php</tt> directement dans le r&eacute;pertoire " .
			"de WikiNi. Une fois que vous aurez fait cela, votre site WikiNi " .
			"devrait fonctionner correctement.</p>\n";
	?>
	<form action="<?php echo  myLocation() ?>?installAction=writeconfig" method="POST">
	<input type="hidden" name="config" value="<?php echo  htmlspecialchars(serialize($config2)) ?>">
	<input type="submit" value="Essayer &agrave; nouveau">
	</form>	
	<?php
	echo"<div style=\"background-color: #EEEEEE; padding: 10px 10px;\">\n<pre><xmp>",$configCode,"</xmp></pre>\n</div>\n";
}

?>
