<?php
/**
* calendrier : programme affichant les evenements du bazar sous forme de Calendrier dans wikini
*
*
* @package Bazar
*
* @author        Florian SCHMITT <florian@outils-reseaux.org>
* @version       $Revision: 1.1 $ $Date: 2011-03-22 09:33:24 $
*
*/

if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}

// recuperation des parametres de l'action
$categorie_nature = $this->GetParameter("categorienature");
if (!empty($categorie_nature)) {
    $GLOBALS['_BAZAR_']['categorie_nature'] = $categorie_nature;
}
// si rien n'est donne, on affiche toutes les categories
else {
    $GLOBALS['_BAZAR_']['categorie_nature'] = 'toutes';
}
$id_typeannonce = $this->GetParameter("idtypeannonce");
if (!empty($id_typeannonce)) {
    $GLOBALS['_BAZAR_']['id_typeannonce'] = $id_typeannonce;
}
// si rien n'est donne, on affiche toutes les annonces
else {
    $GLOBALS['_BAZAR_']['id_typeannonce'] = 'toutes';
}

// on recupere les parametres pour une requete specifique
$query = $this->GetParameter("query");
if (!empty($query)) {
    $tabquery = array();
    $tableau = array();
    $tab = explode('|', $query);
    foreach ($tab as $req) {
        $tabdecoup = explode('=', $req, 2);
        $tableau[$tabdecoup[0]] = trim($tabdecoup[1]);
    }
    $tabquery = array_merge($tabquery, $tableau);
} else {
    $tabquery = '';
}

$minical = $this->GetParameter('minical');

$tableau_resultat = baz_requete_recherche_fiches($tabquery, '', $GLOBALS['_BAZAR_']['id_typeannonce'], $GLOBALS['_BAZAR_']['categorie_nature']);
$js = '';
foreach ($tableau_resultat as $fiche) {
    $valeurs_fiche = json_decode($fiche["body"], true);
    if (YW_CHARSET != 'UTF-8') $valeurs_fiche = array_map('utf8_decode', $valeurs_fiche);
    if (isset($valeurs_fiche['bf_date_debut_evenement']) && isset($valeurs_fiche['bf_date_fin_evenement'])) {
        $js .= '        {
                    title: "'.addslashes($valeurs_fiche['bf_titre']).'",
                    /*start: new Date('.date('Y', strtotime($valeurs_fiche['bf_date_debut_evenement'])).','.(date('n', strtotime($valeurs_fiche['bf_date_debut_evenement']))-1).','.date('d', strtotime($valeurs_fiche['bf_date_debut_evenement'])).'),
                    end: new Date('.date('Y', strtotime($valeurs_fiche['bf_date_fin_evenement'])).','.(date('n', strtotime($valeurs_fiche['bf_date_fin_evenement']))-1).','.date('d', strtotime($valeurs_fiche['bf_date_fin_evenement'])).'),*/
                    start:"'.$valeurs_fiche['bf_date_debut_evenement'].'",
                    end:"'.$valeurs_fiche['bf_date_fin_evenement'].'",
                    url:"'.$GLOBALS['wiki']->config['base_url'].$valeurs_fiche['id_fiche'].'",
                    allDay: '.((strlen($valeurs_fiche['bf_date_debut_evenement'])>10) ? 'false':'true').'
        },';
    }
}
if (!empty($js)) {
    $js = substr($js,0,-1);    
}

