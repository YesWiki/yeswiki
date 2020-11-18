<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

class FieldFactory
{
    public static function create(array $values, ContainerInterface $services)
    {
        switch ($values[0]) {
            case 'radio':
                return new RadioField($values, $services);
            case 'texte':
                return new TextField($values, $services);
            default:
                return false;
//              throw new \Exception('Unknown field type: ' . $values[0]);
        }
    }
}
