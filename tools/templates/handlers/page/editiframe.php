<?php

use YesWiki\Bazar\Service\EntryManager;

$entryManager = $this->services->get(EntryManager::class);

if ($this->HasAccess('write')) {
    if ($entryManager->isEntry($this->GetPageTag())) {
        // dans le cas ou on vient de modifier dans le formulaire une fiche bazar, on enregistre les modifications
        if (isset($_POST['bf_titre'])) {
            baz_formulaire(BAZ_ACTION_MODIFIER_V, $this->href('iframe'), $_POST);
        } else {
            $fiche = $entryManager->getOne($this->GetPageTag());
            $pageeditionfiche = baz_formulaire(
                BAZ_ACTION_MODIFIER,
                $this->href('editiframe'),
                $fiche
            );
            $buffer = $pageeditionfiche;
        }
    } else {
        ob_start();
        echo $this->Run($this->getPageTag(), 'edit');
        $buffer = ob_get_contents();
        ob_end_clean();
    }

    $output = '';
    // on recupere les entetes html mais pas ce qu'il y a dans le body
    $header = explode('<body', $this->Header());
    $output .= $header[0].'<body class="iframe-body">'."\n"
        .'<div class="yeswiki-page-widget page">'."\n";

    // on replace la méthode d'edition classique pour mettre celle de l'édition en iframe
    $buffer = str_replace(
        'action="'.$this->href('edit', $this->getPageTag()).'"',
        'action="'.$this->href('editiframe', $this->getPageTag()).'"',
        $buffer
    );
    $buffer = str_replace(
        'onclick="location.href=\''.$this->href('', $this->getPageTag()),
        'onclick="location.href=\''.$this->href('iframe', $this->getPageTag()),
        $buffer
    );
    $output .= str_replace(
        'value="'.$this->getPageTag().'/edit"',
        'value="'.$this->getPageTag().'/editiframe"',
        $buffer
    );

    $output .= '</div><!-- end div.page-widget -->'."\n";
} else {
    $output = '';
    // on recupere les entetes html mais pas ce qu'il y a dans le body
    $header = explode('<body', $this->Header());
    $output .= $header[0] . '<body class="login-body">'."\n"
    .'<div class="yeswiki-page-widget page-widget page">'."\n"
    .'<div class="alert alert-danger alert-error">'
    ._t('LOGIN_NOT_AUTORIZED_EDIT').'. '._t('LOGIN_PLEASE_REGISTER').'.'
    .'</div><!-- end .alert -->'."\n"
    .$this->Format('{{login signupurl="0"}}'."\n\n")
    .'</div><!-- end .page -->'."\n";
}

$this->addJavascriptFile('tools/bazar/libs/bazar.js');
$this->addJavascriptFile('tools/templates/libs/vendor/iframeResizer.contentWindow.min.js');
// on recupere juste les javascripts et la fin des balises body et html
$output .= preg_replace('/^.+<script/Us', '<script', $this->Footer());
echo $output;
