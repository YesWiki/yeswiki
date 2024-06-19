<?php

// Verification de securite
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}
// on ne fait quelque chose uniquement dans le cas d'une requete jsonp
if (isset($_GET['jsonp_callback'])) {
    // on initialise la sortie:
    header('Content-type:application/json');

    if ($this->page && $this->HasAccess('comment', $_POST['initialpage']) && isset($_POST['antispam']) && $_POST['antispam'] == 1) {
        // find number
        $sql = 'SELECT MAX(SUBSTRING(tag, 8) + 0) AS comment_id'
            . ' FROM ' . $this->GetConfigValue('table_prefix') . 'pages'
            . ' WHERE comment_on != ""';
        if ($lastComment = $this->LoadSingle($sql)) {
            $num = $lastComment['comment_id'] + 1;
        } else {
            $num = '1';
        }

        $body = mb_convert_encoding(trim($_POST['body']), 'ISO-8859-1', 'UTF-8');
        if ($body) {
            // store new comment
            $wakkaname = 'Comment' . $num;
            $this->SavePage($wakkaname, $body, $this->tag, true);

            $comment = $this->LoadPage($wakkaname);
            $valcomment['commentaires'][0]['tag'] = $comment['tag'];
            $valcomment['commentaires'][0]['body'] = $this->Format($comment['body']);
            $valcomment['commentaires'][0]['infos'] = $this->Format($comment['user']) . ', ' . date(_t('TAGS_DATE_FORMAT'), strtotime($comment['time']));
            $valcomment['commentaires'][0]['hasrighttoaddcomment'] = $this->HasAccess('comment', $_POST['initialpage']);
            $valcomment['commentaires'][0]['hasrighttomodifycomment'] = $this->HasAccess('write', $comment['tag']) || $this->UserIsOwner($comment['tag']) || $this->UserIsAdmin();
            $valcomment['commentaires'][0]['hasrighttodeletecomment'] = $this->UserIsOwner($comment['tag']) || $this->UserIsAdmin();
            $valcomment['commentaires'][0]['replies'] = '';
            $squelcomment->set($valcomment);
            $content = $this->render('@tags/comment_list.tpl.html', $valcomment);

            echo $_GET['jsonp_callback'] . '(' . json_encode(['html' => mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1')]) . ')';
        }
    }
}
