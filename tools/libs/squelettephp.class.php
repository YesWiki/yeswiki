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
            if (! $fichier) {
                $fichier = $this->fichier;
            }

            // TODO : faire en sorte que les templates puisse etre mis en cache et voir que faire du js et css
            // // on efface les fichier du cache qui sont plus vieux que 5 minutes
            // foreach (glob('cache/'.$GLOBALS['wiki']->getPageTag().'-'.basename($fichier).'-*') as $filename) {
            //     if (filemtime($filename) < (time() - 5 * 60) or (isset($_GET['refresh']) and $_GET['refresh'] == '1')) {
            //         unlink($filename);
            //     }
            // }
            // // generation d'un marqueur pour identifier le fichier template a partir de ses valeurs
            // $id = '';
            // foreach (array_keys($this->vars) as $key => $value) {
            //     if (is_array($this->vars[$value])) {
            //         $id .= $value.count($this->vars[$value]);
            //     } else {
            //         $id .= $value.$this->vars[$value];
            //     }
            // }
            // //var_dump($id);
            // $cachedFile = 'cache/'.$GLOBALS['wiki']->getPageTag().'-'.basename($fichier).'-'.md5($id);
            // if (file_exists($cachedFile)) {
            //     return file_get_contents($cachedFile);
            // } else {
                extract($this->vars); // Extrait les variables et les ajoutes à l'espace de noms local
                ob_start(); // Démarre le buffer
                include($fichier); // Inclusion du fichier
                $contenu = ob_get_contents(); // Récupérer le contenu du buffer
                ob_end_clean(); // Arrête et détruit le buffer
            //    file_put_contents($cachedFile, $contenu);
                return $contenu; // Retourne le contenu
            // }
        }
    }
}
