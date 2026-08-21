<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;

use function Symfony\Component\String\u;

use YesWiki\Kernel\Service\StringUtilService;

/**
 * A WikiName nothing else in the wiki holds.
 *
 * Accents stripped, punctuation dropped, words capitalised and joined, capped at 48 characters
 * so a collision suffix still fits, and at least two capitals so it reads as a WikiName. A name
 * already taken is retried as `NomWiki2`, `NomWiki3`, and so on.
 *
 * Was the global `generateWikiName()` in `Content/bazar.functions.php`, which recursed on itself
 * and reached the container to ask whether a page already existed (ticket 50).
 */
class WikiNameGenerator
{
    public function __construct(private readonly ContainerInterface $services)
    {
    }

    /**
     * @param string $name       the string to derive the name from
     * @param int    $occurrence recursion depth; 1 on the first call, 0 to skip the uniqueness
     *                           check entirely, and only >1 when retrying with a suffix
     */
    public function generate(string $name, int $occurrence = 1): string
    {
        // si la fonction est appelee pour la premiere fois, on nettoie le nom passe en parametre
        if ($occurrence <= 1) {
            // les noms wiki ne doivent pas depasser les 50 caracteres, on coupe a 48
            // histoire de pouvoir ajouter un chiffre derriere si nom wiki deja existant
            // plus traitement des accents et ponctuation
            // plus on met des majuscules au debut de chaque mot et on fait sauter les espaces
            $name = u($name)->ascii();
            $temp = StringUtilService::withoutDiacritics(mb_substr(preg_replace('/[[:punct:]]/', ' ', $name), 0, 47, YW_CHARSET));
            $temp = explode(' ', ucwords(strtolower($temp)));
            $name = '';
            foreach ($temp as $mot) {
                // on vire d'eventuels autres caracteres speciaux
                $name .= (string)preg_replace('/[^a-zA-Z0-9]/', '', trim($mot));
            }

            // on verifie qu'il y a au moins 2 majuscules, sinon on en rajoute une a la fin
            $var = (string)preg_replace('/[^A-Z]/', '', $name);
            if (strlen($var) < 2) {
                $last = ucfirst(substr($name, strlen($name) - 1));
                $name = substr($name, 0, -1) . $last;
            }

            $name = '';
            foreach ($temp as $mot) {
                // on vire d'eventuels autres caracteres speciaux
                $name .= (string)preg_replace('/[^a-zA-Z0-9]/', '', trim($mot));
            }

            // on verifie qu'il y a au moins 2 majuscules, sinon on en rajoute une a la fin
            $var = (string)preg_replace('/[^A-Z]/', '', $name);
            if (strlen($var) < 2) {
                $last = ucfirst(substr($name, strlen($name) - 1));
                $name = substr($name, 0, -1) . $last;
            }
        } elseif ($occurrence > 2) {
            // si on en est a plus de 2 occurences, on supprime le chiffre precedent et on ajoute la nouvelle occurence
            $nb = -1 * strlen(strval($occurrence - 1));
            $name = substr($name, 0, $nb) . $occurrence;
        } else {
            // cas ou l'occurence est la deuxieme : on reprend le NomWiki en y ajoutant le chiffre 2
            $name = $name . $occurrence;
        }

        if ($occurrence == 0) {
            // pour occurence = 0 on ne teste pas l'existance de la page
            return $name;
        } elseif (!is_array($this->services->get(PageManager::class)->getOne($name))) {
            // on verifie que la page n'existe pas deja : si c'est le cas on le retourne
            return $name;
        }
        // sinon, on rappele recursivement la fonction jusqu'a ce que le nom aille bien
        $occurrence++;

        return $this->generate($name, $occurrence);
    }
}
