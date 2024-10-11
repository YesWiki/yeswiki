<?php

// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

use YesWiki\Core\Service\CommentService;

$commentService = $this->services->get(CommentService::class);
$result = $commentService->addCommentIfAuthorized($_POST);

if (!empty($result['error'])) {
    $this->SetMessage($result['error']);
} elseif (!empty($result['success'])) {
    $this->SetMessage($result['success']);
}
// redirect to page
$this->redirect($this->href('', '', '#post-comment'));
