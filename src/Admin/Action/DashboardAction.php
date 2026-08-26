<?php

namespace YesWiki\Admin\Action;

use YesWiki\Admin\Service\DashboardData;
use YesWiki\Content\Service\FormOverview;
use YesWiki\Content\Service\ListOverview;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;

/** `{{dashboard}}` -- what the wiki holds and what it has been up to, on one screen. */
class DashboardAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** Every section, in the order they are drawn when none are asked for by name. */
    public const SECTIONS = ['forms', 'lists', 'activity', 'keywords', 'index', 'export'];

    private const DEFAULT_MAX = 12;

    public static function performableName(): string
    {
        return 'dashboard';
    }

    public function components(): array
    {
        return [
            Component::for('dashboard')
                ->category(Category::Admin)
                ->label(_t('AB_advanced_action_dashboard_label'))
                ->icon('layout-rows')
                ->previewHeight('400px')
                ->settings(
                    Setting::text('sections')
                        ->label(_t('AB_advanced_action_dashboard_sections_label'))
                        ->hint(_t('AB_advanced_action_dashboard_sections_hint'))
                        ->default(''),
                    Setting::number('max')
                        ->label(_t('AB_advanced_action_dashboard_max_label'))
                        ->default('')
                        ->min(1),
                ),
        ];
    }

    /**
     * @param array<string, mixed> $arg
     *
     * @return array<string, mixed>
     */
    public function formatArguments($arg): array
    {
        $asked = array_filter(array_map('trim', explode(',', (string)($arg['sections'] ?? ''))));
        $sections = array_values(array_intersect(self::SECTIONS, $asked));

        return [
            'sections' => $sections === [] ? self::SECTIONS : $sections,
            'max' => max(1, (int)($arg['max'] ?? 0) ?: self::DEFAULT_MAX),
        ];
    }

    /** @return string */
    public function run()
    {
        $sections = $this->arguments['sections'];
        $max = $this->arguments['max'];
        $data = $this->getService(DashboardData::class);
        $forms = $this->getService(FormOverview::class)->all();

        return $this->render('@core/dashboard/dashboard.twig', [
            'sections' => $sections,
            'formOverview' => $forms,
            'listOverview' => in_array('lists', $sections, true)
                ? $this->getService(ListOverview::class)->all()
                : ['lists' => [], 'canCreate' => false],
            'recentPages' => in_array('activity', $sections, true) ? $data->recentPages($max) : [],
            'newestAccounts' => in_array('activity', $sections, true) ? $data->newestAccounts($max) : [],
            'recentComments' => in_array('activity', $sections, true) ? $data->recentComments($max) : [],
            'keywords' => in_array('keywords', $sections, true) ? $data->keywords(40) : [],
            'pageIndex' => in_array('index', $sections, true) ? $data->pageIndex() : null,
        ] + $data->exportLinks());
    }
}
