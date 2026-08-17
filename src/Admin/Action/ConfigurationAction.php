<?php

namespace YesWiki\Admin\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\ThemeManager;

/** `{{configuration}}` -- converted from the procedural actions/configuration.php by ticket 06. */
class ConfigurationAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'configuration';
    }

    public function components(): array
    {
        return [
            Component::for('configuration')
                ->category(Category::Admin)
                ->label(_t('AB_advanced_action_configuration_label'))
                ->icon('settings')
                ->previewHeight('200px')
                ->settings(
                    Setting::choice('param', [
                        'root_page',
                        'base_url',
                        'navigation_links',
                        'meta_keywords',
                        'meta_description',
                        'yeswiki_name',
                        'yeswiki_release',
                        'yeswiki_version',
                        'favorite_theme',
                        'favorite_style',
                        'favorite_squelette',
                        'default_language',
                        'charset',
                        'lang',
                        'theme_path',
                        'wakka_version',
                    ])
                        ->label('param')
                        ->required(),
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
        $themeManager = $this->getService(ThemeManager::class);

        $param = $this->getService(PerformableArguments::class)->get('param');
        if (!empty($param)) {
            if ($param === 'wakka_name') {
                $param = 'yeswiki_name';
            }
            switch ($param) {
                case 'yeswiki_version':
                case 'yeswiki_release':
                case 'root_page':
                case 'yeswiki_name':
                case 'base_url':
                case 'navigation_links':
                case 'meta_keywords':
                case 'meta_description':
                case 'favorite_theme':
                case 'favorite_style':
                case 'favorite_squelette':
                case 'default_language':
                case 'charset':
                    echo htmlentities($this->getService(RuntimeConfig::class)[$param], ENT_QUOTES, YW_CHARSET);
                    break;
                case 'lang':
                    echo $GLOBALS['prefered_language'];
                    break;
                case 'theme_path':
                    $theme = $themeManager->getFavoriteTheme();
                    echo (is_dir('custom/themes/' . $theme)) ?
                        "custom/themes/$theme/" :
                        "themes/$theme/";
                    break;
                default:
                    break;
            }
        }
    }
}
