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
            case 'mot_de_passe':
                return new PasswordField($values, $services);
            case 'champs_mail':
                return new EmailField($values, $services);
            case 'lien_internet':
                return new LinkField($values, $services);
            case 'champs_cache':
                return new HiddenField($values, $services);
            default:
                return false;
//              throw new \Exception('Unknown field type: ' . $values[0]);
        }
    }
}
