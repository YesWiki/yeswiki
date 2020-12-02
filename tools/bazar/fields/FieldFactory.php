<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

class FieldFactory
{
    public static function create(array $values, ContainerInterface $services)
    {
        switch ($values[0]) {
            case 'radio':
                return new RadioListField($values, $services);
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
            case 'labelhtml':
                return new LabelField($values, $services);
            case 'listedatedeb':
            case 'listedatefin':
            case 'jour':
                return new DateField($values, $services);
            case 'tags':
                return new TagsField($values, $services);
            case 'fichier':
                return new FileField($values, $services);
            case 'image':
                return new ImageField($values, $services);
            case 'yeswiki_user':
            case 'utilisateur_wikini':
                return new UserField($values, $services);
            default:
                return false;
//              throw new \Exception('Unknown field type: ' . $values[0]);
        }
    }
}
