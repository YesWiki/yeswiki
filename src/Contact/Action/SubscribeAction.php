<?php

namespace YesWiki\Contact\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Render\Service\TemplateEngine;

/** `{{subscribe}}` -- converted from the procedural actions/abonnement.php by ticket 06. */
class SubscribeAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'subscribe';
    }

    public function components(): array
    {
        return [
            Component::for('subscribe')
                ->category(Category::Forms)
                ->label(_t('AB_abonnement_action_label'))
                ->icon('mail-forward')
                ->previewHeight('200px')
                ->settings(
                    Setting::email('mail')
                        ->label(_t('AB_abonnement_action_mail_label'))
                        ->suggests('my@mailing.list')
                        ->required(),
                    Setting::choice('mailinglist', [
                        'sympa',
                        'ezmlm',
                    ])
                        ->label(_t('AB_abonnement_mailinglist_label')),
                    Setting::text('nbactionmail')
                        ->label(_t('AB_abonnement_template_label')),
                    Setting::text('class')
                        ->label(_t('AB_abonnement_class_label')),
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
        $templateVars['mail'] = $this->getService(PerformableArguments::class)->get('mail');
        if (empty($templateVars['mail'])) {
            echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_ABONNEMENT') . ' :</strong>&nbsp;' . _t('CONTACT_MAIL_REQUIRED') . '</div>';
        } else {
            if (isset($GLOBALS['nbactionmail'])) {
                $GLOBALS['nbactionmail']++;
            } else {
                $GLOBALS['nbactionmail'] = 1;
            }
            $templateVars['nbactionmail'] = $GLOBALS['nbactionmail'];

            $template = $this->getService(PerformableArguments::class)->get('template');
            if (empty($template)) {
                $template = 'subscribe-form.twig';
            }

            $templateVars['class'] = ($this->getService(PerformableArguments::class)->get('class') ? 'form-abonnement ' . $this->getService(PerformableArguments::class)->get('class') : 'form-abonnement');

            $templateVars['hiddeninputs'] = '';

            $mailinglist = $this->getService(PerformableArguments::class)->get('mailinglist');
            if (!empty($mailinglist) and ($mailinglist == 'ezmlm' or $mailinglist == 'sympa')) {
                $templateVars['hiddeninputs'] .= '<input type="hidden" name="mailinglist" value="' . $mailinglist . '">';
            }

            $templateVars['pageTag'] = $this->getService(PageContext::class)->getTag();

            $templateVars['demand'] = 'subscribe';
            $templateVars['placeholder'] = _t('CONTACT_SUBSCRIBE');

            echo $this->getService(TemplateEngine::class)->renderSafely("@core/$template", $templateVars);

            $this->getService(AssetRegistry::class)->addJsFile('javascripts/contact.js');
        }
    }
}
