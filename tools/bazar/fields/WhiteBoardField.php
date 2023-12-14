<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 *  WhiteBoardField - Custom Bazar Field for Collaborative Whiteboard Integration
 *
 * @Field({"whiteboard"})
 */
class WhiteBoardField extends BazarField
{    
    // Properties to store whiteboard URL and entry ID
    protected $whiteboardUrl;
    protected $entryId;

    /**
     * Constructor
     *
     * @param array $values   - Field values
     * @param ContainerInterface $services - Service container
     */
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

    }

    /**
     * Render input in edit mode
     *
     * @param mixed $entry - Bazar entry data
     */
    protected function renderInput($entry)
    {
        // Si la méthode du wiki n'est pas 'bazariframe', ne rien renvoyer
        if ($this->getWiki()->GetMethod() != 'bazariframe') {
            return ;
        }
    }

    /**
     * Render static content
     *
     * @param mixed $entry - Bazar entry data
     */
    protected function renderStatic($entry)
    {
        // Check if the wiki method is 'bazariframe'
        if ($this->getWiki()->GetMethod() == 'bazariframe') {
            return '<a class="btn btn-danger pull-right" href="javascript:window.close();"><i class="fa fa-remove icon-remove icon-white"></i>&nbsp;' . _t('BAZ_CLOSE_THIS_WINDOW') . '</a>';
        }
        // Return the rendered Twig template for the whiteboard iframe
        if ($this->getWiki()->GetMethod() != 'bazariframe') {
            $entryId = $entry['id_fiche'];
            $whiteboardUrl = "https://wbo.ophir.dev/boards/". $entryId;
            return $this->render("@bazar/inputs/whiteboard.twig", [
                'iframeUrl' => $whiteboardUrl,
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