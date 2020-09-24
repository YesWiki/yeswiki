<?php

namespace YesWiki;

class BazarFiche
{
    protected $wiki = ''; // give access to the main wiki object

    public function __construct($wiki)
    {
        $this->wiki = $wiki;
    }

    public function getOne($tag, $semantic = false, $time = '')
    {
        $page = $this->wiki->LoadPage($tag, $time);
        $data = json_decode($page['body'], true);

        foreach ($data as $key => $value) {
            $data[$key] = _convert($value, 'UTF-8');
        }

        // cas ou on ne trouve pas les valeurs id_fiche
        if (!isset($data['id_fiche'])) {
            $data['id_fiche'] = $tag;
        }

        if( $semantic ) {
            $data = baz_append_semantic_data($data, $data['id_typeannonce'], true);
        }

        return $data;
    }

    public function getList($formId, $semantic = false)
    {
        // on recupere toutes les fiches du formulaire donné
        $results = baz_requete_recherche_fiches(
            '',
            'alphabetique',
            $formId,
            '',
            1,
            '',
            '',
            true,
            ''
        );

        $tab_entries = array();
        foreach ($results as $wikipage) {
            $decoded_entry = json_decode($wikipage['body'], true);
            // Output JSON-LD
            if( $semantic ) {
                $tab_entries[] = baz_append_semantic_data($decoded_entry, $decoded_entry['id_typeannonce'], true);
            } else {
                $tab_entries[$decoded_entry['id_fiche']] = array_map('strval', $decoded_entry);
            }
        }
        if (count($tab_entries)>0) {
            ksort($tab_entries);
        }

        return $tab_entries;
    }

    protected function convertSemanticData($formId, $data)
    {
        $nonSemanticData = [];
        $form = baz_valeurs_formulaire($formId);

        if( ($data['@type'] && $data['@type'] !== $form['bn_sem_type']) || $data['type'] && $data['type'] !== $form['bn_sem_type'] ) {
            exit('The @type of the sent data must be ' . $form['bn_sem_type']);
        }

        $fields_infos = bazPrepareFormData($form);
        foreach ($fields_infos as $field_info) {
            // If the file is not semantically defined, ignore it
            if ($field_info['sem_type'] && $data[$field_info['sem_type']]) {
                if( $field_info['type'] === 'date') {
                    $date = new \DateTime($data[$field_info['sem_type']]);
                    $nonSemanticData[$field_info['id']] = $date->format('Y-m-d');
                    $nonSemanticData[$field_info['id'] . '_allday'] = 0;
                    $nonSemanticData[$field_info['id'] . '_hour'] = $date->format('H');
                    $nonSemanticData[$field_info['id'] . '_minutes'] = $date->format('i');
                } elseif ($field_info['type'] === 'image') {
                    $nonSemanticData['image'.$field_info['id']] = $data[$field_info['sem_type']];
                } else {
                    $nonSemanticData[$field_info['id']] = $data[$field_info['sem_type']];
                }
            }
        }

        return $nonSemanticData;
    }

