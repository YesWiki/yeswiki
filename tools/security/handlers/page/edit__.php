<?php

use YesWiki\Core\Service\BotGuard;

if ($this->HasAccess('write') && $this->HasAccess('read')) {
    $plugin_output_new = $this->services->get(BotGuard::class)->insertInto($plugin_output_new, 'ACEditor');
}
