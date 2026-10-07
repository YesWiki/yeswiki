<?php

use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiMigration;

/**
 * Gives every {{login}} of PageLogin its own context, so the login form shown on a page you cannot read is not answered by the menu's one.
 */
class AddLoginContextToPageLogin extends YesWikiMigration
{
    public const CONTEXT = 'login-page';

    public function run(): void
    {
        $this->contextualise('PageLogin');
    }

    /** Saves the page $tag with its {{login}} actions contextualised, when one of them still lacks a context. */
    public function contextualise(string $tag): void
    {
        $pageManager = $this->getService(PageManager::class);
        $page = $pageManager->getOne($tag, null, false, true);
        if (empty($page)) {
            return;
        }

        $body = self::addContext($page['body']);
        if ($body !== $page['body']) {
            $pageManager->save($tag, $body, '', true);
        }
    }

    /** Adds context="login-page" to each {{login}} action of $body that has no context yet. */
    public static function addContext(string $body): string
    {
        return preg_replace_callback(
            '/\{\{login(?![\w-])((?:(?!\}\}).)*)\}\}/s',
            function ($matches) {
                if (preg_match('/(^|\s)context\s*=/', $matches[1])) {
                    return $matches[0];
                }

                return '{{login context="' . self::CONTEXT . '"' . $matches[1] . '}}';
            },
            $body
        );
    }
}
