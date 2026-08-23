<?php

namespace YesWiki\Render\Component;

use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Render\Service\PresentationRenderer;

/** The shared Presentations, as palette cards. */
class PresentationComponents implements ProvidesComponents
{
    /** id => [label key, icon]. */
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
     * @param list<array{tag: string, label: string, settings: list<Setting>, selection: list<Setting>}> $sources
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
        //
        // In three courses, because declaration order is what the rail lays out: what the
        // list is pointed at, then what it looks like, then which of its items to take.
        // The one being built comes before the fine print about narrowing it down.
        return $component
            ->settings(...self::foldIn($sources, 'settings'))
            ->settings(...self::shapeSettings($id))
            ->settings(...self::foldIn($sources, 'selection'));
    }

    /**
     * Every Source's settings, each shown for the Source (or Sources) that declared it.
     *
     * @param list<array{tag: string, label: string, settings: list<Setting>, selection: list<Setting>}> $sources
     * @param 'settings'|'selection'                                                                     $course  which of a Source's two lists
     *
     * @return list<Setting>
     */
    private static function foldIn(array $sources, string $course): array
    {
        /** @var array<string, list<string>> $declaredBy */
        $declaredBy = [];
        foreach ($sources as $source) {
            foreach ($source[$course] as $setting) {
                $declaredBy[$setting->name()][] = $source['tag'];
            }
        }

        $settings = [];
        foreach ($sources as $source) {
            foreach ($source[$course] as $setting) {
                $name = $setting->name();
                if (isset($settings[$name])) {
                    continue;
                }
                $settings[$name] = $setting->andShowIf([
                    'source' => implode('|', $declaredBy[$name]),
                ]);
            }
        }

        return array_values($settings);
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
            Setting::choice('imagefit', [
                'cover' => _t('PRESENTATION_IMAGE_COVER'),
                'contain' => _t('PRESENTATION_IMAGE_CONTAIN'),
            ])
                ->label(_t('PRESENTATION_IMAGE_FIT'))
                ->withIcon('crop')
                ->default('cover'),
        ];
    }

    /** Whether this template is one of the shared shapes -- the renderer is the authority. */
    public static function isShared(string $template): bool
    {
        return PresentationRenderer::knows($template);
    }
}
