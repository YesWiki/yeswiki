<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;

// V?rification de s?curit?
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

use YesWiki\Core\Service\CommentService;

// Generate page before displaying the header, so that it might interract with the header
ob_start();

echo '<div class="page"';
echo (($user = $this->GetUser()) && ($user['doubleclickedit'] == 'N') || !$this->HasAccess('write')) ? '' : ' ondblclick="doubleClickEdit(event);"';
echo '>' . "\n";
if (!empty($_SESSION['redirects'])) {
    $trace = $_SESSION['redirects'];
    $tag = $trace[count($trace) - 1];
    $prevpage = $this->LoadPage($tag);
    echo '<div class="redirectfrom"><em>(' . str_replace('{linkFrom}', $this->Link($prevpage['tag'], 'edit'), _t('REDIRECTED_FROM')) . ")</em></div>\n";
    unset($_SESSION['redirects'][count($trace) - 1]);
}

if ($HasAccessRead = $this->HasAccess('read')) {
    if (!$this->page) {
        echo str_replace(
            ['{beginLink}', '{endLink}'],
            ["<a href=\"{$this->href('edit')}\">", '</a>'],
            _t('NOT_FOUND_PAGE')
        );
    } else {
        // comment header?
        if ($this->page['comment_on']) {
            echo '<div class="commentinfo">' . str_replace(
                ['{tag}', '{user}', '{time}'],
                [$this->ComposeLinkToPage($this->page['comment_on'], '', '', 0), $this->Format($this->page['user']), $this->page['time']],
                _t('COMMENT_INFO')
            ) . '</div>';
        }

        if ($this->page['latest'] == 'N') {
            echo '<div class="alert alert-info">' . "\n";
            echo str_replace(['{link}', '{time}'], ["<a href=\"{$this->href()}\">{$this->GetPageTag()}</a>", $this->page['time']], _t('REVISION_IS_ARCHIVE_OF_TAG_ON_TIME'));
            // if this is an old revision, display some buttons
            if ($this->HasAccess('write')) {
                $latest = $this->LoadPage($this->tag); ?>
				<?php
                  $time = isset($_GET['time']) ? $_GET['time'] : '';
                echo $this->FormOpen(testUrlInIframe() ? 'editiframe' : 'edit', '', 'get'); ?>
				<input type="hidden" name="time" value="<?php echo $time; ?>" />
				<input class="btn btn-primary" type="submit" value="<?php echo _t('EDIT_ARCHIVED_REVISION'); ?>" />
				<?php echo $this->FormClose(); ?>
				<?php
            }

            echo '</div>' . "\n";
        }

        // display page
        $this->RegisterInclusion($this->GetPageTag());
        $entryManager = $this->services->get(EntryManager::class);
        if ($entryManager->isEntry($this->page['tag'])) {
            $entryController = $this->services->get(EntryController::class);
            echo $entryController->view($this->GetPageTag(), $this->page['time'] ?? null);
        } else {
            echo $this->Format($this->page['body'], 'wakka', $this->GetPageTag());
        }
        $this->UnregisterLastInclusion();
    }
} else {
    echo '<i>' . _t('LOGIN_NOT_AUTORIZED') . '</i>'; // to sync with /tools/templates/handlers/page/show__.php
}
?>
<hr class="hr_clear" />
</div>


<?php
// render the comments if needed
echo $this->services->get(CommentService::class)->renderCommentsForPage($this->getPageTag());

// get the content buffer and display the page
$content = ob_get_clean();
echo $this->Header();
echo $content;
echo $this->Footer();
?>
