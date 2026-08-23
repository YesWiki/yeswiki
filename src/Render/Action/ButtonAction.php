<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateHelperService;

/** `{{button}}` -- converted from the procedural actions/button.php by ticket 06. */
class ButtonAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'button';
    }

    public function components(): array
    {
        return [
            Component::for('button')
                ->category(Category::Navigation)
                ->label(_t('AB_buttons_action_button_label'))
                ->icon('external-link')
                ->description(_t('AB_buttons_action_button_description'))
                ->previewHeight('100px')
                ->settings(
                    Setting::text('text')
                        ->label(_t('AB_buttons_action_button_text_label'))
                        ->default('')
                        ->suggests(_t('AB_buttons_action_button_text_default')),
                    Setting::page('link')
                        ->label(_t('AB_buttons_action_button_link_label'))
                        ->hint(_t('AB_buttons_action_button_link_hint'))
                        ->suggests('https://yeswiki.net')
                        ->required(),
                    Setting::text('title')
                        ->label(_t('AB_buttons_action_button_title_label')),
                    Setting::icon('icon')
                        ->label(_t('AB_buttons_action_button_icon_label')),
                    Setting::cssClass('class')
                        ->label(_t('AB_buttons_action_button_class_label'))
                        ->subSettings(
                            Setting::choice('color', [
                                'btn-default' => _t('AB_buttons_action_button_color_default'),
                                'btn-primary' => _t('AB_buttons_action_button_color_primary'),
                                'btn-secondary-1' => _t('AB_buttons_action_button_color_secondary1'),
                                'btn-secondary-2' => _t('AB_buttons_action_button_color_secondary2'),
                                'btn-success' => _t('AB_buttons_action_button_color_success'),
                                'btn-info' => _t('AB_buttons_action_button_color_info'),
                                'btn-warning' => _t('AB_buttons_action_button_color_warning'),
                                'btn-danger' => _t('AB_buttons_action_button_color_danger'),
                                'btn-link' => _t('AB_buttons_action_button_color_link'),
                            ])
                            ->label(_t('AB_buttons_action_button_color_label'))
                            ->suggests('btn-primary'),
                            Setting::choice('size', [
                                '' => _t('AB_buttons_action_button_size_standard'),
                                'btn-xs' => _t('AB_buttons_action_button_size_small'),
                                'btn-sm' => _t('AB_buttons_action_button_size_medium'),
                                'btn-lg' => _t('AB_buttons_action_button_size_big'),
                            ])
                            ->label(_t('AB_buttons_action_button_size_label')),
                            Setting::choice('modal', [
                                'modalbox' => _t('AB_buttons_action_button_modal_modalbox'),
                                'modalbox-hover' => _t('AB_buttons_action_button_modal_modalbox_hover'),
                            ])
                            ->label(_t('AB_buttons_action_button_modal_label'))
                            ->hint(_t('AB_buttons_action_button_modal_hint')),
                            Setting::choice('pull', [
                                'pull-right' => _t('AB_buttons_action_button_pull_right'),
                                'btn-block' => _t('AB_buttons_action_button_pull_block'),
                            ])
                            ->label(_t('AB_buttons_action_button_pull_label')),
                            Setting::choice('new-window', [
                                'new-window' => _t('AB_buttons_action_button_new_window_yes'),
                            ])
                            ->label(_t('AB_buttons_action_button_new_window_label')),
                        ),
                    Setting::checkbox('hideifnoaccess')
                        ->label(_t('AB_buttons_action_button_hideifnoaccess_label'))
                        ->default('false'),
                    Setting::checkbox('nobtn')
                        ->label(_t('AB_buttons_action_button_nobtn_label'))
                        ->default('0')
                        ->checkedValues(1, 0),
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
        $link = $this->getService(PerformableArguments::class)->get('link');

        if ($link == 'config/root_page') {
            $link = $this->getService(RuntimeConfig::class)['root_page'];
            $this->getService(PerformableArguments::class)->set('link', $link);
        }

        $linkParts = $this->getService(UrlFormatter::class)->extractLinkParts($link);
        if ($linkParts) {
            $link = $this->getService(UrlFormatter::class)->href($linkParts['method'], $linkParts['tag'], $linkParts['params']);
        }

        $link = $this->getService(UrlFormatter::class)->generateLink($link);

        $text = $this->getService(PerformableArguments::class)->get('text');

        $title = $this->getService(PerformableArguments::class)->get('title');

        $icon = $this->getService(TemplateHelperService::class)->formatIconHtml($this->getService(PerformableArguments::class)->get('icon'));
        if (!empty($icon) && !empty($text)) {
            $icon = $icon . ' ';
        }

        $class = $this->getService(PerformableArguments::class)->get('class');
        $class .= (!empty($class) ? ' ' : '') . 'yw-btn';

        $datasize = '';
        if (preg_match('/\bmodalbox\b/i', $class)) {
            $datasize .= 'modal-lg';
        }

        $nobtn = $this->getService(PerformableArguments::class)->get('nobtn');
        if (!empty($nobtn) && $nobtn == '1') {
            $class = (string)preg_replace('/\byw-btn(?:--\w+)?\b/i', '', $class);
            $class = trim((string)preg_replace('/\s{2,}/', ' ', $class));
        }

        $hideIfNoAccess = $this->getService(PerformableArguments::class)->get('hideifnoaccess');
        if ($hideIfNoAccess == 'true' && isset($linkParts['tag']) && !$this->getService(AclService::class)->hasAccess('read', $linkParts['tag'])) {
            echo '';
        } elseif (empty($link)) {
            echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('TEMPLATE_ACTION_BUTTON') . '</strong> : ' . _t('TEMPLATE_LINK_PARAMETER_REQUIRED') . '.</div>' . "\n";
        } else {
            $btn = '<a'
                . ' href="' . $link . '"'
                . (!empty($class) ? ' class="' . $class . '"' : '')
                . (!empty($datasize) ? ' data-size="' . $datasize . '"' : '')
                . ((!empty($datasize) && empty($linkParts)) ? ' data-iframe="1"' : '')
                . (!empty($title) ? ' title="' . htmlentities($title, ENT_COMPAT, YW_CHARSET) . '"' : '');
            $btn .= '>' . $icon . (!empty($text) ? htmlentities($text, ENT_COMPAT, YW_CHARSET) : '') . '</a>';
            echo $btn;
        }
    }
}
