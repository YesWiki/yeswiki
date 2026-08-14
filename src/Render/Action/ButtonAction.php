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

/**
 * `{{button}}` -- converted from the procedural actions/button.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
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
            // Several of these bodies end in $this->exit(), which throws. The old
            // runFileInBuffer() accumulated output into a by-reference variable, so a throw
            // did not discard what had already been printed; keep that by flushing into the
            // shared output before rethrowing -- and close the buffer either way.
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        // adresse vers quoi le bouton pointe
        $link = $this->getService(PerformableArguments::class)->get('link');

        // extration du nom de 'root_page' si nécessaire
        if ($link == 'config/root_page') {
            $link = $this->getService(RuntimeConfig::class)['root_page'];
            $this->getService(PerformableArguments::class)->set('link', $link);
        }

        $linkParts = $this->getService(UrlFormatter::class)->extractLinkParts($link);
        if ($linkParts) {
            $link = $this->getService(UrlFormatter::class)->href($linkParts['method'], $linkParts['tag'], $linkParts['params']);
        }
        // change short yeswiki urls in real links
        $link = $this->getService(UrlFormatter::class)->generateLink($link);

        // texte genere a l'interieur du bouton
        $text = $this->getService(PerformableArguments::class)->get('text');

        // titre au survol du bouton et dans la boite modale associée
        $title = $this->getService(PerformableArguments::class)->get('title');

        // icone du bouton
        $icon = $this->getService(TemplateHelperService::class)->formatIconHtml($this->getService(PerformableArguments::class)->get('icon'));
        if (!empty($icon) && !empty($text)) {
            $icon = $icon . ' ';
        }

        // classe css supplémentaire pour changer le look
        $class = $this->getService(PerformableArguments::class)->get('class');
        $class .= (!empty($class) ? ' ' : '') . 'yw-btn';

        $datasize = '';
        if (preg_match('/\bmodalbox\b/i', $class)) {
            // if modalbox, set the big size
            $datasize .= 'modal-lg';
        }

        $nobtn = $this->getService(PerformableArguments::class)->get('nobtn');
        if (!empty($nobtn) && $nobtn == '1') {
            // remove all the yw-btn or yw-btn--* css class
            $class = preg_replace('/\byw-btn(?:--\w+)?\b/i', '', $class);
            // remove unneeded spaces
            $class = preg_replace('/(^\s*)|(\s*$)/', '', preg_replace('/\s{2,}/', ' ', $class));
        }

        $hideIfNoAccess = $this->getService(PerformableArguments::class)->get('hideifnoaccess');
        if ($hideIfNoAccess == 'true' && isset($linkParts['tag']) && !$this->getService(AclService::class)->hasAccess('read', $linkParts['tag'])) {
            echo '';
        } elseif (empty($link)) {
            echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('TEMPLATE_ACTION_BUTTON') . '</strong> : ' . _t('TEMPLATE_LINK_PARAMETER_REQUIRED') . '.</div>' . "\n";
        } else {
            $btn = '<a'
                . (!empty($link) ? ' href="' . $link . '"' : '')
                . (!empty($class) ? ' class="' . $class . '"' : '')
                . (!empty($datasize) ? ' data-size="' . $datasize . '"' : '')
                . ((!empty($datasize) && empty($linkParts)) ? ' data-iframe="1"' : '') // use iframe for external links in modalbox
                . (!empty($title) ? ' title="' . htmlentities($title, ENT_COMPAT, YW_CHARSET) . '"' : '');
            $btn .= '>' . $icon . (!empty($text) ? htmlentities($text, ENT_COMPAT, YW_CHARSET) : '') . '</a>';
            echo $btn;
        }
    }
}
