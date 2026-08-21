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
 * ADR-0020: the `material-card` entry-list template is deleted, and the lists that ask for it are moved to `card`.
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
            "SELECT id, tag, body FROM {$pages} WHERE {$db->jsonAsText('body')} LIKE ?" . SqlParameters::LIKE_CLAUSE_SUFFIX,
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

    /** Rewrite the template parameter inside each action call, and nothing outside one. */
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

    /** The wiki-wide default, if it happens to be the template being deleted. */
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
