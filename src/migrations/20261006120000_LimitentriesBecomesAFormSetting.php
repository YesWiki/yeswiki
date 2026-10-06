<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** contrib's `{{limitentries}}` becomes the form's own `max_entries`, and each call the entry form it used to show. */
class LimitentriesBecomesAFormSetting extends YesWikiMigration
{
    private const CALL = '/\{\{\s*limitentries\b((?:"[^"]*"|[^}"])*)\}\}/i';

    public function run()
    {
        $db = $this->getService(DbService::class);
        $pages = $db->prefixTable('pages');
        $rows = $db->loadAll("SELECT id, tag, body, latest FROM {$pages} WHERE " . $db->jsonAsText('body') . " LIKE '%limitentries%'");

        $settings = [];
        foreach ($rows as $row) {
            if ($row['latest'] === 'Y') {
                $settings = self::settingsFrom(PageBody::content(PageBody::decode((string)$row['body'])), $settings);
            }
        }
        $limited = $this->applyToForms($settings);

        $rewritten = [];
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            $changed = false;
            array_walk_recursive($body, function (&$value) use (&$changed): void {
                if (!is_string($value)) {
                    return;
                }
                $after = self::rewrite($value);
                if ($after !== $value) {
                    $value = $after;
                    $changed = true;
                }
            });
            if ($changed) {
                $db->query("UPDATE {$pages} SET body = ? WHERE id = ?", [PageBody::encode($body), (string)$row['id']]);
                $rewritten[(string)$row['tag']] = true;
            }
        }
        $this->getService(SearchIndexer::class)->enqueue(array_keys($rewritten));

        if ($rewritten !== []) {
            $this->say(
                '{{limitentries}} is now a setting of the form: ' . ($limited === [] ? 'no form was given a limit' : 'limits set on form(s) ' . implode(', ', $limited))
                . '; the calls in ' . implode(', ', array_keys($rewritten)) . ' became the entry form.'
            );
        }
    }

    /**
     * @param array<string, array{limit: int, message: string, count: ?string}> $settings
     *
     * @return array<string, array{limit: int, message: string, count: ?string}> a null count is one no call gave, which gets the default
     */
    public static function settingsFrom(string $content, array $settings): array
    {
        preg_match_all(self::CALL, $content, $calls);
        foreach ($calls[1] as $arguments) {
            $args = self::arguments($arguments);
            $id = trim($args['id'] ?? '');
            $limit = (int)($args['limit'] ?? 0);
            if ($id === '' || $limit <= 0) {
                continue;
            }
            $current = $settings[$id] ?? ['limit' => $limit, 'message' => '', 'count' => null];
            $settings[$id] = [
                'limit' => min($current['limit'], $limit),
                'message' => $current['message'] !== '' ? $current['message'] : self::placeholders($args['message_max'] ?? ''),
                'count' => $current['count'] ?? (isset($args['message_count']) ? self::placeholders($args['message_count']) : null),
            ];
        }

        return $settings;
    }

    /** Each call becomes the entry form of its form, as the palette writes it; a call naming no form goes. */
    public static function rewrite(string $content): string
    {
        return (string)preg_replace_callback(self::CALL, static function (array $call): string {
            $id = trim(self::arguments($call[1])['id'] ?? '');

            return $id === '' ? '' : '{{bazar id="' . $id . '" showmenu="0" view="saisir"}}';
        }, $content);
    }

    /**
     * @param array<string, array{limit: int, message: string, count: ?string}> $settings
     *
     * @return list<string> the forms given a limit
     */
    private function applyToForms(array $settings): array
    {
        $formManager = $this->getService(FormManager::class);
        $limited = [];
        foreach ($settings as $id => $setting) {
            $form = $formManager->getOne((string)$id);
            if (empty($form) || !empty($form['max_entries'])) {
                continue;
            }
            $form['max_entries'] = (string)$setting['limit'];
            $form['max_entries_message'] = $setting['message'];
            $form['max_entries_count_message'] = $setting['count'] ?? _t('FORM_MAX_ENTRIES_DEFAULT_COUNT_MESSAGE');
            $formManager->update($form);
            $limited[] = (string)$id;
        }

        return $limited;
    }

    /** @return array<string, string> */
    private static function arguments(string $arguments): array
    {
        preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $arguments, $pairs, PREG_SET_ORDER);

        return array_column($pairs, 2, 1);
    }

    private static function placeholders(string $message): string
    {
        return strtr($message, ['%{limit}' => '{limit}', '%{nb}' => '{nb}']);
    }
}
