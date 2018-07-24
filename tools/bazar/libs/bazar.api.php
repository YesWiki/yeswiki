<?php
/**
 * Function library for bazar
 *
 * @category Wiki
 * @package  YesWiki
 * @author   Florian Schmitt <mrflos@lilo.org>
 * @license  GNU/GPL version 3
 * @link     https://yeswiki.net
 */

/**
 * Get all form or one form's informations
 *
 * @param string $form ID of the form
 * 
 * @return string json
 */
function getForm($form = '')
{
    global $wiki;
    if ($wiki->UserIsAdmin()) {
        $formval = baz_valeurs_formulaire($form);
        // si un seul formulaire, on cree un tableau à une entrée
        if (!empty($form)) {
            $formval = array($formval['bn_id_nature'] => $formval);
        }
        if (!function_exists('sortByLabel')) {
            /**
             * Sort form arrays by label
             *
             * @param array $a first form array
             * @param array $b second form array
             * 
             * @return void
             */
            function sortByLabel($a, $b)
            {
                return $a['bn_label_nature'] < $b['bn_label_nature'];
            }
        }
        usort($formval, 'sortByLabel');      
        echo json_encode($formval);
    } else {
        return json_encode(
            array('error' => array('Unauthorized (admins only)'))
        );
    }
}

/**
 * Display bazar api documentation
 *
 * @return void
 */
function documentationBazar()
{
    global $wiki;
    $output = '<h2>Extension bazar</h2>'."\n";
    $link = $wiki->href('', 'api/form');
    $output .= 'GET <code><a href="'.$link.'">'.$link.'</a></code><br />';
    return $output;
}