<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateHelperService;

/** `{{nav}}` -- converted from the procedural actions/nav.php by ticket 06. */
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
                    Setting::navLinks('nav-links')
                        ->raw('btn-label-add', _t('AB_templates_nav_add_tag'))
                        ->subSettings(
                            Setting::page('link')
                            ->label(_t('AB_templates_nav_link')),
                            Setting::text('title')
                            ->label(_t('AB_templates_nav_title')),
                        ),
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
                    Setting::checkbox('hideifnoaccess')
                        ->label(_t('AB_templates_nav_hide_if_no_access_label'))
                        ->default(false),
                ),
        ];
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        $class = $this->getService(PerformableArguments::class)->get('class');
        $class = ((!empty($class)) ? $class : 'yw-nav');

        $data = $this->getService(TemplateHelperService::class)->getDataParameter();
        $pagetag = $this->getService(PageContext::class)->getTag();

        $links = $this->getService(PerformableArguments::class)->get('links');
        if (!empty($links)) {
            $links = explode(',', $links);
            $links = array_map('trim', $links);
        }

        $titles = $this->getService(PerformableArguments::class)->get('titles');
        if (!empty($titles)) {
            $titles = explode(',', $titles);
            $titles = array_map('trim', $titles);
        }

        $icons = $this->getService(PerformableArguments::class)->get('icons');
        if (!empty($icons)) {
            $icons = explode(',', $icons);
            foreach ($icons as $key => $icon) {
                $icon = $this->getService(TemplateHelperService::class)->formatIconHtml($icon);

                if (!empty($icon)) {
                    $icon = $icon . ' ';
                }
                $icons[$key] = $icon;
            }
        }

        $hideIfNoAccess = $this->getService(PerformableArguments::class)->get('hideifnoaccess');
        $listlinks = '';
        foreach ($titles as $key => $title) {
            $haveAccess = true;
            if (empty($links[$key])) {
                $url = '';
            } else {
                $linkParts = $this->getService(UrlFormatter::class)->extractLinkParts($links[$key]);
                [$url, $method, $params] = ['', '', ''];
                if ($linkParts) {
                    $method = $linkParts['method'];
                    $params = $linkParts['params'];
                    $url = $this->getService(UrlFormatter::class)->href($method, $linkParts['tag'], $params);
                    if ($hideIfNoAccess == 'true' && isset($linkParts['tag']) && !$this->getService(AclService::class)->hasAccess('read', $linkParts['tag'])) {
                        $haveAccess = false;
                    }
                } else {
                    $url = $links[$key];
                }
            }

            if ($haveAccess) {
                $method ??= '';
                $params ??= [];
                $listclass = ($url == $this->getService(UrlFormatter::class)->href($method, $this->getService(PageContext::class)->getTag(), $params)) ? ' class="active"' : '';
                $listlinks .= '<li' . $listclass . '><a href="' . $url . '">'
                    . (isset($icons[$key]) ? $icons[$key] : '')
                    . $title . '</a></li>' . "\n";
            }
        }

        $navID = uniqid('nav_');
        $data = '';
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data .= ' data-' . $key . '="' . $value . '"';
            }
        }

        if (!empty($listlinks)) {
            echo ' <!-- start of nav -->
                <nav><ul class="' . $class . '" id="' . $navID . '" ' . $data . '>' . $listlinks . '</ul></nav>' . "\n";
        }
    }
}
