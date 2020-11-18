<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Wiki;

class ApiService
{
    protected $wiki;
    protected $params;

    public function __construct(Wiki $wiki, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->params = $params;
    }

    public function process($apiArgs)
    {
        if (!$this->isAuthorized()) {
            $this->blockAccess();
        } elseif( !isset($apiArgs[0]) ) {
            $this->showDocumentation();
        } else {
            header("Content-Type: application/json; charset=UTF-8");
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: X-Requested-With, Location, Slug, Accept, Content-Type');
            header('Access-Control-Expose-Headers: Location, Slug, Accept, Content-Type');
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT, PATCH');
            header('Access-Control-Max-Age: 86400');

            $apiFunctionName = strtolower($_SERVER['REQUEST_METHOD']).ucwords(strtolower($apiArgs[0]));
            array_shift($apiArgs);

            if (function_exists($apiFunctionName)) {
                // We may need to parse the body manually
                if (empty($_POST) && ($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'PUT' || $_SERVER['REQUEST_METHOD'] == 'PATCH')) {
                    $_POST = json_decode(file_get_contents('php://input'), true);
                }

                echo $apiFunctionName($apiArgs);
                exit();
            } else {
                $this->showDocumentation();
            }
        }
    }

    /**
     * Get header Authorization
     * */
    public function getAuthorizationHeader()
    {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
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
    public function getBearerToken()
    {
        $headers = $this->getAuthorizationHeader();
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }
    public function isAuthorized()
    {
        $apiKey = $this->getBearerToken();
        return(
            $this->params->has('api_allowed_keys') &&
            ($this->params->get('api_allowed_keys')['public'] === true || in_array($apiKey, $this->params->get('api_allowed_keys')))
        );
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
    protected function showDocumentation()
    {
        echo $this->wiki->Header();

        $output = '<h1>YesWiki API</h1>';

        $urlUser = $this->wiki->Href('', 'api/user');
        $output .= '<h2>'._t('USERS').'</h2>'."\n".
            'GET <code><a href="'.$urlUser.'">'.$urlUser.'</a></code><br />';

        $urlGroup = $this->wiki->Href('', 'api/group');
        $output .= '<h2>'._t('GROUPS').'</h2>'."\n".
            'GET <code><a href="'.$urlGroup.'">'.$urlGroup.'</a></code><br />';

        echo $output;

        $extensions = array_keys($this->wiki->extensions);
        foreach ($extensions as $extension) {
            $func = 'documentation'.ucfirst(strtolower($extension));
            if (function_exists($func)) {
                echo $func();
            }
        }
        echo $this->wiki->Footer();
    }

    protected function blockAccess()
    {
        http_response_code(401);
        header("Access-Control-Allow-Origin: * ");
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode(array("message" => "You are not allowed to use this api, check your api key."));
    }
}
