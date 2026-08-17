<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\AssetRegistry;

abstract class RadioField extends EnumField
{
    protected $displayMethod;
    protected $displayFilterLimit;

    protected const FIELD_DISPLAY_METHOD = 7;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->displayMethod = $values[self::FIELD_DISPLAY_METHOD];
        $params = $this->services->get(ParameterBagInterface::class);
        $this->displayFilterLimit = $params->has('BAZ_MAX_RADIO_WITHOUT_FILTER') ? $params->get('BAZ_MAX_RADIO_WITHOUT_FILTER') : false;
    }

    protected function renderInput($entry)
    {
        switch ($this->displayMethod) {
            case 'tags':
                $htmlReturn = $this->render('@core/inputs/radio_tags.twig', [
                    'tagsData' => $this->generateTagsData($entry),
                ]);

                return $htmlReturn;
            default:
                $options = $this->getOptions();
                if ($this->displayFilterLimit && (count($options) > $this->displayFilterLimit)) {
                    $this->getService(AssetRegistry::class)->addJsFile('javascripts/inputs/filter-entries.js');
                }

                return $this->render('@core/inputs/radio.twig', [
                    'options' => $options,
                    'value' => $this->getValue($entry),
                    'displayFilterLimit' => $this->displayFilterLimit,
                ]);
        }
    }

    private function generateTagsData($entry)
    {
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
