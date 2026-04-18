<?php

namespace YesWiki\AutoUpdate\Entity;

class Messages extends Collection
{
    public function reset()
    {
        $this->list = [];

        return $this->list;
    }

    public function add($pMessage, $pStatus = '')
    {
        if ($pMessage instanceof Messages) {
            $this->add($pMessage->toArray());
        } elseif (is_array($pMessage)) {
            $this->list = array_merge($this->list, $pMessage);
        } else {
            $this[] = [
                'text' => _t($pMessage),
                'status' => _t($pStatus),
            ];
        }

        return $this;
    }
}
