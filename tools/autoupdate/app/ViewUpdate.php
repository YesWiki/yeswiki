<?php
namespace AutoUpdate;

class ViewUpdate extends View
{
    public function __construct($autoUpdate, $messages)
    {
        parent::__construct($autoUpdate);
        $this->messages = $messages;
        $this->template = "update";
    }

    protected function grabInformations()
    {
        $infos = array(
            'messages' => $this->messages,
            'baseUrl' => $this->autoUpdate->baseUrl(),
        );
        return $infos;
    }
}
