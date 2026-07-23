<?php

use YesWiki\Core\YesWikiAction;

class ButtondropdownAction extends YesWikiAction
{
    public function run()
   {
       ob_start();

       // texte genere a l'interieur du bouton
       $text = $this->arguments['text'] ?? '';

       // titre au survol du bouton et dans la boite modale associée
       $title = $this->arguments['title'] ?? '';

       // mettre un petit triangle pour indiquer que c'est déroulant
       $caret = $this->arguments['caret'];
       if ($caret != '0') {
           $caret = '1';
       }

       $icon = trim($this->arguments['icon']);
       if (!empty($icon)) {
           // si le parametre contient des espaces, il s'agit d'une icone autre que celles par defaut de bootstrap

           if (preg_match('/\s/', $icon) === 1) {
               $icon = '<i class="' . $icon . '"></i>';
           } else {
               $icon = '<i class="icon-' . $icon . ' fa fa-' . $icon . '"></i>';
           }
           if (!empty($text)) {
               $icon = $icon . ' ';
           }
       }

       // classe css supplémentaire l'ensemble du
       $class = $this->arguments['class'] ?? '';

       // classe css supplémentaire pour changer le look des boutons
       $btnclass = $this->arguments['btnclass'] ?? ' btn-default';
       $btnclass = 'btn ' . $btnclass;


       $nobtn = $this->arguments['nobtn'] ?? '';
       if (!empty($nobtn) && $nobtn == '1') {
           $btnclass = str_replace(['btn ', 'btn-default'], ['', ''], $btnclass);
       }

        if ($this->check_end_elem('buttondropdown')) {
            $encodedtitle = htmlentities($title, ENT_COMPAT, YW_CHARSET);
            echo '<div class="btn-group' . (!empty($class) ? ' ' . $class : '') . '"> <!-- start of buttondropdown -->
            <button class="' . $btnclass . ' dropdown-toggle" data-toggle="dropdown" aria-label="' . $encodedtitle . '" title="' . $encodedtitle . '">
            ' . $icon . $text . (($caret == '1') ? ' <span class="caret"></span>' : '') . '
            </button>' . "\n";

        } else {
            echo $this->generate_error_msg('buttondropdown');
        }
       $buttondropdown = ob_get_contents();
       ob_end_clean();
       return $buttondropdown;
   }


   public function end(): string {
       return "\n</div> <!-- end of buttondropdown -->\n";
   }
}
