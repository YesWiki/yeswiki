<?php
/*
textsearch.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002  Patrick PAUL
Copyright 2004  Jean Christophe ANDRÉ
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

// label à afficher devant la zone de saisie
$label = $this->GetParameter('label', 'Ce que vous souhaitez chercher&nbsp;: ');
// largeur de la zone de saisie
$size = $this->GetParameter('size', '40');
// texte du bouton
$button = $this->GetParameter('button', 'Chercher');
// texte à chercher
$phrase = $this->GetParameter('phrase', false);
// séparateur entre les éléments trouvés
$separator = $this->GetParameter('separator', false);

// se souvenir si c'était :
// -- un paramètre de l'action : {{textsearch phrase="Test"}}
// -- ou du CGI http://example.org/wakka.php?wiki=RechercheTexte&phrase=Test
//
// récupérer le paramètre de l'action
$paramPhrase = $phrase;
// ou, le cas échéant, récupérer le paramètre du CGI
if (!$phrase && isset($_GET['phrase'])) $phrase = $_GET['phrase'];

// s'il y a un paramètre d'action "phrase", on affiche uniquement le résultat
// dans le cas contraire, présenter une zone de saisie
if (!$paramPhrase)
{
	echo $this->FormOpen('', '', 'get');
	if ($label)
	{
		echo $this->Format($label), ' ';
	}
	echo '<input name="phrase" size="', htmlspecialchars($size), '" value="', htmlspecialchars($phrase), '" />';
	if ($button)
	{
		echo '&nbsp;<input type="submit" value="', htmlspecialchars($button), '" />';
	}
	echo "\n", $this->FormClose();
}

if ($phrase)
{
	if ($results = $this->FullTextSearch($phrase))
	{
	    if ($separator)
	    {
		$separator = htmlspecialchars($separator);
		if (!$paramPhrase)
		{
			echo '<p>R&eacute;sultat(s) de la recherche de "', htmlspecialchars($phrase), '"&nbsp;: ';
		}
		foreach ($results as $i => $page)
		{
			if ($i > 0) echo $separator;
			echo $this->ComposeLinkToPage($page['tag']);
		}
		if (!$paramPhrase)
		{
			echo '</p>', "\n";
		}
	    }
	    else
	    {
		echo	'<p><strong>R&eacute;sultat(s) de la recherche de "', htmlspecialchars($phrase), '"&nbsp;:</strong></p>', "\n",
			'<ol>', "\n";
		foreach ($results as $i => $page)
		{
			echo "<li>", $this->ComposeLinkToPage($page["tag"]), "</li>\n";
		}
		echo "</ol>\n";
	    }
	}
	else
	{
	    if (!$paramPhrase)
	    {
		echo "<p>Aucun r&eacute;sultat pour \"", htmlspecialchars($phrase), "\". :-(</p>";
	    }
	}
}
?>
