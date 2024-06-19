<?php

/******************************************************************************
*			DOCUMENTATION
*******************************************************************************
    RESUME
L'action {{attach}} permet de lier un fichier é une page, d'uploader ce fichier
et de downloader ce fichier. Si le fichier est une image, elle est affichée
dans la page. Lorsque le fichier est sur le serveur, il est possible de faire
une mise é jour de celui-ci.

    PARAMETRES DE L'ACTION
L'action {{attach}} prend les paramétres suivants :
 - file ou attachfile: nom du fichier tel qu'il sera sur le serveur. Les
   espaces sont remplacé par des "_". (OBLIGATOIRE)
 - desc ou attachdesc: description du fichier. C'est le texte qui sera affiché
   comme lien vers le fichier ou dans l'attribut alt de la balise <img>. Ce
   paramétre est obligatoire pour les images pour étre conforme au XHTML.
 - delete ou attachdelete: Si ce paramétre est non vide alors le fichier sera
   effacé sur le serveur.
 - link ou attachlink: URL de lien pour une image sensible. le lien peut étre
   un nom de page WikiNi, un lien interwiki ou une adresse http
 - class: indique le nom de la ou les classes de style é utiliser pour afficher
   l'image. les noms des classes sont séparés par un espace.

    EXEMPLES
 - Attacher un fichier archive:
        {{attach file="archive.zip"}}
 - Attacher un fichier archive avec une description
        {{attach file="archive.zip" desc="Code source de l'application"}}
 - Supprimer un fichier:
        {{attach file="archive.zip" delete="y"}}
 - Afficher une image:
        {{attach file="image.png" desc="voici une image"}}
 - Afficher une image sensible:
        {{attach file="image.png" desc="voici une image" link="PagePrincipale"}}
        {{attach file="image.png" desc="voici une image" link="WikiNi:PagePrincipale"}}
        {{attach file="image.png" desc="voici une image" link="http://www.wikini.net"}}
 - Afficher une image collé sur le bord droit et sans contour:
        {{attach file="image.png" desc="voici une image" class="right noborder"}}

    INSTALLATION
1) Copiez le fichier attach.php dans le répertoire des actions (/actions)
2) Copiez le fichier attach.lib.php dans le répertoire des actions (/actions)
3) Copiez le fichier attachfm.php dans le repertoire des actions (/actions)
4) Copiez le fichier filamanager.php dans le répertoire des handlers (/handlers/page)
5) Copiez le fichier upload.php dans le répertoire des handlers (/handlers/page)
6) Copiez le fichier download.php dans le répertoire des handlers (/handlers/page)
7) Créez le répertoire racine des uploads sur le site du wiki. Si le SAFE_MODE
    de PHP est activé, vous devez créer vous méme ce répertoire et autoriser
    l'écriture dans ce répertoire pour l'utilisateur et le groupe.
8) Ouvrez le fichier wakka.config.php et ajoutez la configuration de l'action.
    Tous les paramétres de configuration ont une valeur par défaut.
    Le configuration par défaut est:

    $wakkaConfig["attach_config"] = array(
            "upload_path" => 'files',				//repertoire racine des uploads
            "ext_images" => 'gif|jpeg|png|jpg',	//extension des fichiers images
            "ext_script" => 'php|php3|asp|asx|vb|vbs|js',	//extension des script(non utilisé)
            "update_symbole" => '*',				//symbole pour faire un update du fichier
            "max_file_size" => 1024*2000,			//taille maximum du fichier en octer (2M par défaut)
            "fmDelete_symbole" => 'Supr',			//symbole a afficher pour le lien "supprimer" dans le gestionnaire de fichier
            "fmRestore_symbole" => 'Rest',		//symbole a afficher pour le lien "restaurer" dans le gestionnaire de fichier
            "fmTrash_symbole" => 'Poubelle')		//symbole a afficher pour le lien "Poubelle" dans le gestionnaire de fichier

9) Ajoutez les classes de style au fichier wakka.css. Exemple de style :
.attach_margin05em { margin: 0.5em;}
.attach_margin1em { margin: 1em;}
.attach_left {float: left;}
.attach_right {float: right;}
.attach_noborder {border-width: 0px;}
.attach_vmiddle {vertical-align: text-bottom;}

10)Pour configurer l'aspect du gestionnnaire de fichier utiliser les classes de style .tableFM
tableFMCol1 et tableFMCol2
Exemple :
.tableFM {border: thin solid Black; width: 100%;  }
.tableFM THEAD { background-color: Silver; font-weight: bold; text-align: center;   }
.tableFM TFOOT { background-color: Silver; font-weight: bold; text-align: left;   }
.tableFM TBODY TR { text-align: center;  }
.tableFMCol1 { background-color: Aqua; }
.tableFMCol2 { background-color: Yellow; }
*******************************************************************************/

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

if (!class_exists('attach')) {
    include 'tools/attach/libs/attach.lib.php';
}

$att = new attach($this);
$att->doAttach();
unset($att);
