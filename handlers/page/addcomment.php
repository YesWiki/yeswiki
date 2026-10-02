<?php

use YesWiki\Core\Service\BotGuard;
use YesWiki\Core\Service\CommentService;

$botGuard = $this->services->get(BotGuard::class);
$refusal = $botGuard->check($this->request);
$result = $refusal === null
    ? $this->services->get(CommentService::class)->addCommentIfAuthorized($_POST)
    : ['error' => $botGuard->message($refusal)];

if (!empty($result['error'])) {
    $this->SetMessage($result['error']);
} elseif (!empty($result['success'])) {
    $this->SetMessage($result['success']);
}
$this->redirect($this->href('', '', '#post-comment'));
