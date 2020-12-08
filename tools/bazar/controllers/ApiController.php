<?php

namespace YesWiki\Bazar\Controller;

use Symfony\Component\Routing\Annotation\Route;

class ApiController
{
    /**
     * @Route("/api/form")
     */
    public function getAllForms()
    {
        return baz_valeurs_formulaire('');
    }

    /**
     * @Route("/api/form/{id}")
     */
    public function getOneForm($id)
    {
        return baz_valeurs_formulaire($id);
    }
}
