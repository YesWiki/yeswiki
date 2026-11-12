<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\WikiSettings;
use YesWiki\Search\Service\SearchIndexer;

/** Map backgrounds leaflet-providers 4 no longer has, or only behind a key, become OpenStreetMap in action calls and in the wiki's own setting. */
class RemovedMapBackgroundsBecomeOpenStreetMap extends YesWikiMigration
{
    public const REPLACEMENT = 'OpenStreetMap.Mapnik';

    public const REMOVED = [
        'OpenStreetMap.BlackAndWhite',
        'Stamen.Toner', 'Stamen.TonerBackground', 'Stamen.TonerLite', 'Stamen.Watercolor', 'Stamen.Terrain', 'Stamen.TerrainBackground',
        'Esri.DeLorme',
        'HERE.normalDay',
        'CartoDB.Positron', 'CartoDB.PositronNoLabels', 'CartoDB.PositronOnlyLabels',
        'CartoDB.DarkMatter', 'CartoDB.DarkMatterNoLabels', 'CartoDB.DarkMatterOnlyLabels',
    ];

    private const CALL = '/\{\{((?:"[^"]*"|[^}"])*)\}\}/';

    private const PARAMETER = '/\b(providers?)(\s*=\s*)"([^"]*)"/i';

    public function run()
    {
        $db = $this->getService(DbService::class);
        $pages = $db->prefixTable('pages');
        $rows = $db->loadAll("SELECT id, tag, body FROM {$pages} WHERE " . $db->jsonAsText('body') . " LIKE '%provider%'");

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

        $setting = (string)$this->getService(RuntimeConfig::class)->getValue('baz_provider', '');
        $settingChanged = in_array($setting, self::REMOVED, true);
        if ($settingChanged) {
            $this->getService(WikiSettings::class)->store(['baz_provider' => self::REPLACEMENT]);
        }

        if ($rewritten !== [] || $settingChanged) {
            $this->say(
                'Map backgrounds that no longer exist or now need an API key were replaced by ' . self::REPLACEMENT
                . ($rewritten !== [] ? ' in ' . implode(', ', array_keys($rewritten)) : '')
                . ($settingChanged ? ($rewritten !== [] ? ', and' : '') . " in the map field setting (was {$setting})" : '')
                . '.'
            );
        }
    }

    /** Swaps the removed backgrounds named by `provider` and `providers` in the action calls of $content, keeping each list free of repeats. */
    public static function rewrite(string $content): string
    {
        return (string)preg_replace_callback(self::CALL, static function (array $call): string {
            $arguments = (string)preg_replace_callback(self::PARAMETER, static function (array $parameter): string {
                $names = array_map('trim', explode(',', $parameter[3]));
                if (array_intersect($names, self::REMOVED) === []) {
                    return $parameter[0];
                }
                $names = array_map(static fn (string $name): string => in_array($name, self::REMOVED, true) ? self::REPLACEMENT : $name, $names);

                return $parameter[1] . $parameter[2] . '"' . implode(',', array_values(array_unique($names))) . '"';
            }, $call[1]);

            return '{{' . $arguments . '}}';
        }, $content);
    }
}
