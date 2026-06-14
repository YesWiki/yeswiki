<?php

use YesWiki\Core\Service\DbService;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Core\Service\TripleStore;

function convertDataStructure($json) {
    if (isset($json['titre_liste'])) {
        $newJson = ['title' => $json['titre_liste'], 'nodes' =>[]];
        foreach($json['label'] as $id => $label) {
            $newJson['nodes'][] = ['id' => $id, 'label' => $label];
        }
    }
}

class MigrateListFormat extends YesWikiMigration
{
    public function run()
    {
        $tripleStore = $this->getService(TripleStore::class);
        $dbService = $this->getService(DbService::class);

        $table_page_name = trim($this->dbService->prefixTable('pages'));

        $lists = $dbService->loadAll('Select * from '.$table_page_name.' where tag in (SELECT resource FROM '.$this->dbService->prefixTable('triples').' WHERE value = \'liste\')');

        foreach($lists as $list) {
            $id = $list['id'];
            $list = json_decode($list['body'], true);
            $nodes = [];
            if (!isset($list['nodes'])) {
                $list = convertDataStructure($list);
            }
            foreach ($list['nodes'] as $index => $node) {
                $children = [];
                if (isset($node['children'])) {
                    foreach ($node['children'] as $i => $child) {
                        $children[$i] = [
                            'id' => $child['id'],
                            'label' => $child['label'],
                            'children' => [],
                        ];
                    }
                }
                $nodes[$index] = [
                    'id' => $node['id'],
                    'label' => $node['label'],
                    'children' => $children,
                ];
            }
            $new_lists[$id] = [
                'id' => $id,
                'title' => $list['title'],
                'nodes' => $nodes,
            ];
        }

        $temp_table_query = 'create temporary table lists (id int, body longtext)';
        $insert_query = 'insert into lists (id, body) values ';

        foreach ($new_lists as $key => $value) {
            $json = json_encode($value, JSON_FORCE_OBJECT);
            $json = str_replace('\\', '\\\\', $json);
            $json = str_replace("'", "\'", $json);
            $insert_query .= '( '.$key.",'".$json."'),";
        }
        $insert_query = rtrim($insert_query, ',');

        $update_query = 'update lists, '.
            $table_page_name.
            ' set '.
            $table_page_name.'.body=lists.body where lists.id='.$table_page_name.'.id';

        $query = $temp_table_query.';'.$insert_query.';'.$update_query;
        $link = $dbService->getLink();
        mysqli_multi_query($link, $query);
        do {
            /* store the result set in PHP */
            if ($result = mysqli_store_result($link)) {
                while ($row = mysqli_fetch_row($result)) {
                    printf("%s\n", $row[0]);
                }
                /* Affichage d'une séparation */
                if (mysqli_more_results($link)) {
                    printf("-----------------\n");
                }
            }
            /* print divider */
            if (mysqli_more_results($link)) {
                printf("-----------------\n");
            }
        } while (mysqli_next_result($link));
    }
}
