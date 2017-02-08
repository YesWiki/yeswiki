<?php
/*
textsearch.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002  Patrick PAUL
Copyright 2004  Jean Christophe ANDRé
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

// label é afficher devant la zone de saisie
$label = $this->GetParameter('label', _t('WHAT_YOU_SEARCH').'&nbsp;: ');
// largeur de la zone de saisie
$size = $this->GetParameter('size', '40');
// texte du bouton
$button = $this->GetParameter('button', _t('SEARCH'));
// texte é chercher
$phrase = $this->GetParameter('phrase', false);
// séparateur entre les éléments trouvés
$separator = $this->GetParameter('separator', false);

// se souvenir si c'était :
// -- un paramétre de l'action : {{textsearch phrase="Test"}}
// -- ou du CGI http://example.org/wakka.php?wiki=RechercheTexte&phrase=Test
//
// récupérer le paramétre de l'action
$paramPhrase = $phrase;
// ou, le cas échéant, récupérer le paramétre du CGI
if (!$phrase && isset($_GET['phrase'])) $phrase = $_GET['phrase'];

// s'il y a un paramétre d'action "phrase", on affiche uniquement le résultat
// dans le cas contraire, présenter une zone de saisie
if (!$paramPhrase)
{
	echo $this->FormOpen('', '', 'get');
	echo '<div class="input-prepend input-append input-group input-group-lg">
			<span class="add-on input-group-addon"><i class="glyphicon glyphicon-search icon-search"></i></span>
      <input name="phrase" type="text" class="form-control" placeholder="'.(($label) ? $label : '').'" size="', $size, '" value="', htmlspecialchars($phrase, ENT_COMPAT, YW_CHARSET), '" >
      <span class="input-group-btn">
        <input type="submit" class="btn btn-primary btn-lg" value="', $button, '" />
      </span>
    </div><!-- /input-group --><br>';
	echo "\n", $this->FormClose();
}

if ($phrase)
{
	// on cherche sur le mot avec entités html, le mot encodé par le wiki, ou le mot encodé par bazar en json
	$search = $phrase.','.utf8_decode($phrase).','.substr(json_encode($phrase),1,-1);
	$results = $this->FullTextSearch($search);
	if ($results)
	{
	    if ($separator)
	    {
		$separator = htmlspecialchars($separator, ENT_COMPAT, YW_CHARSET);
		if (!$paramPhrase)
		{
			echo '<p>'._t('SEARCH_RESULT_OF').' "', htmlspecialchars($phrase, ENT_COMPAT, YW_CHARSET), '"&nbsp;: ';
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
		echo	'<p><strong>'._t('SEARCH_RESULT_OF').' "', htmlspecialchars($phrase, ENT_COMPAT, YW_CHARSET), '"&nbsp;:</strong></p>', "\n",
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
		echo "<div class=\"alert alert-info\">"._t('NO_RESULT_FOR')." \"", htmlspecialchars($phrase, ENT_COMPAT, YW_CHARSET), "\". :-(</div>\n";
	    }
	}
}
?>
