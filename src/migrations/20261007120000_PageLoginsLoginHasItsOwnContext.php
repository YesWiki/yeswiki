<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** Gives PageLogin's `{{login}}` its own context. */
class PageLoginsLoginHasItsOwnContext extends YesWikiMigration
{
    public const CONTEXT = 'login-page';

    public function run()
    {
        $db = $this->getService(DbService::class);
        $pages = $db->prefixTable('pages');
        $row = $db->loadSingle("SELECT id, body FROM {$pages} WHERE tag = ? AND latest = 'Y'", ['PageLogin']);
        if ($row === null) {
            return;
        }

        $body = PageBody::decode((string)$row['body']);
        $content = PageBody::content($body);
        $withContext = self::addContext($content);
        if ($withContext === $content) {
            return;
        }

        $body[PageBody::CONTENT] = $withContext;
        $db->query("UPDATE {$pages} SET body = ? WHERE id = ?", [PageBody::encode($body), (string)$row['id']]);
        $this->getService(SearchIndexer::class)->enqueue(['PageLogin']);
        $this->say('the {{login}} of PageLogin now has context="' . self::CONTEXT . '", so it no longer answers for the login of the menu.');
    }

    /** Adds context="login-page" to each `{{login}}` without one. */
    public static function addContext(string $content): string
    {
        return (string)preg_replace_callback(
            '/\{\{\s*login(?![\w-])((?:(?!\}\}).)*)\}\}/si',
            function (array $matches): string {
                if (preg_match('/(^|\s)context\s*=/i', $matches[1]) === 1) {
                    return $matches[0];
                }

                return '{{login context="' . self::CONTEXT . '"' . $matches[1] . '}}';
            },
            $content
        );
    }
}
