<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"whiteboard"})
 */
class WhiteBoardField extends BazarField
{    
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

    }

    protected function renderInput($entry)
    {
        $wiki = $this->getWiki();
        if ($this->getWiki()->GetMethod() != 'bazariframe') {
            $whiteboardUrl = "http://localhost:5001/boards/test1";
            return $this->render("@bazar/inputs/whiteboard.twig", [
                'iframeUrl' => $whiteboardUrl,
                'iframeParams' => [
                    'width' => '100%',
                    'height' => '400px',
                ]
            ]);
        }
    }

    protected function renderStatic($entry)
    {
        if ($this->getWiki()->GetMethod() == 'bazariframe') {
            return '<a class="btn btn-danger pull-right" href="javascript:window.close();"><i class="fa fa-remove icon-remove icon-white"></i>&nbsp;' . _t('BAZ_CLOSE_THIS_WINDOW') . '</a>';
        }
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'urlField' => $this->getUrlField(),
            ]
        );
    }
}