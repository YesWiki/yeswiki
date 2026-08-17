<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/**
 * ADR-0020: the `material-card` entry-list template is deleted, and the lists that ask for
 * it are moved to `card`.
 *
 * Its stylesheet was 1,208 lines carrying a Material Design swatch set of its own -- twenty
 * pairs of hard-coded colours no palette collapse can absorb, and which no Preset or Colour
 * scheme could ever have reached. The template is also one of the pre-rewrite entry-list
 * family that ticket 37 replaced with shared Presentations, of which `card` is the one this
 * was a variant of.
 *
 * **It is not an orphan, which is why this exists.** `{{entrylist template="material-card"}}`
 * is written in page content, and a template that is not found renders an `alert-danger` box
 * where the entry list should be. Deleting the file without this migration would replace a
 * list of entries with an error message on every page that used it.
 *
 * ## A parse, not a str_replace
 *
 * The value is rewritten inside the action call it belongs to, so prose that happens to
 * contain the words is left alone -- a page *explaining* `template="material-card"` is
 * exactly the page a blind replacement corrupts (ticket 33's lesson: a str_replace passes the
 * obvious test and corrupts content).
 *
 * ## Every revision, not just the latest
 *
 * Same reasoning as ticket 33's rename sweep: the revisions handler diffs revision against
 * revision, and reverting to an older one must not resurrect a template that no longer
 * exists.
 *
 * Idempotent: `card` is not a name this matches, so a second run finds nothing.
 */
class MaterialCardIsRetired extends YesWikiMigration
{
    private const RETIRED = 'material-card';
    private const REPLACEMENT = 'card';

    public function run()
    {
        $db = $this->getService(DbService::class);
        $log = $this->getService(AdministrativeLogService::class);
        $pages = $db->prefixTable('pages');

        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages} WHERE body LIKE ?" . SqlParameters::LIKE_CLAUSE_SUFFIX,
            [SqlParameters::likeContains(self::RETIRED)]
        );

        $rewritten = [];
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            $changed = false;
            array_walk_recursive($body, function (&$value) use (&$changed): void {
                if (!is_string($value) || !str_contains($value, '{{')) {
                    return;
                }
                $result = $this->rewriteText($value);
                if ($result !== $value) {
                    $value = $result;
                    $changed = true;
                }
            });
            if (!$changed) {
                continue;
            }

            $db->query(
                "UPDATE {$pages} SET body = ? WHERE id = ?",
                [PageBody::encode($body), (string)$row['id']]
            );
            $rewritten[(string)$row['tag']] = true;
        }

        // the rewritten text is what the index holds; queued rather than indexed inline, like
        // every other write path (ticket 18)
        $this->getService(SearchIndexer::class)->enqueue(array_keys($rewritten));

        $configured = $this->rewriteDefaultTemplateSetting();

        if ($rewritten !== [] || $configured) {
            $log->log(
                'migration',
                'ADR-0020: the material-card entry-list template was deleted -- its stylesheet'
                . ' carried a colour set no Preset or Colour scheme could reach. Lists asking'
                . ' for it now use the card presentation'
                . ($rewritten === [] ? '' : ', on ' . count($rewritten) . ' page(s), across all revisions: '
                    . implode(', ', array_keys($rewritten)))
                . ($configured ? '. The wiki-wide default_bazar_template was pointing at it and was moved too.' : '.')
            );
        }
    }

    /**
     * Rewrite the template parameter inside each action call, and nothing outside one.
     *
     * The parameter is matched by name so a *value* that happens to read `material-card`
     * somewhere else in the call -- a title, a search term -- is not touched either. Both
     * quote styles, because stored content has both, and the extension is optional because
     * `template="material-card.twig"` is as valid as the bare name.
     */
    private function rewriteText(string $text): string
    {
        return (string)preg_replace_callback(
            '/\{\{(.*?)\}\}/s',
            fn (array $call): string => '{{' . preg_replace(
                '/(\btemplate\s*=\s*)(["\'])' . preg_quote(self::RETIRED, '/') . '(\.twig|\.tpl\.html)?\2/i',
                '$1$2' . self::REPLACEMENT . '$2',
                $call[1]
            ) . '}}',
            $text
        );
    }

    /**
     * The wiki-wide default, if it happens to be the template being deleted.
     *
     * Rarer than a page saying so, and worse: it applies to every `{{entrylist}}` that names
     * no template at all, so leaving it would turn every list on the wiki into an error box.
     */
    private function rewriteDefaultTemplateSetting(): bool
    {
        $file = ConfigurationFileProvider::getConfigFileFromEnv();
        $configuration = $this->getService(ConfigurationService::class)->getConfiguration($file);
        $configuration->load();

        $current = (string)($configuration['default_bazar_template'] ?? '');
        if (!str_starts_with($current, self::RETIRED)) {
            return false;
        }

        $configuration['default_bazar_template'] = self::REPLACEMENT . '.twig';
        $configuration->write();

        return true;
    }
}
