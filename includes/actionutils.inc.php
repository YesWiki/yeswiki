<?php
/*
Some usefull functions to deal with actions

@Author : Yves Gufflet contact@yvesgufflet.fr

Date : 23/07/2025
*/

/**
 * Replace or add a parameter and its value to all given action present in a text
 * ex :
 *	setActionParameter (
 *		"...{{ login incomingurl="?toto" }}...{{ login incomingurl="?tata" }}...",
 *		"login",
 *		"incomingurl",
 *		"?titi")
 *	returns :
 *		"...{{ login incomingurl="?titi" }}...{{ login incomingurl="?titi" }}..."
 *
 * @return the new action string
 */

function setActionParameter ($pBody, $pAction, $pParameter, $pValue)
{
    // Match tous les blocs {{ ... }}
    return preg_replace_callback('/{{[[:blank:]]*\b' . $pAction .'\b.*?}}/mi', function ($vMatch) use ($pAction, $pParameter, $pValue)
    {

        $vContenuBloc = trim($vMatch[0]);

        // Vérifie si le parametre est déjà présent

        if (preg_match('/\b' . $pParameter . '[[:blank:]]*=[[:blank:]]*([\'"])(.*?)\1/mi', $vContenuBloc))
        {
            // Remplace l’ancienne valeur par la nouvelle
            $vContenuModifie = preg_replace(
                '/\b' . $pParameter . '[[:blank:]]*=[[:blank:]]*([\'"])(.*?)\1/mi',
                $pParameter . '="' . $pValue . '"',
                $vContenuBloc
            );
        } else {
            // Ajouter le paramètre après l'action
			
			$vContenuModifie = preg_replace ('/{{[[:blank:]]*\b' . $pAction . '\b(.*?)}}/mi', '{{ ' . $pAction . ' ' . $pParameter . '="' . $pValue . '"\1 }}' , $vContenuBloc);
        }

        return $vContenuModifie;
    }, $pBody);
    
    return $pBody;
}

