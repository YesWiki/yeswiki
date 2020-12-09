<?php

namespace YesWiki\Bazar\Controller;

use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api/form")
     */
    public function getAllForms()
    {
        return $this->getService(FormManager::class)->getAll();
    }

    /**
     * @Route("/api/form/{id}")
     */
    public function getOneForm($id)
    {
        return $this->getService(FormManager::class)->getOne($id);
    }
}
