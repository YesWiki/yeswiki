<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Render\Service\LanguageSwitch;
use YesWiki\Render\Service\TemplateEngine;

/** `{{languages}}` -- the language switch, put where an author wants it rather than only in the chrome. */
class LanguagesAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'languages';
    }

    public function components(): array
    {
        return [
            Component::for('languages')
                ->category(Category::Navigation)
                ->label(_t('AB_languages_action_label'))
                ->icon('world')
                ->previewHeight('80px')
                ->settings(
                    Setting::cssClass('class'),
                ),
        ];
    }

    public function run(): string
    {
        $switch = $this->getService(LanguageSwitch::class);
        if (!$switch->isOffered()) {
            return '';
        }

        return $this->getService(TemplateEngine::class)->render('@core/layout/_language-switch.twig', [
            'languages' => $switch->readingOptions(),
            'class' => (string)$this->getService(PerformableArguments::class)->get('class'),
        ]);
    }
}
