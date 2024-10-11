<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Wiki;

abstract class RadioField extends EnumField
{
    protected $displayMethod; // empty, tags
    protected $displayFilterLimit; // number of items without filter ; false if no limit
    protected $wiki;

    protected const FIELD_DISPLAY_METHOD = 7;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->displayMethod = $values[self::FIELD_DISPLAY_METHOD];
        $this->wiki = $this->services->get(Wiki::class);
        $params = $this->services->get(ParameterBagInterface::class);
        $this->displayFilterLimit = $params->has('BAZ_MAX_RADIO_WITHOUT_FILTER') ? $params->get('BAZ_MAX_RADIO_WITHOUT_FILTER') : false;
    }

    protected function renderInput($entry)
    {
        switch ($this->displayMethod) {
            case 'tags':
                $htmlReturn = $this->render('@bazar/inputs/radio_tags.twig', [
                    'bazarlistTagsInputsData' => json_encode($this->generateTagsData($entry)),
                ]);

                return $htmlReturn;
                break;
            default:
                $options = $this->getOptions();
                if ($this->displayFilterLimit && (count($options) > $this->displayFilterLimit)) {
                    // javascript additions
                    $this->wiki->AddJavascriptFile('tools/bazar/libs/vendor/jquery.fastLiveFilter.js');
                    $script = "$(function() { $('.filter-entries').each(function() {
                                $(this).fastLiveFilter($(this).siblings('.bazar-radio-rows')); });
                            });";
                    $this->wiki->AddJavascript($script);
                }

                return $this->render('@bazar/inputs/radio.twig', [
                    'options' => $options,
                    'value' => $this->getValue($entry),
                    'displayFilterLimit' => $this->displayFilterLimit,
                ]);
        }
    }

    private function generateTagsData($entry)
    {
        // list of choices available from options
        $existingTags = [];
        foreach ($this->getOptions() as $key => $label) {
            $existingTags[$key] = [
                'id' => $key,
                'title' => $label,
            ];
        }

        $selectedOption = $this->getValue($entry);
        $selectedOptions = empty($selectedOption) ? [] : [$selectedOption];

        return [
            'existingTags' => $existingTags,
            'selectedOptions' => $selectedOptions,
            'limit' => 1,
        ];
    }
}
