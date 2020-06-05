<?php
namespace YesWiki;
class Api
{
    protected $wiki = ''; // give access to the main wiki object

    public function __construct($wiki)
    {
        $this->wiki = $wiki;
    }

    public function getUser($username = '')
    {
        if (!empty($username[0])) {
            if ($this->wiki->UserIsAdmin() or $this->wiki->GetUserName() == $username[0]) {
                $user = $this->wiki->LoadUser($username[0]);
                if ($user) {
                    echo json_encode($user);
                } else {
                    echo json_encode(
                        array('error' => array('User '.$username[0].' not found.'))
                    );
                }
            } else {
                echo json_encode(
                    array('error' => array('Unauthorized'))
                );
            }
        } else {
            $users = $this->wiki->LoadUsers();
            echo json_encode($users);
        }
    }


    /**
     * Documentation de l'API YesWiki
     *
     * @return void
     */
    public function documentationYesWiki()
    {
        $output = '<h1>YesWiki API</h1>';
        $output .= '<h2>'._t('USERS').'</h2>'."\n".
        'GET <code>'.$this->wiki->href('', 'api/user').'</code><br />';
        $output .= '<h2>'._t('GROUPS').'</h2>'."\n".
        'GET <code>'.$this->wiki->href('', 'api/group').'</code><br />';
        return $output;
    }
}
