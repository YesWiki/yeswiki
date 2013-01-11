<?php
// Auteur d'origine : Brian Lozier
// Source : http://www.massassi.com/php/articles/template_engines/

if (!class_exists('SquelettePhp')) {
    class SquelettePhp
    {
        private $vars; // Contient toutes les variables � ins�rer dans le squelette

        /**
        * Constructeur
        *
        * @param $fichier string le nom du fichier de template � charger.
        */
        public function __construct($fichier_tpl = null)
        {
            $this->fichier = $fichier_tpl;
        }

        /**
        * Ajout une variable pour le squelette.
        */
        public function set($nom, $valeur = null)
        {
            if (is_null($valeur) && is_array($nom)) {
                $this->vars = $nom;
            } elseif ($valeur instanceof SquelettePhp) {
                $this->vars[$nom] = $valeur->analyser();
            } else {
                $this->vars[$nom] = $valeur;
            }
        }

        /**
        * Ouvre, parse, and retourne le squelette.
        *
        * @param $fichier string le nom du fichier squelette.
        */
        public function analyser($fichier = null)
        {
            if(!$fichier) $fichier = $this->fichier;
            extract($this->vars);          // Extrait les variables et les ajoutes � l'espace de noms local
            ob_start();                    // D�marre le buffer
            include($fichier);             // Inclusion du fichier
            $contenu = ob_get_contents();  // R�cup�rer le  contenu du buffer
            ob_end_clean();                // Arr�te et d�truit le buffer

            return $contenu;               // Retourne le contenu
        }
    }
}
