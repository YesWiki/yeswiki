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
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\ThemeSelectorRenderer;

/** `{{themeselector}}` -- converted from the procedural actions/themeselector.php by ticket 06. */
class ThemeselectorAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'themeselector';
    }

    public function components(): array
    {
        return [
            Component::for('themeselector')
                ->category(Category::Admin)
                ->label(_t('AB_management_themeselector_label'))
                ->icon('brush')
                ->previewHeight('200px')
                ->adminOnly()
                ->settings(
                    Setting::text('class')
                        ->label(_t('AB_template_actions_class')),
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
        if (
            $this->getService(AclService::class)->isAdmin()
            && isset($_POST['action']) && ($_POST['action'] === 'setTemplate')
        ) {
            $this->getService(ActionRunner::class)->action('setwikidefaulttheme');

            $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', $this->getService(PageContext::class)->getTag()));
        } else {
            echo $this->getService(ThemeSelectorRenderer::class)->showFormThemeSelector('selector', $class);
        }
    }
}
