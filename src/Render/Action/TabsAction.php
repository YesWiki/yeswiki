<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Render\Service\TabsRenderer;

class TabsAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{tabs}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'tabs';
    }

    public function components(): array
    {
        return [
            Component::for('tabs')
                ->category(Category::Writing)
                ->label(_t('AB_templates_tabs_label'))
                ->icon('layout-list')
                ->description(_t('AB_templates_tabs_description'))
                ->hint(_t('AB_templates_tabs_hint'))
                ->previewHeight('300px')
                ->wraps(_t('AB_templates_tabs_wrappedcontentexample'))
                ->addOnly()
                ->settings(
                    Setting::text('titles')
                        ->label(_t('AB_templates_tabs_titles_label'))
                        ->hint(_t('AB_templates_tabs_titles_hint'))
                        ->suggests(_t('AB_templates_tabs_titles_default')),
                    Setting::number('selectedtab')
                        ->label(_t('AB_templates_tabs_selectedtab_label'))
                        ->default(1)
                        ->min(1)
                        ->advanced(),
                    Setting::choice('btnsize', [
                        'std' => _t('AB_templates_tabs_btnsize_default'),
                        'btn-xs' => _t('AB_templates_tabs_btnsize_small'),
                    ])
                        ->label(_t('AB_templates_tabs_btnsize_label'))
                        ->default('btn-xs')
                        ->advanced(),
                    Setting::choice('btncolor', [
                        'btn-primary' => _t('AB_templates_tabs_btncolor_primary'),
                        'btn-secondary-1' => _t('AB_templates_tabs_btncolor_secondary_1'),
                        'btn-secondary-2' => _t('AB_templates_tabs_btncolor_secondary_2'),
                    ])
                        ->label(_t('AB_templates_tabs_btncolor_label'))
                        ->default('btn-primary')
                        ->advanced(),
                    Setting::choice('bottom_nav', [
                        'yes' => _t('AB_templates_tabs_bottom_nav_yes'),
                        'no' => _t('AB_templates_tabs_bottom_nav_no'),
                    ])
                        ->label(_t('AB_templates_tabs_bottom_nav_label'))
                        ->default('yes')
                        ->advanced(),
                    Setting::choice('counter_on_bottom_nav', [
                        'yes' => _t('AB_templates_tabs_counter_on_bottom_nav_yes'),
                        'no' => _t('AB_templates_tabs_counter_on_bottom_nav_no'),
                    ])
                        ->label(_t('AB_templates_tabs_counter_on_bottom_nav_label'))
                        ->default('no')
                        ->advanced(),
                ),
        ];
    }

    public function formatArguments($arg)
    {
        $titles = array_map('trim', $this->formatArray($arg['titles'] ?? []));

        return [
            'titles' => $titles,
            'btnsize' => (isset($arg['btnsize']) && $arg['btnsize'] === 'std')
              ? ''
              : 'btn-xs',
            'btncolor' => (!empty($arg['btncolor']) && in_array($arg['btncolor'], ['btn-primary', 'btn-secondary-1', 'btn-secondary-2'], true))
              ? $arg['btncolor']
              : 'btn-primary',
            'bottom_nav' => $this->formatBoolean($arg, true, 'bottom_nav'), // default should be true if empty
            'counter_on_bottom_nav' => $this->formatBoolean($arg, false, 'counter_on_bottom_nav'),
            'selectedtab' => (array_key_exists('selectedtab', $arg) && intval($arg['selectedtab']) > 0 && intval($arg['selectedtab']) <= count($titles)) ? intval($arg['selectedtab']) : 1,
        ];
    }

    public function run()
    {
        return $this->getService(TabsRenderer::class)->openTabs('action', array_merge(
            $this->arguments,
            ['btnClass' => $this->arguments['btncolor'] . ' ' . $this->arguments['btnsize']]
        ));
    }

    public function end(): string
    {
        return $this->getService(TabsRenderer::class)->closeTabs();
    }
}
