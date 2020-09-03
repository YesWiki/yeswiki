<?php
namespace YesWiki;
class Api
{
    protected $wiki = ''; // give access to the main wiki object

    public function __construct($wiki)
    {
        $this->wiki = $wiki;
    }
    /** 
     * Get header Authorization
     * */
    public function getAuthorizationHeader(){
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        }
        else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            //print_r($requestHeaders);
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }

    /**
    * get access token from header
    * */
    public function getBearerToken() {
        $headers = $this->getAuthorizationHeader();
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
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

        $urlUser = $this->wiki->href('', 'api/user');
        $output .= '<h2>'._t('USERS').'</h2>'."\n".
        'GET <code><a href="'.$urlUser.'">'.$urlUser.'</a></code><br />';

        $urlGroup = $this->wiki->href('', 'api/group');
        $output .= '<h2>'._t('GROUPS').'</h2>'."\n".
        'GET <code><a href="'.$urlGroup.'">'.$urlGroup.'</a></code><br />';
        return $output;
    }
}
