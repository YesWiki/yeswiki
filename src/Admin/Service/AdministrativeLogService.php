<?php

namespace YesWiki\Admin\Service;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\DbService;

/**
 * Append-only administrative audit log written into wiki pages
 * (historic Wiki::LogAdministrativeAction()): one page per day,
 * `LogDesActionsAdministratives{Ymd}`, pruned to its last 10 revisions.
 */
class AdministrativeLogService
{
    protected PageManager $pageManager;
    protected DbService $dbService;

    public function __construct(PageManager $pageManager, DbService $dbService)
    {
        $this->pageManager = $pageManager;
        $this->dbService = $dbService;
    }

    /**
     * Append one log line for $user to the daily log page (or to $page when given).
     *
     * @return int 0 on success, 1 when the append failed
     */
    public function log(string $user, string $content, string $page = ''): int
    {
        $content = str_replace(["\r\n", "\n", "\r"], '\\n', $content);
        $contentToAppend = "\n" . date('Y-m-d H:i:s') . ' . . . . ' . $user . ' . . . . ' . $content . "\n";
        $tag = $page ?: 'LogDesActionsAdministratives' . date('Ymd');
        $result = $this->appendContentToPage($contentToAppend, $tag);
        if (empty($page) && $result === 0) {
            try {
                // keep only 10 revisions of this page
                $revisions = $this->pageManager->getRevisions($tag);
                if (!empty($revisions) && count($revisions) > 10) {
                    $idsToDelete = array_map(
                        function ($data) {
                            return $data['id'];
                        },
                        array_slice($revisions, 10)
                    );

                    $formattedIds = implode(
                        ',',
                        array_map(
                            function ($id) {
                                return $this->dbService->escape($id);
                            },
                            $idsToDelete
                        )
                    );

                    // there are some versions to remove from DB
                    // let's build one big request, that's better...
                    $sql = <<<SQL
                    DELETE FROM {$this->dbService->prefixTable('pages')} WHERE id IN ($formattedIds);
                    SQL;

                    // ... and send it !
                    $this->dbService->query($sql);
                }
            } catch (\Throwable $th) {
            }
        }

        return $result;
    }

    /**
     * Append $content to the end of page $tag (ACLs bypassed: the log is written no
     * matter who triggered the logged action) and re-register the page's links.
     *
     * @return int 0 on success, 1 when no page could be saved
     */
    protected function appendContentToPage(string $content, string $tag): int
    {
        $page = $this->pageManager->getOne($tag);
        $body = $page['body'] ?? [];
        $body[PageBody::CONTENT] = PageBody::content($body) . $content;
        $this->pageManager->save($tag, $body, '', true);

        return 0;
    }
}
