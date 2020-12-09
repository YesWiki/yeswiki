<?php

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
