<?php

namespace YesWiki\Kernel\Entity;

class Messages extends Collection
{
    /**
     * @return array<array-key, mixed>
     */
    public function reset()
    {
        $this->list = [];

        return $this->list;
    }

    /**
     * @param Messages|array<array-key, mixed>|string $pMessage
     * @param string                                  $pStatus
     *
     * @return $this
     */
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

    /** Whether any message says something failed. */
    public function hasError(): bool
    {
        foreach ($this->list as $message) {
            if (($message['status'] ?? '') === _t('AU_ERROR')) {
                return true;
            }
        }

        return false;
    }
}
