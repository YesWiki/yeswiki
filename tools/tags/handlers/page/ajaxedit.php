<?php

use YesWiki\Core\Service\LinkTracker;
use YesWiki\Core\Service\PageManager;

// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}
//on ne fait quelque chose uniquement dans le cas d'une requete jsonp
if (isset($_GET['jsonp_callback'])) {
    // on initialise la sortie:
    header('Content-type:application/json');
    $output = '';

    if ($this->HasAccess('write') && $this->HasAccess('read')) {
        if (!empty($_GET['submit'])) {
            $submit = $_GET['submit'];
        } else {
            $submit = false;
        }

        // fetch fields
        if (empty($_GET['previous'])) {
            $previous = $this->page['id'];
        } else {
            $previous = $_GET['previous'];
        }
        if (empty($_GET['body'])) {
            $body = $this->page['body'];
        } else {
            $body = $_GET['body'];
        }

        switch ($submit) {
            case 'savecomment':
                // check for overwriting
                if ($this->page && $this->page['id'] != $_GET['previous']) {
                    $error = _t('TAGS_ALERT_PAGE_ALREADY_MODIFIED');
                } else { // store
                    $body = str_replace("\r", '', $body);

                    // teste si la nouvelle page est differente de la précédente
                    if (rtrim($body) == rtrim($this->page['body'])) {
                        echo $_GET['jsonp_callback'] . '(' . json_encode(['nochange' => '1']) . ')';
                    } else { // sécurité
                        // add page (revisions)
                        $this->SavePage($this->tag, $body);

                        // now we render it internally so we can write the updated link table.
                        $page = $this->services->get(PageManager::class)->getOne($this->tag);
                        $this->services->get(LinkTracker::class)->registerLinks($page, false, false);

                        // on recupere le commentzire bien formatte
                        $comment = $this->LoadPage($this->tag);

                        $valcomment['commentaires'][0]['tag'] = $comment['tag'];
                        $valcomment['commentaires'][0]['body'] = $this->Format($comment['body']);
                        $valcomment['commentaires'][0]['infos'] = $this->Format($comment['user']) . ', ' . date(_t('TAGS_DATE_FORMAT'), strtotime($comment['time']));
                        $valcomment['commentaires'][0]['hasrighttoaddcomment'] = $this->HasAccess('comment', $_GET['initialpage']);
                        $valcomment['commentaires'][0]['hasrighttomodifycomment'] = $this->HasAccess('write', $comment['tag']) || $this->UserIsOwner($comment['tag']) || $this->UserIsAdmin();
                        $valcomment['commentaires'][0]['hasrighttodeletecomment'] = $this->UserIsOwner($comment['tag']) || $this->UserIsAdmin();
                        $valcomment['commentaires'][0]['replies'] = '';

                        $content = $this->render('@tags/comment_list.tpl.html', $valcomment);
                        echo $_GET['jsonp_callback'] . '(' . json_encode(['html' => mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1')]) . ')';
                    }

                    // sécurité
                    $this->exit();
                }
                // NB.: en cas d'erreur on arrive ici, donc default sera exécuté...
                // no break
            default:
                // display form
                if (isset($error)) {
                    $output .= "<div class=\"alert alert-danger\">$error</div>\n";
                }

                // append a comment?
                if (isset($_REQUEST['appendcomment'])) {
                    $body = trim($body);
                }

                $output .= '<form class="form-modify-comment well well-small" method="post" action="' . $this->href('ajaxedit') . "\">\n" .
                    "<input type=\"hidden\" name=\"previous\" value=\"$previous\" />\n" .
                    '<textarea name="body" required="required" rows="3" placeholder="' . _t('TAGS_WRITE_YOUR_COMMENT_HERE') . "\" class=\"comment-response\">\n" .
                    htmlspecialchars($body, ENT_COMPAT, YW_CHARSET) .
                    "</textarea>\n" .
                    ($this->config['preview_before_save'] ? '' : '<input name="submit" type="button" class="btn btn-sm btn-primary btn-modify" value="' . _t('TAGS_MODIFY') . "\" />\n") .
                    '<input type="button" value="' . _t('TAGS_CANCEL') . "\" class=\"btn btn-sm btn-cancel-modify\" />\n" .
                    "</form>\n";
        } // switch
    } else {
        $output .= '<div class="alert alert-danger">' . _t('TAGS_NO_WRITE_ACCESS') . "</div>\n";
    }
    $response = $_GET['jsonp_callback'] . '(' . json_encode(['html' => mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1')]) . ')';
    echo $response;
}
