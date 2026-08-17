<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;

class LabelAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{label}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'label';
    }

    public function components(): array
    {
        return [
            Component::for('label')
                ->category(Category::Writing)
                ->label(_t('AB_template_action_label_label'))
                ->icon('tags')
                ->previewHeight('300px')
                ->wraps(_t('AB_template_action_label_example'))
                ->settings(
                    Setting::cssClass('class')
                        ->label(_t('AB_template_actions_class'))
                        ->subSettings(
                            Setting::choice('label', [
                                'label-primary' => _t('AB_template_actions_primary'),
                                'label-secondary-1' => _t('AB_template_actions_secondary_1'),
                                'label-secondary-2' => _t('AB_template_actions_secondary_2'),
                                'label-success' => _t('AB_template_actions_success'),
                                'label-info' => _t('AB_template_actions_info'),
                                'label-warning' => _t('AB_template_actions_warning'),
                                'label-danger' => _t('AB_template_actions_danger'),
                            ])
                            ->label(_t('AB_template_actions_color')),
                        ),
                ),
        ];
    }

    public function run()
    {
        ob_start();
        $id = $this->arguments['id'] ?? '';

        $class = $this->arguments['class'] ?? 'label-default';
        if ($this->check_end_elem('label')) {
            echo '<!-- start of label -->' . "\n" .
            '<span' . (!empty($id) ? ' id="' . $id . '"' : '') . ' class="yw-label ' . $class
                . '">';
        } else {
            echo $this->generate_error_msg('label');
        }
        $label = ob_get_contents();
        ob_end_clean();

        return $label;
    }

    public function end(): string
    {
        return '</span>';
    }
}
