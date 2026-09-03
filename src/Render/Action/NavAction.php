<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Render\Service\MenuRenderer;
use YesWiki\Render\Service\TemplateHelperService;

/**
 * `{{nav}}` -- a page asking for one of the wiki's menus (ticket 64 / ADR-0028).
 *
 * It used to carry the navigation itself, as parallel comma-separated `links` and `titles` with an
 * `icons` parameter the palette never offered. Those are gone: a menu is Content, this names one,
 * and the same renderer draws it here, in the navbar and in the quick access bar.
 */
class NavAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'nav';
    }

    public function components(): array
    {
        return [
            Component::for('nav')
                ->category(Category::Navigation)
                ->label(_t('AB_templates_nav_label'))
                ->icon('menu-2')
                ->description(_t('AB_templates_nav_description'))
                ->hint(_t('AB_templates_nav_hint'))
                ->previewHeight('300px')
                ->settings(
                    Setting::menu('menu')
                        ->label(_t('AB_templates_nav_menu_label'))
                        ->hint(_t('AB_templates_nav_menu_hint')),
                    Setting::checkbox('showicons')
                        ->label(_t('AB_templates_nav_showicons_label'))
                        ->default(false),
                    Setting::checkbox('showlabels')
                        ->label(_t('AB_templates_nav_showlabels_label'))
                        ->default(true),
                    Setting::checkbox('showdropdown')
                        ->label(_t('AB_templates_nav_showdropdown_label'))
                        ->default(true),
                    Setting::cssClass('class')
                        ->subSettings(
                            Setting::choice('type', [
                                'nav nav-tabs' => _t('AB_templates_nav_class_tabs'),
                                'nav nav-pills' => _t('AB_templates_nav_class_pills'),
                                'nav nav-tabs nav-justified' => _t('AB_templates_nav_class_justified'),
                                'nav nav-stacked' => _t('AB_templates_nav_class_vertical'),
                            ])
                            ->label(_t('AB_templates_nav_class_label'))
                            ->default('nav nav-tabs'),
                        ),
                ),
        ];
    }

    public function run(): string
    {
        $arguments = $this->getService(PerformableArguments::class);
        $menu = trim((string)$arguments->get('menu'));
        if ($menu === '') {
            return '';
        }

        return $this->getService(MenuRenderer::class)->render($menu, MenuRenderer::NAV, [
            'showicons' => $this->flag('showicons', false),
            'showlabels' => $this->flag('showlabels', true),
            'showdropdown' => $this->flag('showdropdown', true),
            'class' => trim((string)$arguments->get('class')),
            'data' => $this->getService(TemplateHelperService::class)->getDataParameter(),
        ]);
    }

    private function flag(string $name, bool $default): bool
    {
        $value = $this->getService(PerformableArguments::class)->get($name);

        return $value === null || $value === '' ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
