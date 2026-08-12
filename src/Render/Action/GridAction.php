<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;

class GridAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{grid}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'grid';
    }

    public function components(): array
    {
        return [
            Component::for('grid')
                ->category(Category::Writing)
                ->label(_t('AB_template_action_grid_label'))
                ->icon('columns')
                ->previewHeight('300px')
                ->wraps(_t('AB_template_action_grid_example'))
                ->addOnly()
                ->settings(
                    Setting::choice('nb', [
                        2,
                        3,
                        4,
                        6,
                        12,
                    ])
                        ->label(_t('AB_template_action_grid_nb'))
                        ->hint(_t('AB_template_action_grid_nb_hint'))
                        ->suggests(2)
                        ->notWritten(),
                ),
        ];
    }

    public function run()
    {
        ob_start();
        $class = $this->arguments['class'] ?? '';
        $class = 'yw-row' . ((!empty($class)) ? ' ' . $class : '');
        if ($this->check_end_elem('grid')) {
            echo '<!-- start of grid -->' . "\n" .
            '<div class="' . $class . '">';
        } else {
            echo $this->generate_error_msg('grid');
        }
        $col = ob_get_contents();
        ob_end_clean();

        return $col;
    }

    public function end(): string
    {
        return "\n</div> <!-- end of grid -->\n";
    }
}
