<?php

/**
 *  Programme gerant les fiches bazar depuis une interface de type geographique.
 **/

// pour retro-compatibilité
$this->setParameter('template', 'map.tpl.html');
include( __DIR__.'/bazar_show.php');
