<?php

namespace YesWiki\HelloWorld\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Field\BazarField;

/**
 * Display an alert box with the text given in the second row
 * alerte***Warning, you are watching a very important page!***.
 *
 * @Field({"alerte"})
 */
class AlertField extends BazarField
{
    protected $alertText;

    protected const FIELD_ALERT_TEXT = 1;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->alertText = $values[self::FIELD_ALERT_TEXT];
    }

    protected function renderInput($entry)
    {
        // No input need to be displayed for this example field
        return null;
    }

    public function formatValuesBeforeSave($entry)
    {
        // Here you can perform operations on each create/update operation

        // Return the values you want to be saved in the entry
        return ['alert' => _t('HELLOWORLD_FIELD_ALERT')];
    }

    protected function renderStatic($entry)
    {
        return $this->render('@helloworld/alert-field.twig', [
            'alertText' => $this->alertText,
        ]);
    }
}
