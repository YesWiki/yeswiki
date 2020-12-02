<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

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
    }

    protected function renderInput($entry)
    {
        if ($_GET['wiki'] != $GLOBALS['wiki']->getPageTag().'/iframe') {
            $id = isset($GLOBALS['params']['idtypeannonce']) ? $GLOBALS['params']['idtypeannonce'] : $entry['id_typeannonce'];
            $urlParams = 'vue='.BAZ_VOIR_SAISIR.'&action='.BAZ_ACTION_NOUVEAU.'&id='.$id;
            $url = $GLOBALS['wiki']->href('iframe', $GLOBALS['wiki']->getPageTag(), $urlParams);

            return $this->render("@bazar/inputs/bookmarklet.twig", [
                'url' => $url
            ]);
        }
    }  

    public function renderStatic($entry)
    {
        if ($GLOBALS['wiki']->GetMethod() == 'iframe') {
            return '<a class="btn btn-danger pull-right" href="javascript:window.close();"><i class="fa fa-remove icon-remove icon-white"></i>&nbsp;Fermer cette fen&ecirc;tre</a>';
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
}
