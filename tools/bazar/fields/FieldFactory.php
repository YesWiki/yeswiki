<?php

namespace YesWiki\Bazar\Field;

class FieldFactory
{
//    protected $formManager;
//
//    public function __construct(FormManager $formManager)
//    {
//        // TODO pass
//        $this->formManager = $formManager;
//    }

    public static function create(array $values)
    {
        switch ($values[0]) {
            case 'radio':
                return new RadioField($values);
            default:
                return false;
//                throw new \Exception('Unknown field type: ' . $values[0]);
        }
    }
}
