<?php

namespace YesWiki\Bazar\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
        return new JsonResponse($this->getService(FormManager::class)->getAll());
    }

    /**
     * @Route("/api/form/{id}")
     */
    public function getOneForm($id)
    {
        $form = $this->getService(FormManager::class)->getOne($id);
        if( !$form ) throw new NotFoundHttpException();
        return new JsonResponse($form);
    }
}
