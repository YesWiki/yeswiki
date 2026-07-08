<?php

use YesWiki\Core\YesWikiMigration;

class RemoveLoginTplTemplate extends YesWikiMigration
{
    public function run()
    {
        // Get the correct REGEXP operator for the database driver
        $regexpOp = $this->dbService->regexpOperator();
        $pages = $this->dbService->loadAll("SELECT * FROM {$this->dbService->prefixTable('pages')} WHERE body $regexpOp '\\{\\{login.*tpl\\.html.*\\}\\}'");
        foreach ($pages as $p) {
            $p['body'] = preg_replace('/(\{\{login.*template=".*)(\.tpl\.html)"/m', '$1.twig"', $p['body']);
            $this->dbService->query("UPDATE {$this->dbService->prefixTable('pages')} SET body='{$this->dbService->escape($p['body'])}' WHERE id='{$p['id']}'");
        }
    }
}
