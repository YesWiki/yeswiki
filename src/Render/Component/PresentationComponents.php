<?php

namespace YesWiki\Render\Component;

use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Render\Service\PresentationRenderer;

/**
 * The shared Presentations, as palette cards.
 *
 * Declared here rather than by an action because no action owns one: `Cards` writes
 * `{{entrylist}}` over a form and `{{syndication}}` over a feed, and neither of them can
 * claim it. This is the standalone-provider case `ProvidesComponents` exists for.
 *
 * What a webmaster chooses between is how a list LOOKS -- so that is what the palette shows,
 * and where the things come from is its first setting. Before this, the palette offered
 * thirteen ways to list a form's entries and, separately, one way to list a feed; the same
 * card was two cards depending on which drawer you found it in.
 */
class PresentationComponents implements ProvidesComponents
{
    /** id => [label key, icon]. The four shapes PresentationRenderer knows. */
    private const PRESENTATIONS = [
        'card' => ['PRESENTATION_CARD', 'layout-grid'],
        'list' => ['PRESENTATION_LIST', 'layout-list'],
        'table' => ['PRESENTATION_TABLE', 'table'],
        'timeline' => ['PRESENTATION_TIMELINE', 'history'],
    ];

    public function __construct(private readonly SourceRegistry $sources)
    {
    }

    public function components(): array
    {
        $sources = $this->sources->all();
        if ($sources === []) {
            return [];
        }

        $components = [];
        foreach (self::PRESENTATIONS as $id => [$labelKey, $icon]) {
            $components[] = $this->presentation($id, $labelKey, $icon, $sources);
        }

        return $components;
    }

    /**
     * @param list<array{tag: string, label: string, settings: list<Setting>}> $sources
     */
    private function presentation(string $id, string $labelKey, string $icon, array $sources): Component
    {
        $choices = [];
        foreach ($sources as $source) {
            $choices[$source['tag']] = $source['label'];
        }

        $component = Component::for("presentation-{$id}")
            // every Source's tag: which one is written is decided by the `source` setting,
            // and the rest are tags this component also answers to when reopened
            ->writes(...array_map(static fn (array $s) => $s['tag'], $sources))
            ->pin('template', $id)
            ->category(Category::Lists)
            ->label(_t($labelKey))
            ->icon($icon)
            ->previewHeight('450px')
            ->settings(
                Setting::choice('source', $choices)
                    ->label(_t('PRESENTATION_SOURCE'))
                    ->withIcon('database')
                    ->decidesTag()
                    ->default($sources[0]['tag']),
            );

        // ...and what each Source needs to be told, shown only when it is the one chosen.
        // The Presentation never learns what those settings are; it hands them through.
        foreach ($sources as $source) {
            $shown = array_map(
                static fn (Setting $setting) => $setting->showIf(['source' => $source['tag']]),
                $source['settings']
            );
            $component = $component->settings(...$shown);
        }

        return $component->settings(...self::shapeSettings($id));
    }

    /** @return list<Setting> */
    private static function shapeSettings(string $id): array
    {
        if ($id !== 'card') {
            return [];
        }

        return [
            Setting::number('columns')
                ->label(_t('PRESENTATION_COLUMNS'))
                ->withIcon('columns')
                ->default(3)
                ->min(1)
                ->max(6),
        ];
    }

    /** Whether this template is one of the shared shapes -- the renderer is the authority. */
    public static function isShared(string $template): bool
    {
        return PresentationRenderer::knows($template);
    }
}
