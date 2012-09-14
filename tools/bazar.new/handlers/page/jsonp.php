<?php
/*
jsonp.php

Copyright 2011  Florian Schmitt <florian@outils-reseaux.org>
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
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}



if (isset($_REQUEST['demand'])) {
	header('Content-type: application/json; charset=UTF-8');
	$output = '';
	switch ($_REQUEST['demand']) {
		//les pages wiki
		case "pages":
				/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
				 * Easy set variables
				 */
				
				/* Array of database columns which should be read and sent back to DataTables. Use a space where
				 * you want to insert a non-database field (for example a counter or static image)
				 */
				$aColumns = array( 'tag', 'time', 'owner' );
				
				/* Indexed column (used for fast and accurate table cardinality) */
				$sIndexColumn = "tag";
				
				/* DB table to use */
				$sTable = $this->config["table_prefix"]."pages";
				
				/* 
				 * Paging
				 */
				$sLimit = "";
				if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' )
				{
					$sLimit = "LIMIT ".mysql_real_escape_string( $_GET['iDisplayStart'] ).", ".
						mysql_real_escape_string( $_GET['iDisplayLength'] );
				}
				
				
				/*
				 * Ordering
				 */
				$sOrder = "";
				if ( isset( $_GET['iSortCol_0'] ) )
				{
					$sOrder = "ORDER BY  ";
					for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ )
					{
						if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" )
						{
							$sOrder .= $aColumns[ intval( $_GET['iSortCol_'.$i] ) ]."
								".mysql_real_escape_string( $_GET['sSortDir_'.$i] ) .", ";
						}
					}
					
					$sOrder = substr_replace( $sOrder, "", -2 );
					if ( $sOrder == "ORDER BY" )
					{
						$sOrder = "";
					}
				}
				
				
				/* 
				 * Filtering
				 * NOTE this does not match the built-in DataTables filtering which does it
				 * word by word on any field. It's possible to do here, but concerned about efficiency
				 * on very large tables, and MySQL's regex functionality is very limited
				 */
				$sWhere = "WHERE latest=\"Y\" AND comment_on=\"\"";
				if ( isset($_GET['sSearch']) && $_GET['sSearch'] != "" )
				{
					$sWhere .= " AND (";
					for ( $i=0 ; $i<count($aColumns) ; $i++ )
					{
						$sWhere .= $aColumns[$i]." LIKE '%".mysql_real_escape_string( $_GET['sSearch'] )."%' OR ";
					}
					$sWhere = substr_replace( $sWhere, "", -3 );
					$sWhere .= ')';
				}
				
				/* Individual column filtering */
				for ( $i=0 ; $i<count($aColumns) ; $i++ )
				{
					if ( isset($_GET['bSearchable_'.$i]) && $_GET['bSearchable_'.$i] == "true" && $_GET['sSearch_'.$i] != '' )
					{
						if ( $sWhere == "" )
						{
							$sWhere = "WHERE ";
						}
						else
						{
							$sWhere .= " AND ";
						}
						$sWhere .= $aColumns[$i]." LIKE '%".mysql_real_escape_string($_GET['sSearch_'.$i])."%' ";
					}
				}
				
				
				/*
				 * SQL queries
				 * Get data to display
				 */
				$sQuery = "
					SELECT SQL_CALC_FOUND_ROWS ".str_replace(" , ", " ", implode(", ", $aColumns))."
					FROM   $sTable
					$sWhere
					$sOrder
					$sLimit
				";
				$rResult = $this->query( $sQuery );
				
				/* Data set length after filtering */
				$sQuery = "
					SELECT FOUND_ROWS()
				";
				$rResultFilterTotal = $this->query( $sQuery );
				$aResultFilterTotal = mysql_fetch_array($rResultFilterTotal);
				$iFilteredTotal = $aResultFilterTotal[0];
				
				/* Total data set length */
				$sQuery = "
					SELECT COUNT(".$sIndexColumn.")
					FROM   $sTable
					WHERE latest=\"Y\" AND comment_on=\"\"
				";
				$rResultTotal = $this->query( $sQuery );
				$aResultTotal = mysql_fetch_array($rResultTotal);
				$iTotal = $aResultTotal[0];
				
				
				/*
				 * Output
				 */
				$output = array(
					"sEcho" => intval($_GET['sEcho']),
					"iTotalRecords" => $iTotal,
					"iTotalDisplayRecords" => $iFilteredTotal,
					"aaData" => array()
				);
				
				while ( $aRow = mysql_fetch_array( $rResult ) )
				{
					$row = array();
					for ( $i=0 ; $i<count($aColumns) ; $i++ )
					{
						if ( $aColumns[$i] == "version" )
						{
							/* Special output formatting for 'version' column */
							$row[] = ($aRow[ $aColumns[$i] ]=="0") ? '-' : $aRow[ $aColumns[$i] ];
						}
						else if ( $aColumns[$i] != ' ' )
						{
							/* General output */
							$row[] = $aRow[ $aColumns[$i] ];
						}
					}
					$output['aaData'][] = $row;
				}
				
				echo $_GET['callback'].'('.json_encode( $output ).');';

		    break;
	}
}

	
	

?>
