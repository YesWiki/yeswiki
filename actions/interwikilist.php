<?php

$file = implode('', file('interwiki.conf', 1));
echo $this->Format('%%' . $file . '%%');
