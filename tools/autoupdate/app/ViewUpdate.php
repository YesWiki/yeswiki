<?php
// file only to prevent errors when updating from cercopitheque to doryphore
namespace AutoUpdate;

class ViewUpdate
{
    protected $autoUpdate;
    protected $messages;
    protected $baseURL;
    
    public function __construct($autoUpdate, $messages)
    {
        $this->autoUpdate = $autoUpdate;
        $this->baseURL = $autoUpdate->baseUrl();
        $this->messages = $messages;
    }

    protected function grabInformations()
    {
        $infos = array(
            'messages' => $this->messages,
            'baseUrl' => $this->autoUpdate->baseUrl(),
        );
        return $infos;
    }
    
    public function show()
    {
        $output = '';
        foreach ($this->messages as $message) {
            $output .= $message['text'] . ':' . $message['status'] . ';';
        }
        $_SESSION['message'] = $output;
        header("Location: ".$this->baseURL);
        exit();
    }
}
