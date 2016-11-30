<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                        |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2012 Outils-Réseaux (accueil@outils-reseaux.org)                                       |
// +------------------------------------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or                                        |
// | modify it under the terms of the GNU Lesser General Public                                           |
// | License as published by the Free Software Foundation; either                                         |
// | version 2.1 of the License, or (at your option) any later version.                                   |
// |                                                                                                      |
// | This library is distributed in the hope that it will be useful,                                      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
// | Lesser General Public License for more details.                                                      |
// |                                                                                                      |
// | You should have received a copy of the GNU Lesser General Public                                     |
// | License along with this library; if not, write to the Free Software                                  |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
// CVS : $Id: savemetadatas.php,v 1.10 2010/03/04 14:19:03 mrflos Exp $
/**
*
* Handler AJAX pour sauver les meta-données
*
*
*@package templates
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     Outils-Réseaux 2012
*@version       $Revision: 0.1 $ $Date: 2010/03/04 14:19:03 $
*/

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

header('Content-type: application/json; charset=UTF-8');

// on teste si on a le droit d'accés aux meta-données
if ($this->HasAccess("write") && $this->HasAccess("read")) {
	
	// on ajoute les nouvelles meta-données si quelquechose est passé dans le POST meta
	if (isset($_POST['metadatas'])) {
		echo json_encode(array('result' => $this->SaveMetaDatas($this->GetPageTag(), $_POST['metadatas'])));
	} else {
		echo json_encode(array('result' => _t('TEMPLATE_ERROR_NO_DATA')));
	}
} else {
	echo json_encode(array('result' => _t('TEMPLATE_ERROR_NO_ACCESS')));
}
?>
