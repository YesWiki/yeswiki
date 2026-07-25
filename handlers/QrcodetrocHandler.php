<?php

use YesWiki\Core\YesWikiHandler;

class QrcodetrocHandler extends YesWikiHandler
{
    public function run()
    {
        // allow to pass get parameters, fallback on default config
        $relation = empty($_GET['relation']) ?
            $this->wiki->config['qrcode_config']['default_relation_type'] :
            $_GET['relation'];
        $formuser = empty($_GET['formuser']) ?
            $this->wiki->config['qrcode_config']['default_user_form'] :
            $_GET['formuser'];
        $refresh = empty($_GET['refresh']) ?
            $this->wiki->config['qrcode_config']['visualisation_refresh_period'] :
            $_GET['refresh'];
        $form = empty($_GET['form']) ?
            $this->wiki->config['qrcode_config']['relation_form_id'] :
            $_GET['form'];

        $output = '';
        // on recupere les entetes html mais pas ce qu'il y a dans le body
        $header = explode('<body', $this->wiki->Header());
        $output .= $header[0] . '<body>'."\n";
        $output .= '<main id="canvas-qrcodetroc" data-form="'.$form.'" data-formuser="'.$formuser.'" data-relation="'.$relation.'" data-refresh="'.$refresh.'"></main>';
        $this->wiki->addJavascriptFile('javascripts/vendor/p5.min.js');
        $this->wiki->addJavascriptFile('javascripts/qrcodetroc-visualisation.js');

        // on recupere juste les javascripts et la fin des balises body et html
        $output .= preg_replace('/^.+<script/Us', '<script', $this->wiki->Footer());
        return ($output);
    }
}
