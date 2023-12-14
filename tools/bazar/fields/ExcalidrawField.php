<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

use Ramsey\Uuid\Uuid;
use UUID as GlobalUUID;

/**
 * @Field({"excalidraw"})
 */
class ExcalidrawField extends BazarField
{
    protected $excalidrawUrl;
    protected $entryId;
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
    }

    protected function renderInput($entry)
    {
        if ($this->getWiki()->GetMethod() != 'bazariframe') {
            return;
        }
    }

    protected function renderStatic($entry)
    {

        if ($this->getWiki()->GetMethod() == 'bazariframe') {
            return '<a class="btn btn-danger pull-right" href="javascript:window.close();"><i class="fa fa-remove icon-remove icon-white"></i>&nbsp;' . _t('BAZ_CLOSE_THIS_WINDOW') . '</a>';
        }

        if ($this->getWiki()->GetMethod() != 'bazariframe') {
            $entryId = $entry['id_fiche'];

            $excalidrawUrl =
                $this->getWiki()->config['excalidraw_url'] . $entryId;
            return $this->render("@bazar/inputs/excalidraw.twig", [
                'iframeUrl' => $excalidrawUrl,
                'iframeParams' => [
                    'width' => '100%',
                    'height' => '400px',
                ]
            ]);
        }
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'default' => $this->getDefault(),
            ]
        );
    }
}
