<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                        |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2012 Florian Schmitt <florian@outils-reseaux.org>                                      |
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
//
/**
*
* Liste de toutes les pages Ebook
*
*
*@package tags
*
*@author        Florian Schmitt <florian@outils-reseaux.org>
*
*@copyright     Outils-Reseaux 2012
*@version       $Revision: 0.1 $ $Date: 2010/03/04 14:19:03 $
*/

if (!defined("WIKINI_VERSION")) {
    die ("acc&egrave;s direct interdit");
}


$ebookpagenamestart = $this->getParameter('ebookpagenamestart');
if (empty($ebookpagenamestart)) $ebookpagenamestart = 'Ebook';

$output = '';

// recuperation des pages wikis
$sql = 'SELECT DISTINCT resource FROM '.$this->GetConfigValue('table_prefix').'triples';
$sql .= ' WHERE property="http://outils-reseaux.org/_vocabulary/metadata"
			AND value LIKE "%ebook-title%"
			AND resource LIKE "'.$ebookpagenamestart.'%" ';
$sql .= ' ORDER BY resource ASC';

$pages = $this->LoadAll($sql);
if (count($pages) > 0) {
	$output .= '<ul class="media-list">'."\n";
	foreach($pages as $page) {
		$metas = $this->GetMetadatas($page['resource']);
		$output .= '<li class="media">
		<a href="'.$this->href('',$page['resource']).'" class="pull-left">
			<img src="'.$metas['ebook-cover-image'].'" alt="cover" class="media-object" width="128" />
		</a>
		<div class="media-body">'."\n";
		if ($this->UserIsAdmin()) $output .= '<a class="btn btn-danger btn-error pull-right" href="'.$this->href('deletepage',$page['resource']).'"><i class="glyphicon glyphicon-trash glyphicon glyphicon-white"></i>&nbsp;'._t('TAGS_DELETE').'</a>';
		$output .= '<h4 class="media-heading"><a href="'.$this->href('',$page['resource']).'">'.$metas['ebook-title'].'</a></h4>
			<strong>'.$metas['ebook-author'].'</strong><br />'.$metas['ebook-description'].'<br /><br />';
		$output .= '<strong><i class="glyphicon glyphicon-download"></i>&nbsp;'._t('TAGS_DOWNLOAD').' </strong><a class="btn btn-primary" href="'.$this->href('pdf',$page['resource']).'"><i class="glyphicon glyphicon-book glyphicon glyphicon-white"></i>&nbsp;'._t('TAGS_DOWNLOAD_PDF').'</a> <!-- epub download link for '.$page['resource'].' -->
			<br /><br />
		</div>
		</li>'."\n";
	}
	$output .= '</ul>'."\n";
}
else $output .= '<div class="alert alert-info">'._t('TAGS_NO_EBOOK_FOUND').'</div>';

echo $output."\n";
