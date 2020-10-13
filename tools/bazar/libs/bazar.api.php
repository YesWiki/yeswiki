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
function getForm($form = '') {
    if ($GLOBALS['wiki']->UserIsAdmin()) {
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

function getFiche($args) {
    if( $args ) {
        if( $args[0]==='url' ) {
            $triples = $GLOBALS['wiki']->GetMatchingTriples(null, 'http://outils-reseaux.org/_vocabulary/sourceUrl', urldecode($args[1]) );
            $resources = array_map(function($triple) {
                return $GLOBALS['wiki']->href('', $triple['resource']);
            }, $triples);

            header("Content-type: application/json; charset=UTF-8");
            header("Access-Control-Allow-Origin: *");
            exit(json_encode($resources));
        } else {
            $semantic = strpos($_SERVER['HTTP_ACCEPT'], 'application/ld+json') !== false || $args[1] === 'json-ld';

            $data = $GLOBALS['bazarFiche']->search([ 'formsIds'=>$args[0] ]);

            // Put data inside LDP container
            if( $semantic ) {
                $form = baz_valeurs_formulaire($args[0]);

                $data = [
                    '@context' => (array) json_decode($form['bn_sem_context']) ?: $form['bn_sem_context'],
                    '@id' => $GLOBALS['wiki']->href('fiche/' . $args[0], 'api'),
                    '@type' => [ 'ldp:Container', 'ldp:BasicContainer' ],
                    'dcterms:title' => $form['bn_label_nature'],
                    'ldp:contains' => array_map(function($fiche) {
                        $resource = $GLOBALS['bazarFiche']->convertToSemanticData($fiche['id_typeannonce'], $fiche, true);
                        unset($resource['@context']);
                        return $resource;
                    }, array_values($data))
                ];
            }

            header("Content-type: ".($semantic ? 'application/ld+json' : 'application/json')."; charset=UTF-8");
            header("Access-Control-Allow-Origin: *");
            exit(json_encode($data));
        }
    } else {
        http_response_code(404);
        exit(json_encode(['error' => array('Missing fiche ID')]));
    }
}

function postFiche($args) {
    if( $args ) {
        $semantic = strpos($_SERVER['CONTENT_TYPE'], 'application/ld+json') !== false || $args[1] === 'json-ld';

        $_POST['antispam'] = 1;
        $fiche = $GLOBALS['bazarFiche']->create($args[0], $_POST, $semantic, $_SERVER['HTTP_SOURCE_URL']);

        if( $fiche ) {
            http_response_code(201);

            if( $semantic ) {
                // Standard LDP headers
                header('Link: <http://www.w3.org/ns/ldp#Resource>; rel="type"');
                header('Location: ' . $GLOBALS['wiki']->href('', $fiche['id_fiche']) );
                header('Content-Length: 0');
                exit();
            } else {
                exit(json_encode(['success' => $GLOBALS['wiki']->href('', $fiche['id_fiche'])]));
            }
        } else {
            http_response_code(400);
            exit(json_encode(['error' => 'Invalid data']));
        }
    } else {
        http_response_code(404);
        exit(json_encode(['error' => 'Missing form ID']));
    }
}

/**
 * Display bazar api documentation
 *
 * @return void
 */
function documentationBazar() {
    $output = '<h2>Extension bazar</h2>'."\n";

    $form = $GLOBALS['wiki']->href('', 'api/form');
    $output .= 'GET <code><a href="'.$form.'">'.$form.'</a></code><br />';

    $form = $GLOBALS['wiki']->href('', 'api/form/{formId}');
    $output .= 'GET <code><a href="'.$form.'">'.$form.'</a></code><br />';

    $fiche = $GLOBALS['wiki']->href('', 'api/fiche/url/{sourceUrl}');
    $output .= 'GET <code><a href="'.$fiche.'">'.$fiche.'</a></code><br />';

    $fiche = $GLOBALS['wiki']->href('', 'api/fiche/{formId}');
    $output .= 'GET <code><a href="'.$fiche.'">'.$fiche.'</a></code><br />';

    $fiche = $GLOBALS['wiki']->href('', 'api/fiche/{formId}/json-ld');
    $output .= 'GET <code><a href="'.$fiche.'">'.$fiche.'</a></code><br />';

    $fiche = $GLOBALS['wiki']->href('', 'api/fiche/{formId}');
    $output .= 'POST <code><a href="'.$fiche.'">'.$fiche.'</a></code><br />';

    $fiche = $GLOBALS['wiki']->href('', 'api/fiche/{formId}/json-ld');
    $output .= 'POST <code><a href="'.$fiche.'">'.$fiche.'</a></code><br />';

    return $output;
}
