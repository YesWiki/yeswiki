<?php

use YesWiki\Core\Service\DbService;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\TripleStore;

class migrateListFormat extends YesWikiMigration
{
    public function run()
    {
        $tripleStore = $this->getService(TripleStore::class);
        $listManager = $this->getService(ListManager::class);
        $dbService = $this->getService(DbService::class);

        $table_page_name = trim($this->dbService->prefixTable('pages'));

        $lists = $dbService->loadAll('Select * from '.$table_page_name.' where tag in (SELECT resource FROM '.$this->dbService->prefixTable('triples').' WHERE value = \'liste\') and latest = \'Y\'');
     //   $lists = $dbService->loadAll('Select * from '.$this->dbService->prefixTable('pages').' where id = 203');

        $new_lists = [];
        dump($lists);

        foreach($lists as $list) {
            $id = $list['id'];
            $list = json_decode($list['body'], true);
            $nodes = [];
            if (!isset($list['nodes'])) {
                $list = $listManager->convertDataStructure($list);
            }
            dump($list);
            foreach($list['nodes'] as $node) {
                $children = [];
                if (isset($node['children'])) {
                    foreach($node['children'] as $child) {
                        $children[$child['id']] = [
                            'label' => $child['label'],
                            'children' => [],
                        ];
                    }
                }
                $nodes[$node['id']] = [
                    'label' => $node['label'],
                    'children' => $children,
                ];
            }
            $new_lists[$id] = [
                'title' => $list['title'],
                'nodes' => $nodes,
            ];
        }
        dump($new_lists);

        $temp_table_query = 'create temporary table lists (id int, body longtext)';
        $insert_query = 'insert into lists (id, body) values ';

        foreach ($new_lists as $key => $value) {
            dump($key);
            dump($value);
            $json = json_encode($value);
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
        dump($query);
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
