<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/**
 * `{{login}}` now renders the account button; the form moved to `login-form.twig`.
 *
 * Which means a bare `{{login}}` written before this release means something different
 * after it. `PageLogin` -- the page a wiki shows instead of content someone may not read --
 * would answer "you cannot read this" with a 32-pixel icon, and every wiki has one.
 *
 * So a `{{login}}` that never chose a template is pinned to the one it was getting:
 * whatever the page showed yesterday, it shows today. The navbar's button, which named
 * `account-link.twig`, is renamed to what that template is called now -- it was already
 * asking for the button by name, and it is the one place that gets the new behaviour.
 *
 * Bodies are JSON, so this goes through PageBody rather than a string replace on the
 * column (ticket 25's defect 3). Every revision is swept: reverting a page must not bring
 * the old meaning back. Idempotent -- a second run finds every `{{login}}` already
 * carrying a template and does nothing.
 */
class LoginDefaultsToTheAccountButton extends YesWikiMigration
{
    /** `{{login …}}`, however it is spelled, with its parameters captured. */
    private const ACTION = '/\{\{\s*login\b([^}]*)\}\}/i';

    private const FORM = 'login-form.twig';

    private const BUTTON = 'account-button.twig';

    public function run()
    {
        $db = $this->getService(DbService::class);
        $log = $this->getService(AdministrativeLogService::class);
        $pages = $db->prefixTable('pages');

        $rows = $db->loadAll("SELECT id, tag, body FROM {$pages} WHERE body LIKE '%login%'");

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
            // no third argument: the log goes to the daily log page, not into the body just
            // rewritten (see RewriteRetiredSearchActions)
            $log->log(
                'migration',
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
            // never chose one: pin the form it has been rendering all along
            return ' template="' . self::FORM . '"' . $parameters;
        }

        // the navbar's button, renamed. Renamed rather than dropped, even though the
        // parameter is now the default: `{{login}}` means the FORM to this migration, so
        // dropping it here would turn every navbar button back into a form on the next run
        if (strcasecmp($named[2], 'account-link.twig') === 0) {
            return $this->renameTemplate($parameters, self::BUTTON);
        }

        // `default.twig` used to BE the form. The name is retired rather than reused, so
        // that this rewrite is the only thing that decides what such a page meant
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
