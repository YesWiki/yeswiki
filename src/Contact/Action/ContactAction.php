<?php

namespace YesWiki\Contact\Action;

use YesWiki\Contact\Service\MailForm;
use YesWiki\Contact\Service\MailFormCounter;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;

class ContactAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{contact}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'contact';
    }

    public function components(): array
    {
        return [
            Component::for('contact')
                ->category(Category::Forms)
                ->label(_t('AB_contact_action_label'))
                ->icon('mail')
                ->previewHeight('200px')
                ->settings(
                    Setting::email('mail')
                        ->label(_t('AB_contact_action_mail_label'))
                        ->suggests('my@email.com')
                        ->required(),
                    Setting::text('subjectprefix')
                        ->label(_t('AB_contact_action_entete_label'))
                        ->default(_t('AB_contact_action_entete_default')),
                    Setting::text('template')
                        ->label(_t('AB_contact_action_template_label'))
                        ->hint(_t('AB_contact_action_template_hint')),
                    Setting::text('class')
                        ->label(_t('AB_contact_action_class_label')),
                ),
        ];
    }

    public function formatArguments($arg)
    {
        $mailList = $this->formatArray($arg['mail'] ?? null);
        if (!empty($mailList)) {
            $mailList = $this->getService(MailForm::class)->resolveRecipients($mailList);
        }

        return [
            'fieldmapping' => $arg['fieldmapping'] ?? null,
            'mail' => $mailList,
            'subjectprefix' => $arg['subjectprefix'] ?? $this->getService(RuntimeConfig::class)['yeswiki_name'],
            'template' => $arg['template'] ?? 'complete-contact-form.twig',
            'class' => (!empty($arg['class']) ? 'form-contact ' . $arg['class'] : 'form-contact'),
        ];
    }

    public function run()
    {
        if (empty($this->arguments['mail'])) {
            return '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_CONTACT') . ' :</strong>&nbsp;' . _t('CONTACT_MAIL_REQUIRED') . '</div>';
        }

        $options = array_merge($this->arguments, [
            'nbactionmail' => $this->getService(MailFormCounter::class)->next(),
            'pageTag' => $this->getService(PageContext::class)->getTag(),
        ]);

        $this->getService(AssetRegistry::class)->addJsFile('javascripts/contact.js');

        return $this->render('@core/' . $this->arguments['template'], $options);
    }
}
