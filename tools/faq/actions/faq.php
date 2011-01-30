<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5.1                                                                                      |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 1999-2006 outils-reseaux.org                                                            |
// +------------------------------------------------------------------------------------------------------+
// | This file is part of wkfaq.                                                                     |
// |                                                                                                      |
// | Foobar is free software; you can redistribute it and/or modify                                       |
// | it under the terms of the GNU General Public License as published by                                 |
// | the Free Software Foundation; either version 2 of the License, or                                    |
// | (at your option) any later version.                                                                  |
// |                                                                                                      |
// | Foobar is distributed in the hope that it will be useful,                                            |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                                        |
// | GNU General Public License for more details.                                                         |
// |                                                                                                      |
// | You should have received a copy of the GNU General Public License                                    |
// | along with Foobar; if not, write to the Free Software                                                |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
// CVS : $Id: faq.php,v 1.1 2010-08-11 17:52:49 mrflos Exp $
/**
* faq.php
*
* Description :
*
*@package wkfaq
//Auteur original :
*@author        Florian SCHMITT <florian@outils-reseaux.org>
//Autres auteurs :
*@author        Aucun
*@copyright     outils-reseaux.org 2010
*@version       $Revision: 1.1 $ $Date: 2010-08-11 17:52:49 $
// +------------------------------------------------------------------------------------------------------+
*/
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

//recuperation des parametres
$url = $this->GetParameter('url');
if (empty($url)) {die('Action faq : parametre url obligatoire');}

echo '<h1>Foire aux questions</h1>
<a class="buttonfaq expand" href="#">Voir toutes les réponses</a>
<div id="faqSection">
<!-- The FAQs are inserted here -->
</div>
<script type="text/javascript">
$(document).ready(function(){
	
	// The published URL of your Google Docs spreadsheet as CSV:
	var csvURL = \''.$url.'\';
	
	// The YQL address:
	var yqlURL =	"http://query.yahooapis.com/v1/public/yql?q="+
					"select%20*%20from%20csv%20where%20url%3D\'"+encodeURIComponent(csvURL)+
					"\'%20and%20columns%3D\'question%2Canswer\'&format=json&callback=?";
	
	$.getJSON(yqlURL,function(msg){
		
		var dl = $(\'<dl>\');
		
		// Looping through all the entries in the CSV file:
		$.each(msg.query.results.row,function(){
			
			// Sometimes the entries are surrounded by double quotes. This is why 
			// we strip them first with the replace method:
			
			var answer = this.answer.replace(/""/g,\'"\').replace(/^"|"$/g,\'\');
			var question = this.question.replace(/""/g,\'"\').replace(/^"|"$/g,\'\');
			
			// Formatting the FAQ as a definition list: dt for the question
			// and a dd for the answer.
			
			dl.append(\'<dt><span class="icon"></span>\'+question+\'</dt><dd>\'+answer+\'</dd>\');
		});


		// Appending the definition list:
		$(\'#faqSection\').append(dl);
		
		$(\'dt\').live(\'click\',function(){
			var dd = $(this).next();
			
			// If the title is clicked and the dd is not currently animated,
			// start an animation with the slideToggle() method.
			
			if(!dd.is(\':animated\')){
				dd.slideToggle();
				$(this).toggleClass(\'opened\');
			}
			
		});
		
		$(\'a.buttonfaq\').click(function(){
			
			// To expand/collapse all of the FAQs simultaneously,
			// just trigger the click event on the DTs
			
			if($(this).hasClass(\'collapse\')){
				$(this).text(\'Voir toutes les réponses\');
				$(\'dt.opened\').click();
			}
			else {
				$(this).text(\'Cacher toutes les réponses\');
				$(\'dt:not(.opened)\').click();
			}
			
			$(this).toggleClass(\'expand collapse\');
			
			return false;
		});
		
	});
});
</script>
';
?>