$script = "$(document).ready(function() {
    $('#calendar').fullCalendar({
        header: {
            left: 'prev,next today',
            center: 'title',
            right: 'month,agendaWeek,agendaDay'
        },
        editable: false,
        events: [
            ".$js."
        ],
        monthNames: ['"._t('BAZ_JANVIER')."','"._t('BAZ_FEVRIER')."','"._t('BAZ_MARS')."','"._t('BAZ_AVRIL')."','"._t('BAZ_MAI')."','"._t('BAZ_JUIN')."','"._t('BAZ_JUILLET')."','"._t('BAZ_AOUT')."','"._t('BAZ_SEPTEMBRE')."','"._t('BAZ_OCTOBRE')."','"._t('BAZ_NOVEMBRE')."','"._t('BAZ_DECEMBRE')."'],
        monthNamesShort: ['Jan','Fev','Mar','Avr','Mai','Juin','Juil','Aug','Sep','Oct','Nov','Dec'],
        dayNames: ['"._t('BAZ_DIMANCHE')."','"._t('BAZ_LUNDI')."','"._t('BAZ_MARDI')."','"._t('BAZ_MERCREDI')."','"._t('BAZ_JEUDI')."','"._t('BAZ_VENDREDI')."','"._t('BAZ_SAMEDI')."'],
        dayNamesShort: ['"._t('BAZ_DIMANCHE_COURT')."','"._t('BAZ_LUNDI_COURT')."','"._t('BAZ_MARDI_COURT')."','"._t('BAZ_MERCREDI_COURT')."','"._t('BAZ_JEUDI_COURT')."','"._t('BAZ_VENDREDI_COURT')."','"._t('BAZ_SAMEDI_COURT')."'],
        buttonText: {
            prev: '&nbsp;&#9668;&nbsp;',
            next: '&nbsp;&#9658;&nbsp;',
            prevYear: '&nbsp;&lt;&lt;&nbsp;',
            nextYear: '&nbsp;&gt;&gt;&nbsp;',
            today: '"._t('BAZ_TODAY')."',
            month: '"._t('BAZ_MONTH')."',
            week: '"._t('BAZ_WEEK')."',
            day: '"._t('BAZ_DAY')."'
        },
        firstDay : 1,
        timeFormat: 'HH:mm{ - HH:mm}',
        eventClick : calendar_click
    });
});\n";

if (!empty($minical) && $minical==1) {
    $script .= '
    function calendar_click(event) {
        if (event.url) {
            var left = (screen.width/2)-(600/2);
            var top = (screen.height/2)-(400/2);
            window.open(event.url+\'/iframe\', \'_blank\',"toolbar=no, directories=no, resizable=no, location=no, width=600, height=400, top="+top+", left="+left+", menubar=no, status=no, scrollbars=yes");
        }
        return false;
    }

    function init_calendar_tooltip() {
        $(".fc-event-title").each(function() {
            texte = $(this).html();
            $(this).parents(\'.fc-event\').tooltip({\'title\':texte, \'html\':true});
        });
    }
    setTimeout(init_calendar_tooltip,2000);';
} else {
    $script .= "
    function calendar_click(event) {
            if (event.url) {
                $('<div>').attr('id', 'dateModal' ).addClass('modal fade').appendTo($('body'));
                var modal = $('#dateModal');
                modal.html('<div class=\"modal-dialog\"><div class=\"modal-content\"><div class=\"modal-header\"><button type=\"button\" class=\"close\" data-dismiss=\"modal\">&times;</button><h3>'+event.title+'</h3></div><div class=\"modal-body\"></div></div></div>').on('hidden', function() {modal.remove()});
                modal.find('.modal-body').load(event.url + ' .page', function() {
                    $(this).find('.page').append('<a href=\"'+event.url + '/edit' +'\" class=\"btn btn-default pull-right\"><i class=\"glyphicon glyphicon-pencil icon-pencil\"></i> "._t('BAZ_MODIFIER_LA_FICHE')."</a><div class=\"clearfix\"></div>').removeClass('page').find('h1.BAZ_fiche_titre').remove();
                    modal.modal('show');
                });

                return false;
            }
        }";
}

$this->AddJavascriptFile('tools/bazar/libs/vendor/fullcalendar/fullcalendar.js');
$this->AddJavascript($script);
echo "<div id='calendar'></div>\n";
