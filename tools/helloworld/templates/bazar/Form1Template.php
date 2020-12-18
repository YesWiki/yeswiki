<?php
use YesWiki\Core\YesWikiBazarTemplate;

class Form1Template extends YesWikiBazarTemplate
{
    public function prepare()
    {
        // you can prepare your data model here by using services

        // in this example, we shuffle the fields and keep the indexes to access to field metadatas (label, ...)
        $keys = array_keys($this->arguments['html']);

        $indexes = range(0, count($keys) - 1);
        shuffle($indexes);

        $this->arguments['shuffled'] = [];
        foreach ($indexes as $i){
            $this->arguments['shuffled'][] = [
                'key' => $keys[$i],
                'index' => $i,
                'value' => $this->arguments['html'][$keys[$i]]
            ];
        }
    }
}