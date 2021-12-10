<?php

use YesWiki\Core\Service\LinkTracker;
use YesWiki\Core\Service\PageManager;
use YesWiki\Security\Controller\SecurityController;

/*
$Id: edit.php 851 2007-08-29 14:54:07Z lordfarquaad $
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Charles NEPOTE
Copyright 2002, 2003 Patrick PAUL
Copyright 2003  Eric FELDSTEIN
Copyright 2004  Jean Christophe ANDRé
Copyright 2005  Didier Loiseau
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
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

// on initialise la sortie:
$output = '';

$isWikiHibernated = $this->services->get(SecurityController::class)->isWikiHibernated();

if ($this->HasAccess('write') && $this->HasAccess('read') && !$isWikiHibernated) {
    if (!empty($_POST['submit'])) {
        $submit = $_POST['submit'];
    } else {
        $submit = false;
    }

    // fetch fields
    if (empty($_POST['previous'])) {
        $previous = isset($this->page['id']) ? $this->page['id'] : null;
    } else {
        $previous = $_POST['previous'];
    }
    if (empty($_POST['body'])) {
        $body = isset($this->page['body']) ? $this->page['body'] : null;
    } else {
        $body = $_POST['body'];
    }

    switch ($submit) {
        case 'Apercu':
            $temp = $this->SetInclusions(); // a priori, éa ne sert é rien, mais on ne sait jamais...
            $this->RegisterInclusion($this->GetPageTag()); // on simule totalement un affichage normal
            $output .=
              "<div class=\"page_preview\">\n".
              "<div class=\"prev_alert\"><strong>Aper&ccedil;u</strong></div>\n".
              $this->Format($body)."\n\n".
              $this->FormOpen(testUrlInIframe() ? 'editiframe' : 'edit').
              "<input type=\"hidden\" name=\"previous\" value=\"$previous\" />\n".
              '<input type="hidden" name="body" value="'.htmlspecialchars($body, ENT_COMPAT, YW_CHARSET)."\" />\n".
              "<br />\n".
              "<input name=\"submit\" type=\"submit\" value=\"Sauver\" accesskey=\"s\" />\n".
              "<input name=\"submit\" type=\"submit\" value=\"R&eacute;&eacute;diter\" accesskey=\"p\" />\n".
              "<input type=\"button\" value=\"Annulation\" onclick=\"document.location='".addslashes($this->href(testUrlInIframe()))."';\" />\n".
              $this->FormClose()."\n"."</div>\n";
            $this->SetInclusions($temp);
            break;

        // pour les navigateurs n'interprétant pas le javascript
        case 'Annulation':
            $this->Redirect($this->Href(testUrlInIframe()));
            exit; // sécurité

        // only if saving:
        case 'Sauver':
            // check for overwriting
            if ($this->page && $this->page['id'] != $_POST['previous']) {
                $error = 'ALERTE : '.
                "Cette page a &eacute;t&eacute; modifi&eacute;e par quelqu'un d'autre pendant que vous l'&eacute;ditiez.<br />\n".
                "Veuillez copier vos changements et r&eacute;&eacute;diter cette page.\n";
            } else { // store
                $body = str_replace("\r", '', $body);
                // teste si la nouvelle page est differente de la précédente
                if (isset($this->page['body']) && rtrim($body) == rtrim($this->page['body'])) {
                    $this->SetMessage('Cette page n\'a pas &eacute;t&eacute; enregistr&eacute;e car elle n\'a subi aucune modification.');
                    $this->Redirect($this->href(testUrlInIframe()));
                } else {
                    // l'encodage de la base est en iso-8859-1, voir s'il faut convertir
                    $body = _convert($body, YW_CHARSET, true);

                    // add page (revisions)
                    $this->SavePage($this->tag, $body);

                    // now we render it internally so we can write the updated link table.
                    $page = $this->services->get(PageManager::class)->getOne($this->tag);
                    $this->services->get(LinkTracker::class)->registerLinks($page,false,false);

                    // forward
                    if ($this->page['comment_on']) {
                        $this->Redirect($this->href(testUrlInIframe(), $this->page['comment_on']).'#'.$this->tag);
                    } else {
                        $this->Redirect($this->href(testUrlInIframe()));
                    }
                }

                // sécurité
                exit;
            }
        // NB.: en cas d'erreur on arrive ici, donc default sera exécuté...
        // no break
        default:
            // display form
            if (isset($error)) {
                $output .= "<div class=\"error\">$error</div>\n";
            }

            // append a comment?
            if (isset($_REQUEST['appendcomment'])) {
                $body = trim($body)."\n\n----\n\n-- ".$this->GetUserName().' ('.strftime('%c').')';
            }

            $output .=
              $this->FormOpen(testUrlInIframe() ? 'editiframe' : 'edit').
              "<input type=\"hidden\" name=\"previous\" value=\"$previous\" />\n".
              "<textarea id=\"body\" name=\"body\" style='display: none'>" .
                htmlspecialchars($body, ENT_COMPAT, YW_CHARSET).
              "</textarea>".
              "<script type=\"text/javascript\">\n".
              "document.getElementById(\"body\").onkeydown=fKeyDown;\n".
              "</script>\n".
              ($this->config['preview_before_save'] ? '' : "<input name=\"submit\" type=\"submit\" value=\"Sauver\" accesskey=\"s\" />\n").
              "<input name=\"submit\" type=\"submit\" value=\"Aper&ccedil;u\" accesskey=\"p\" />\n".
              "<input type=\"button\" value=\"Annulation\" onclick=\"document.location='".addslashes($this->href(testUrlInIframe()))."';\" />\n".
              $this->FormClose();
    } // switch
} else {
    $output .= "<i>Vous n'avez pas acc&egrave;s en &eacute;criture &agrave; cette page !</i>\n";
    if ($isWikiHibernated) {
        $output .= $this->services->get(SecurityController::class)->getMessageWhenHibernated();
    }
}

// Header
if (!testUrlInIframe()) {
    echo $this->Header();
}

// Main Page
echo '<div class="page">'."\n".$output."\n".'<hr class="hr_clear" />'."\n".'</div>'."\n";

// Popups for aceditor toolbar
include 'tools/aceditor/actions/actions_builder.php';

// Footer
if (!testUrlInIframe()) {
    echo $this->Footer();
}
