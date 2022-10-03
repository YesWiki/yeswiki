<?php



use YesWiki\Core\Service\CommentService;

$commentService = $this->services->get(CommentService::class);
$result = $commentService->addCommentIfAutorized($_POST);

if (!empty($result['error'])) {
    $this->SetMessage($result['error']);
} elseif (!empty($result['success'])) {
    $this->SetMessage($result['success']);
}
// redirect to page
$this->redirect($this->href('', '', '#post-comment'));
