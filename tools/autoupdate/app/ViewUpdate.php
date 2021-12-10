<?php
/* === WARNING !!!! ==== DO NOT USE THIS CLASS IN DORYPHORE
 *
 * This file is only to prevent errors when updating from cercopitheque to doryphore.
 * It is executed in a cercopitheque version in RAM on server. Do not call new others classes
 * because we can not be sure that this class exists in the RAM or if the file is ever on the file system.
 */
namespace AutoUpdate;

class ViewUpdate
{
    protected $autoUpdate;
    protected $messages;
    protected $baseURL;
    
    // important do not change the arguments of this method because called form cercopitheque
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
    
    // important do not change the arguments of this method because called form cercopitheque
    public function show()
    {
        $data = [];
        foreach ($this->messages as $message) {
            $data_message = [];
            $data_message['status'] = $message['status'];
            $data_message['text'] = $message['text'];
            $data['messages'][] = $data_message;
        }
        $data['baseURL'] = $this->baseURL;
        $data['fromCercopitheque'] = (YESWIKI_VERSION == "cercopitheque");
        $_SESSION['updateMessage'] = json_encode($data);

        // reload wiki in doryphore version before displaying the message
        // give $data by $_SESSION['updateMessage']
        $newAdress = $this->baseURL;
        header("Location: ".$newAdress);
        exit();
    }
}
