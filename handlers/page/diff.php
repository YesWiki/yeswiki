<?php
/*
diff.php

Copyright (C) 1992 Free Software Foundation, Inc. Francois Pinard <pinard@iro.umontreal.ca>.
Copyright (C) 2000, 2001 Geoffrey T. Dairiki <dairiki@dairiki.org>
Copyright 2002,2003,2004  David DELON
Copyright 2002  Patrick PAUL
Copyright 2003  Eric FELDSTEIN
Copyright 2004 Charles NEPOTE
Copyright 2005 Didier Loiseau

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

original diff.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
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

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}

require_once 'includes/diff/side.class.php';
require_once 'includes/diff/diff.class.php';
require_once 'includes/diff/diffformatter.class.php';

ob_start();
?>
<div class="page">
<?php

if ($this->HasAccess("read"))
{

// If asked, call original diff

	if (!empty($_REQUEST["fastdiff"])) {

		/* NOTE: This is a really cheap way to do it. I think it may be more intelligent to write the two pages to temporary files and run /usr/bin/diff over them. Then again, maybe not.        */
		// load pages
		  $pageA = $this->LoadPageById($_REQUEST["a"]);
		  $pageB = $this->LoadPageById($_REQUEST["b"]);

		// prepare bodies
		  $bodyA = explode("\n", $pageA["body"]);
		  $bodyB = explode("\n", $pageB["body"]);

		  $added = array_diff($bodyA, $bodyB);
		  $deleted = array_diff($bodyB, $bodyA);
		  if (!isset($output)) $output = '';

		  $output .= "<b>Comparaison de <a href=\"".$this->href("", "", "time=".urlencode($pageA["time"]))."\">".$pageA["time"]."</a> &agrave; <a href=\"".$this->href("", "", "time=".urlencode($pageB["time"]))."\">".$pageB["time"]."</a></b><br />\n";

		  $this->RegisterInclusion($this->GetPageTag());
		  if ($added)
		  {
			// remove blank lines
			$output .= "<br />\n<b>Ajouts:</b><br />\n";
			$output .= "<div class=\"additions\">".$this->Format(implode("\n", $added))."</div>";
		  }

		  if ($deleted)
		  {
			$output .= "<br />\n<b>Suppressions:</b><br />\n";
			$output .= "<div class=\"deletions\">".$this->Format(implode("\n", $deleted))."</div>";
		  }
		  $this->UnregisterLastInclusion();

		  if (!$added && !$deleted)
		  {
			$output .= "<br />\nPas de diff&eacute;rences.";
		  }
		  echo _convert($output, 'ISO-8859-15');

	}

	else {

	// load pages

		$pageA = $this->LoadPageById($_REQUEST["b"]);
		$pageB = $this->LoadPageById($_REQUEST["a"]);

		// extract text from bodies
		$textA = _convert($pageA["body"], "ISO-8859-15");
		$textB = _convert($pageB["body"], "ISO-8859-15");

		$sideA = new Side($textA);
		$sideB = new Side($textB);

		$bodyA='';
		$sideA->split_file_into_words($bodyA);

		$bodyB='';
		$sideB->split_file_into_words($bodyB);

		// diff on these two file
		$diff = new Diff(explode("\n",$bodyA),explode("\n",$bodyB));

		// format output
		$fmt = new DiffFormatter();

		$sideO = new Side($fmt->format($diff));

		$resync_left=0;
		$resync_right=0;

		$count_total_right=$sideB->getposition() ;

		$sideA->init();
		$sideB->init();

		$output='';

		  while (1) {

		      $sideO->skip_line();
		      if ($sideO->isend()) {
			  break;
		      }

		      if ($sideO->decode_directive_line()) {
			$argument=$sideO->getargument();
			$letter=$sideO->getdirective();
		      switch ($letter) {
			    case 'a':
			      $resync_left = $argument[0];
			      $resync_right = $argument[2] - 1;
			      break;

			    case 'd':
			      $resync_left = $argument[0] - 1;
			      $resync_right = $argument[2];
			      break;

			    case 'c':
			      $resync_left = $argument[0] - 1;
			      $resync_right = $argument[2] - 1;
			      break;

			    }

			    $sideA->skip_until_ordinal($resync_left);
			    $sideB->copy_until_ordinal($resync_right,$output);

	// deleted word

			    if (($letter=='d') || ($letter=='c')) {
			      $sideA->copy_whitespace($output);
			      $output .="@@";
			      $sideA->copy_word($output);
			      $sideA->copy_until_ordinal($argument[1],$output);
			      $output .="@@";
			    }

	// inserted word
			    if ($letter == 'a' || $letter == 'c') {
				$sideB->copy_whitespace($output);
				$output .="££";
				$sideB->copy_word($output);
				$sideB->copy_until_ordinal($argument[3],$output);
				$output .="££";
			    }

		  }

		}

		  $sideB->copy_until_ordinal($count_total_right,$output);
		  $sideB->copy_whitespace($output);
		  $out=$this->Format($output);
		  echo _convert($out, 'ISO-8859-15');

	}

}
else{
	echo "<i>Vous n'&ecirc;tes pas autoris&eacute; &agrave; lire cette page.</i>" ;
}

?>
</div>
<?php

$content = ob_get_clean();
echo $this->Header();
echo $content;
echo $this->Footer();
