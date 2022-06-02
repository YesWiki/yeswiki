<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"bookmarklet"})
 */
class BookmarkletField extends BazarField
{
    protected $urlField;
    protected $descriptionField;

    protected const FIELD_URL_FIELD = 3;
    protected const FIELD_DESCRIPTION_FIELD = 4;
    
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->urlField = $values[self::FIELD_URL_FIELD] ?? 'bf_url';
        $this->descriptionField = $values[self::FIELD_DESCRIPTION_FIELD] ?? 'bf_description';
        $this->size = null;
        $this->maxChars = null;
    }

    protected function renderInput($entry)
    {
        $wiki = $this->getWiki();
        if ($this->getWiki()->GetMethod() != 'bazariframe') {
            return $this->render("@bazar/inputs/bookmarklet.twig", [
                'urlParams' => [
                    'vue' => BAZ_VOIR_SAISIR,
                    'action' => BAZ_ACTION_NOUVEAU,
                    'id' => $entry['id_typeannonce'] ?? "_",
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

    // GETTERS. Needed to use them in the Twig syntax

    public function getUrlField()
    {
        return $this->urlField;
    }

    public function getDescriptionField()
    {
        return $this->descriptionField;
    }

    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'urlField' => $this->getUrlField(),
                'descriptionField' => $this->getDescriptionField()
            ]
        );
    }
}
