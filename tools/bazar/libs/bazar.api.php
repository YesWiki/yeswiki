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

use YesWiki\Bazar\Service\FicheManager;
use YesWiki\Bazar\Service\SemanticTransformer;

/**
 * Get all form or one form's informations
 *
 * @param string $form ID of the form
 *
 * @return string json
 */
function getForm($form = '')
{
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

function getFiche($args)
{
    if ($args) {
        if ($args[0]==='url') {
            $triples = $GLOBALS['wiki']->GetMatchingTriples(null, 'http://outils-reseaux.org/_vocabulary/sourceUrl', urldecode($args[1]));
            $resources = array_map(function ($triple) {
                return $GLOBALS['wiki']->href('', $triple['resource']);
            }, $triples);

            header("Content-type: application/json; charset=UTF-8");
            header("Access-Control-Allow-Origin: *");
            exit(json_encode($resources));
        } else {
            $semantic = strpos($_SERVER['HTTP_ACCEPT'], 'application/ld+json') !== false || $args[1] === 'json-ld';

            $data = $GLOBALS['wiki']->services->get(FicheManager::class)->search([ 'formsIds'=>$args[0] ]);

            // Put data inside LDP container
            if ($semantic) {
                $form = baz_valeurs_formulaire($args[0]);

                $data = [
                    '@context' => (array) json_decode($form['bn_sem_context']) ?: $form['bn_sem_context'],
                    '@id' => $GLOBALS['wiki']->href('fiche/' . $args[0], 'api'),
                    '@type' => [ 'ldp:Container', 'ldp:BasicContainer' ],
                    'dcterms:title' => $form['bn_label_nature'],
                    'ldp:contains' => array_map(function ($fiche) {
                        $resource = $GLOBALS['wiki']->services->get(SemanticTransformer::class)->convertToSemanticData($fiche['id_typeannonce'], $fiche, true);
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

function postFiche($args)
{
    if ($args) {
        $semantic = strpos($_SERVER['CONTENT_TYPE'], 'application/ld+json') !== false || $args[1] === 'json-ld';

        $_POST['antispam'] = 1;
        $fiche = $GLOBALS['wiki']->services->get(FicheManager::class)->create($args[0], $_POST, $semantic, $_SERVER['HTTP_SOURCE_URL']);

        if ($fiche) {
            http_response_code(201);

            if ($semantic) {
                // Standard LDP headers
                header('Link: <http://www.w3.org/ns/ldp#Resource>; rel="type"');
                header('Location: ' . $GLOBALS['wiki']->href('', $fiche['id_fiche']));
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
function documentationBazar()
{
    $output = '<h2>Bazar</h2>'."\n";

    $output .= '
    <p>
      <b><code>GET '.$GLOBALS['wiki']->href('', 'api/form').'</code></b><br />
      Retourne la liste de tous les formulaires Bazar.
    </p>';

    $output .= '
    <p>
      <b><code>GET '.$GLOBALS['wiki']->href('', 'api/form/{formId}').'</code></b><br />
      Retourne les informations sur le formulaire <code>formId</code>.
    </p>';

    $output .= '
    <p>
      <b><code>GET '.$GLOBALS['wiki']->href('', '{pageTag}').'</code></b><br />
      Si le header <code>Accept</code> est <code>application/json</code>, retourne la fiche au format JSON.<br />
      Si le header <code>Accept</code> est <code>application/ld+json</code>, retourne la fiche au format JSON-LD.<br />
    </p>';

    $output .= '
    <p>
      <b><code>PUT '.$GLOBALS['wiki']->href('', '{pageTag}').'</code></b><br />
      Si le header <code>Content-Type</code> est <code>application/json</code>, modifie la fiche selon le JSON fourni.<br />
      Si le header <code>Content-Type</code> est <code>application/ld+json</code>, modifie la fiche selon le JSON-LD fourni.<br />
    </p>';

    $output .= '
    <p>
      <b><code>DELETE '.$GLOBALS['wiki']->href('', '{pageTag}').'</code></b><br />
      Supprime la fiche Bazar.
    </p>';

    $output .= '
    <p>
      <b><code>GET '.$GLOBALS['wiki']->href('', 'api/fiche/{formId}').'</code></b><br />
      Obtenir la liste de toutes les fiches du formulaire <code>formId</code><br />
      Si le header <code>Accept</code> est <code>application/ld+json</code>, le JSON retourné sera au format sémantique (container LDP)
    </p>';

    $output .= '
    <p>
      <b><code>GET '.$GLOBALS['wiki']->href('', 'api/fiche/{formId}/json-ld').'</code></b><br />
      Obtenir la liste de toutes les fiches du formulaire <code>formId</code> au format sémantique (container LDP)<br />
    </p>';

    $output .= '
    <p>
      <b><code>POST '.$GLOBALS['wiki']->href('', 'api/fiche/{formId}').'</code></b><br />
      Créer une nouvelle fiche en utilisant le formulaire <code>formId</code><br />
      Si le header <code>Content-Type</code> est <code>application/ld+json</code>, un JSON sémantique est attendu.
    </p>';

    $output .= '
    <p>
      <b><code>POST '.$GLOBALS['wiki']->href('', 'api/fiche/{formId}/json-ld').'</code></b><br />
      Créer une nouvelle fiche de type <code>formId</code> au format sémantique<br />
    </p>';

    $output .= '
    <p>
      <b><code>GET '.$GLOBALS['wiki']->href('', 'api/fiche/url/{sourceUrl}').'</code></b><br />
      Retourne l\'URL de la page Wiki synchronisée avec <code>sourceUrl</code><br />
    </p>';

    return $output;
}
