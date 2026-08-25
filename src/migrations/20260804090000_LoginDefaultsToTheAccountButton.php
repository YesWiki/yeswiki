<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** `{{login}}` now renders the account button; the form moved to `login-form.twig`. */
class LoginDefaultsToTheAccountButton extends YesWikiMigration
{
    /** `{{login …}}`, however it is spelled, with its parameters captured. */
    private const ACTION = '/\{\{\s*login\b([^}]*)\}\}/i';

    private const FORM = 'login-form.twig';

    private const BUTTON = 'account-button.twig';

    public function run()
    {
        $db = $this->getService(DbService::class);
        $pages = $db->prefixTable('pages');

        $bodyAsText = $db->jsonAsText('body');
        $rows = $db->loadAll("SELECT id, tag, body FROM {$pages} WHERE {$bodyAsText} LIKE '%login%'");

        $rewritten = [];
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            $changed = $this->rewriteBody($body);
            if ($changed === null) {
                continue;
            }

            $db->query(
                "UPDATE {$pages} SET body = ? WHERE id = ?",
                [PageBody::encode($changed), (string)$row['id']]
            );
            $rewritten[(string)$row['tag']] = true;
        }

        $this->getService(SearchIndexer::class)->enqueue(array_keys($rewritten));

        foreach (array_keys($rewritten) as $tag) {
            $this->say(
                '{{login}} now renders the account button rather than the sign-in form; page '
                . "'{$tag}' was rewritten so that it keeps showing what it showed before."
            );
        }
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>|null null when nothing in it changed
     */
    private function rewriteBody(array $body): ?array
    {
        $changed = false;
        array_walk_recursive($body, function (&$value) use (&$changed): void {
            if (!is_string($value) || stripos($value, '{{') === false) {
                return;
            }
            $rewritten = (string)preg_replace_callback(
                self::ACTION,
                fn (array $match) => '{{login' . $this->rewriteParameters($match[1]) . '}}',
                $value
            );
            if ($rewritten !== $value) {
                $value = $rewritten;
                $changed = true;
            }
        });

        return $changed ? $body : null;
    }

    /** The parameters of one `{{login}}`, with its template settled. */
    private function rewriteParameters(string $parameters): string
    {
        if (preg_match('/\btemplate\s*=\s*(["\'])(.*?)\1/i', $parameters, $named) !== 1) {
            return ' template="' . self::FORM . '"' . $parameters;
        }

        if (strcasecmp($named[2], 'account-link.twig') === 0) {
            return $this->renameTemplate($parameters, self::BUTTON);
        }

        if (strcasecmp($named[2], 'default.twig') === 0) {
            return $this->renameTemplate($parameters, self::FORM);
        }

        return $parameters;
    }

    private function renameTemplate(string $parameters, string $template): string
    {
        return (string)preg_replace(
            '/\btemplate\s*=\s*(["\']).*?\1/i',
            'template="' . $template . '"',
            $parameters
        );
    }
}
