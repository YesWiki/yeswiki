<?php
/**
 * SquelettePhp
 * 
 * Auteur d'origine : Brian Lozier
 * Source : http://www.massassi.com/php/articles/template_engines/
 */
if (! class_exists('SquelettePhp')) {

    class SquelettePhp
    {

        /**
         * Contient toutes les variables à insérer dans le squelette (template).
         *
         * @var array
         */
        protected $vars;

        /**
         * Le nom du fichier de squelette.
         *
         * @var string
         */
        protected $fichier;

        /**
         * Constructeur
         *
         * @param string|null $fichier
         *            le nom du fichier de template à charger.
         */
        public function __construct($fichier_tpl = null)
        {
            $this->fichier = $fichier_tpl;
        }

        /**
         */
        /**
         * Ajout une (string) ou plusieurs (array) variables nommées pour le squelette.
         *
         * @param mixed $nom
         *            Le nom de la variable, ou un tableau associatif nom=>valeur
         * @param mixed $valeur            
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
         * Ouvre, parse le template, remplace les variable, puis retourne le résultat.
         *
         * @param $fichier string
         *            le nom du fichier squelette.
         */
        public function analyser($fichier = null)
        {
            if (! $fichier)
                $fichier = $this->fichier;
            extract($this->vars); // Extrait les variables et les ajoutes à l'espace de noms local
            ob_start(); // Démarre le buffer
            include ($fichier); // Inclusion du fichier
            $contenu = ob_get_contents(); // Récupérer le contenu du buffer
            ob_end_clean(); // Arrête et détruit le buffer
            
            return $contenu; // Retourne le contenu
        }
    }
}