    public function create($formId, $data, $semantic = false, $sourceUrl = null)
    {
        if( $semantic ) {
            $data = $this->convertSemanticData($formId, $data);
        }

        $data['id_typeannonce'] = $formId;
        $data['antispam'] = 1;

        $valid = validateForm($data);

        if ($valid['result']) {
            $data = baz_requete_bazar_fiche($data);

            // on change provisoirement d'utilisateur
            if (isset($GLOBALS['utilisateur_wikini'])) {
                $olduser = $this->wiki->GetUser();
                $this->wiki->LogoutUser();

                // On s'identifie de facon a attribuer la propriete de la fiche a
                // l'utilisateur qui vient d etre cree
                $user = $this->wiki->LoadUser($GLOBALS['utilisateur_wikini']);
                $this->wiki->SetUser($user);
            }

            $ignoreAcls = true;
            if (isset($this->wiki->config['bazarIgnoreAcls'])) {
                $ignoreAcls = $this->wiki->config['bazarIgnoreAcls'];
            }

            // on sauve les valeurs d'une fiche dans une PageWiki, retourne 0 si succès
            $saved = $this->wiki->SavePage(
                $data['id_fiche'],
                json_encode($data),
                '',
                $ignoreAcls // Ignore les ACLs
            );

            // on cree un triple pour specifier que la page wiki creee est une fiche
            // bazar
            if ($saved == 0) {
                $this->wiki->InsertTriple(
                    $data['id_fiche'],
                    'http://outils-reseaux.org/_vocabulary/type',
                    'fiche_bazar',
                    '',
                    ''
                );
            }

            if ($sourceUrl) {
                $this->wiki->InsertTriple(
                    $data['id_fiche'],
                    'http://outils-reseaux.org/_vocabulary/sourceUrl',
                    $sourceUrl,
                    '',
                    ''
                );
            }

            // on remet l'utilisateur initial
            if (isset($GLOBALS['utilisateur_wikini'])) {
                $this->wiki->LogoutUser();
                if (!empty($olduser)) {
                    $this->wiki->SetUser($olduser, 1);
                }
            }

            // Envoi d un mail aux administrateurs
//        if ($this->wiki->config['BAZ_ENVOI_MAIL_ADMIN']) {
//            include_once 'tools/contact/libs/contact.functions.php';
//
//            $lien = str_replace('/wakka.php?wiki=', '', $this->wiki
//                ->config['base_url']);
//            $sujet = removeAccents('['.str_replace('http://', '', $lien)
//                .'] nouvelle fiche ajoutee : '.$valeur['bf_titre']);
//            $text =
//                'Voir la fiche sur le site pour l\'administrer : '.$this->wiki->href('', $valeur['id_fiche']);
//            $texthtml = '<br /><br /><a href="'.$this->wiki->href('', $valeur['id_fiche']).'" title="Voir la fiche">Voir la fiche sur le site pour l\'administrer</a>';
//            $fichier = 'tools/bazar/presentation/styles/bazar.css';
//            $style = file_get_contents($fichier);
//            $style = str_replace('url(', 'url('.$lien.'/tools/bazar/presentation/', $style);
//            $fiche = str_replace(
//                    'src="tools',
//                    'src="'.$lien.'/tools',
//                    baz_voir_fiche(0, $valeur['id_fiche'])
//                ).$texthtml;
//            $html =
//                '<html><head><style type="text/css">'.$style.
//                '</style></head><body>'.$fiche.'</body></html>';
//
//            //on va chercher les admins
//            $requeteadmins = 'SELECT value FROM '.$this->wiki
//                    ->config['table_prefix'].'triples '
//                .'WHERE resource="ThisWikiGroup:admins" AND property="http://www.wikini.net/_vocabulary/acls" LIMIT 1';
//            $ligne = $this->wiki->LoadSingle($requeteadmins);
//            $tabadmin = explode("\n", $ligne['value']);
//            foreach ($tabadmin as $line) {
//                $admin = $this->wiki->LoadUser(trim($line));
//                send_mail($this->wiki->config['BAZ_ADRESSE_MAIL_ADMIN'], $this->wiki->config['BAZ_ADRESSE_MAIL_ADMIN'], $admin['email'], $sujet, $text, $html);
//            }
//        }

            return $data;
        }
    }

    public function update($tag, $data, $semantic = false, $replace = false)
    {
        $previousData = $this->getOne($tag);

        if( $semantic ) {
            $data = $this->convertSemanticData($previousData['id_typeannonce'], $data);
        }

        if( $replace ) {
            $data['id_typeannonce'] = $previousData['id_typeannonce'];
        } else {
            // If PATCH, overwrite previous data with new data
            $data = array_merge($previousData, $data);
        }

        $data = baz_requete_bazar_fiche($data);

        // on sauve les valeurs d'une fiche dans une PageWiki, pour garder l'historique
        $this->wiki->SavePage($data['id_fiche'], json_encode($data));
    }

    public function delete($tag)
    {
        $this->wiki->DeleteOrphanedPage($tag);
        $this->wiki->DeleteTriple($tag, 'http://outils-reseaux.org/_vocabulary/type', null, '', '');
        $this->wiki->DeleteTriple($tag, 'http://outils-reseaux.org/_vocabulary/sourceUrl', null, '', '');
        $this->wiki->LogAdministrativeAction($this->wiki->GetUserName(), "Suppression de la page ->\"\"" . $tag . "\"\"");
    }
}
