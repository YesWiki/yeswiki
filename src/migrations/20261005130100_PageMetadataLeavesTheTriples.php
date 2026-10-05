<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Files\Service\ProgramFiles;
use YesWiki\Files\Service\Storage;

/**
 * Doryphore kept each page's theme and chrome overrides in a `metadata` triple; ticket 02 retired the triple without carrying its values into `pages.metadata`.
 */
class PageMetadataLeavesTheTriples extends YesWikiMigration
{
    private const PROPERTY = 'http://outils-reseaux.org/_vocabulary/metadata';

    private const CHROME = ['PageHeader', 'PageFooter'];

    private const LISTED = 20;

    public function run()
    {
        if (!in_array(trim($this->dbService->prefixTable('triples')), $this->dbService->schema()->getTables(), true)) {
            return;
        }
        $report = $this->carryAll();
        if ($report['carried'] !== []) {
            $this->say('Theme and header/footer choices kept by Doryphore in the metadata triple were added to the Metadata of ' . count($report['carried'])
                . ' page(s), without replacing any key they already had: ' . self::listed($report['carried']));
        }
        if ($report['dropped'] !== []) {
            $this->say('Values naming a theme, preset, background or page this wiki does not have were not carried: ' . self::listed($report['dropped']));
        }
    }

    /**
     * @param list<string>|null $onlyTags
     *
     * @return array{carried: list<string>, dropped: list<string>}
     */
    public function carryAll(?array $onlyTags = null): array
    {
        $triples = trim($this->dbService->prefixTable('triples'));
        $rows = $this->dbService->loadAll("SELECT resource, value FROM {$triples} WHERE property = ? ORDER BY id", [self::PROPERTY]);
        $report = ['carried' => [], 'dropped' => []];
        foreach ($rows as $row) {
            $tag = (string)$row['resource'];
            if ($onlyTags !== null && !in_array($tag, $onlyTags, true)) {
                continue;
            }
            $values = json_decode((string)$row['value'], true);
            if (!is_array($values)) {
                continue;
            }
            [$carry, $dropped] = $this->usable($values);
            foreach ($dropped as $key) {
                $report['dropped'][] = "{$tag}.{$key}";
            }
            if ($carry !== [] && $this->merge($tag, $carry)) {
                $report['carried'][] = $tag;
            }
        }

        return $report;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array{0: array<string, string>, 1: list<string>}
     */
    private function usable(array $values): array
    {
        $carry = [];
        $dropped = [];
        $text = static fn (string $key): string => is_scalar($values[$key] ?? null) ? trim((string)$values[$key]) : '';

        foreach (self::CHROME as $role) {
            $page = $text($role);
            if ($page === '' || $page === $role) {
                continue;
            }
            if (preg_match('/^' . WN_CAMEL_CASE_EVOLVED . '$/u', $page) === 1 && $this->pageExists($page)) {
                $carry[$role] = $page;
            } else {
                $dropped[] = $role;
            }
        }

        $theme = $text('theme');
        if ($theme !== '') {
            if ($this->themeHas($theme, 'squelettes/' . $text('squelette')) && $this->themeHas($theme, 'styles/' . $text('style'))) {
                $carry += ['theme' => $theme, 'squelette' => $text('squelette'), 'style' => $text('style')];
            } else {
                $dropped[] = 'theme';
            }
        }

        $preset = $text('favorite_preset');
        if ($preset !== '') {
            $presetExists = str_starts_with($preset, 'custom/')
                ? $this->getService(Storage::class)->exists('custom/css-presets/' . substr($preset, strlen('custom/')))
                : isset($carry['theme']) && $this->themeHas($carry['theme'], 'presets/' . $preset);
            if ($presetExists) {
                $carry['favorite_preset'] = $preset;
            } else {
                $dropped[] = 'favorite_preset';
            }
        }

        $background = $text('bgimg');
        if ($background !== '') {
            if ($this->getService(Storage::class)->fileExists('files/backgrounds/' . basename($background))) {
                $carry['bgimg'] = $background;
            } else {
                $dropped[] = 'bgimg';
            }
        }

        return [$carry, $dropped];
    }

    private function pageExists(string $tag): bool
    {
        return (bool)$this->dbService->loadSingle("SELECT 1 FROM {$this->dbService->prefixTable('pages')} WHERE tag = ? LIMIT 1", [$tag]);
    }

    private function themeHas(string $theme, string $file): bool
    {
        if (str_contains($theme, '..') || str_contains($file, '..') || str_ends_with($file, '/')) {
            return false;
        }

        return $this->getService(ProgramFiles::class)->findExists("custom/themes/{$theme}/{$file}", "themes/{$theme}/{$file}");
    }

    /** @param array<string, string> $carry */
    private function merge(string $tag, array $carry): bool
    {
        $pages = $this->dbService->prefixTable('pages');
        $latest = $this->dbService->loadSingle("SELECT id, metadata FROM {$pages} WHERE tag = ? AND latest = 'Y' LIMIT 1", [$tag]);
        if ($latest === null) {
            return false;
        }
        $existing = json_decode((string)($latest['metadata'] ?? ''), true);
        $existing = is_array($existing) ? $existing : [];
        $added = array_diff_key($carry, $existing);
        if ($added === []) {
            return false;
        }
        $this->dbService->query(
            "UPDATE {$pages} SET metadata = ? WHERE id = ?",
            [(string)json_encode($existing + $added, PageBody::JSON_FLAGS), (string)$latest['id']]
        );
        $this->getService(PageManager::class)->forget($tag);

        return true;
    }

    /** @param list<string> $items */
    private static function listed(array $items): string
    {
        return implode(', ', array_slice($items, 0, self::LISTED)) . (count($items) > self::LISTED ? ', …' : '');
    }
}
