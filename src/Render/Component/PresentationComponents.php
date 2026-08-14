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
     * Two Sources may want the same parameter -- `nb` is "how many" to a form and to a
     * feed alike -- and a Component holds its settings BY NAME, so the second declaration
     * simply replaced the first: the control that survived carried the other source's
     * condition, so a list of a form's entries offered no limit at all and a feed offered
     * two. Declared by both means shown for both, which the rail can say because it reads
     * a `showif` value as a pattern.
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
                // `andShowIf`, not `showIf`: a Source's setting may have a condition of
                // its own -- "which way to sort" is meaningless once the order is random
                // -- and replacing it would show it whenever the source is chosen.
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
            // What a picture does with the space a card gives it. Cropping is the default
            // because a row of cards is a grid and a grid wants one shape; a webmaster
            // whose pictures are logos or portraits wants the other, and until now had to
            // choose between a wall of ragged cards and pictures with their heads cut off.
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
