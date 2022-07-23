<?php

/**
 * $_REQUEST :
 * - action : login|logout|checklogged
 * $_SERVER :
 * - REQUEST_URI
 * $_POST :
 * - name
 * - email
 * - password
 * - remember
 * - userpage
 *
 * refactor from login.php whiwh was Copyright 2010  Florian SCHMITT
 */

namespace YesWiki\Login;

use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Login\Exception\LoginException;
use YesWiki\Core\YesWikiAction;

class LoginAction extends YesWikiAction
{
    protected $authController;
    protected $pageManager;
    protected $templateEngine;
    protected $userManager;

    public function formatArguments($arg)
    {
        $noSignupButton = (isset($arg['signupurl']) && $arg['signupurl'] === "0");
        $incomingurl = !empty($arg['incomingurl'])
            ? $this->wiki->generateLink($arg['incomingurl'])
            : $this->getIncomingUrlFromServer($_SERVER ?? []);
        $this->templateEngine = $this->getService(TemplateEngine::class);

        return [
            'signupurl' => $noSignupButton ? "0" : (
                empty($arg['signupurl'])
                // TODO : check page name for other languages
                ? $this->wiki->Href("", "ParametresUtilisateur")
                : $this->wiki->generateLink($arg['signupurl'])
            ),

            'profileurl' => empty($arg['profileurl'])
                ? $this->wiki->Href("", "ParametresUtilisateur")
                : (
                    $arg['profileurl'] == 'WikiName'
                    ? 'WikiName'
                    : $this->wiki->generateLink($arg['profileurl'])
                ),

            'incomingurl' => $incomingurl,

            'loggedinurl' => empty($arg['loggedinurl'])
                ? $incomingurl
                : $this->wiki->generateLink($arg['loggedinurl']),

            'loggedouturl' => empty($arg['loggedouturl'])
                ? $incomingurl
                : $this->wiki->generateLink($arg['loggedouturl']),

            'userpage' => !empty($arg['userpage'])
                ? (
                    $arg['userpage'] == 'user'
                    ? 'user'
                    :$this->wiki->generateLink($arg['userpage'])
                )
                : (
                    (isset($_REQUEST["action"]) && $_REQUEST["action"] == "logout")
                    ? preg_replace('/(&|\\\?)$/m', '', preg_replace('/(&|\\\?)action=logout(&)?/', '$1', $incomingurl))
                    : $incomingurl
                ),

            'lostpasswordurl' => !empty($arg['lostpasswordurl'])
                ? $this->wiki->generateLink($arg['lostpasswordurl'])
                // TODO : check page name for other languages
                : $this->wiki->Href("", "MotDePassePerdu"),

            'class' => !empty($arg['class'])  ? $arg['class'] : '',
            'btnclass' => !empty($arg['btnclass'])  ? $arg['btnclass'] : '',
            'nobtn' => $this->formatBoolean($arg, false, 'nobtn'),
            'template' => (empty($arg['template']) ||
                empty(basename($arg['template'])) ||
                !$this->templateEngine->hasTemplate("@login/".basename($arg['template'])))
                ? 'default.twig'
                : basename($arg['template']),
        ];
    }

    public function run()
    {
        // get services
        $this->authController = $this->getService(AuthController::class);
        $this->pageManager = $this->getService(PageManager::class);
        $this->userManager = $this->getService(UserManager::class);

        $action = $_REQUEST["action"] ?? '';
        switch ($action) {
            case "logout":
                $this->logout();
                break;
            case "login":
                $this->login();
                break;
                
            case "checklogged":
            default:
                return $this->renderForm($action);
        }
        return null;
    }

    private function getIncomingUrlFromServer(array $server): string
    {
        $url = explode('?', $server['REQUEST_URI']);
        $d = dirname($url[0].'?');
        $t = ($d != '/' ? str_replace($d, '', $server['REQUEST_URI']) : $server['REQUEST_URI']);
        return $this->wiki->getBaseUrl().$t;
    }

    private function renderForm(string $action): string
    {
        $user = $this->authController->getLoggedUser();
        $connected = !empty($user);
        $error = "";
        $pageMenuUserContent = "";
        if ($connected) {
            $pageMenuUser = $this->pageManager->getOne("PageMenuUser");
            if (!empty($pageMenuUser)) {
                $pageMenuUserContent = $this->wiki->Format("{{include page=\"PageMenuUser\"}}");
            }
            if ($this->arguments['profileurl'] == 'WikiName') {
                $this->arguments['profileurl'] = $this->wiki->Href("edit", $user['name']);
            }
        } elseif ($action == "checklogged") {
            $error = _t('LOGIN_COOKIES_ERROR');
        }

        $output = $this->render("@login/{$this->arguments['template']}", [
            "connected" => $connected,
            "user" => ((isset($user["name"])) ? $user["name"] : ((isset($_POST["name"])) ? $_POST["name"] : '')),
            "email" => ((isset($user["email"])) ? $user["email"] : ((isset($_POST["email"])) ? $_POST["email"] : '')),
            "incomingurl" => $this->arguments['incomingurl'],
            "signupurl" => $this->arguments['signupurl'],
            'lostpasswordurl' => $this->arguments['lostpasswordurl'],
            "profileurl" => $this->arguments['profileurl'],
            "userpage" => $this->arguments['userpage'],
            "PageMenuUser" => $pageMenuUserContent,
            "btnclass" => $this->arguments['btnclass'],
            "class" => $this->arguments['class'],
            "nobtn" => $this->arguments['nobtn'],
            "error" => $error
        ]);

        // backward compatibility TODO remove it for ectoplasme
        if (!empty($this->arguments['class']) && substr($this->arguments['template'], -strlen(".tpl.html")) == ".tpl.html") {
            $output = "<div class=\"{$this->arguments['class']}\">\n$output\n</div>\n";
        }
        return $output ;
    }

    private function login()
    {
        $incomingurl = filter_input(INPUT_POST, 'incomingurl', FILTER_SANITIZE_URL);
        if (empty($incomingurl)) {
            $incomingurl = $this->arguments['incomingurl'];
        }
        try
        {
            if (!empty($_POST["name"])) {
                $name = filter_input(INPUT_POST, 'name', FILTER_UNSAFE_RAW);
                $name = ($name === false) ? "" : htmlspecialchars(strip_tags($name));
                if (empty($name)) {
                    throw new LoginException(_t('LOGIN_WRONG_USER'));
                }
                if (strpos($name, '@') !== false) {
                    // si le nomWiki est un mail
                    $user = $this->userManager->getOneByEmail($name);
                } else {
                    $user = $this->userManager->getOneByName($name);
                }
            } else {
                if (empty($_POST["email"])) {
                    throw new LoginException(_t('LOGIN_WRONG_USER'));
                }
                $email = filter_input(INPUT_POST, 'email', FILTER_UNSAFE_RAW);
                $email = ($email === false) ? "" : htmlspecialchars(strip_tags($email));
                if (empty($email)) {
                    throw new LoginException(_t('LOGIN_WRONG_USER'));
                }
                $user = $this->userManager->getOneByEmail($email);
            }
            if (empty($user)) {
                throw new LoginException(_t('LOGIN_WRONG_USER'));
            }
            
            if (($this->wiki->GetConfigValue ("signup_mail_activation") === "1") && !$this->userManager->isActivated ($user["name"]))
            {            
            	$vMessage = "Your account must be activated first. ";
            
            	if ($this->userManager->sendActivationLink ($user["name"]))
				{	            
	            	$vMessage .= "A mail was sent to you with the instruction to activate you account. ";
				}
				else
				{
					$vMessage .= "There was a problem to send you an mail to activate you account. Please contact the website administrator. ";
				}

				$this->wiki->SetMessage($vMessage);
				$this->wiki->Redirect($incomingurl);				
            }
			else            
			{
	            $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
	            $password = ($password === false) ? "" : $password;
	            if (!$this->authController->checkPassword($password, $user)) {
	                throw new LoginException(_t('LOGIN_WRONG_PASSWORD'));
	            }
	            $remember = filter_input(INPUT_POST, 'remember', FILTER_VALIDATE_BOOL);
	            $this->authController->login($user, $remember);
            
            	// si l'on veut utiliser la page d'accueil correspondant au nom d'utilisateur
	            if (((!empty($_POST['userpage']) && $_POST['userpage'] == 'user') || $userpage == 'user') && $this->pageManager->getOne($user["name"])) {
	                $this->wiki->Redirect($this->href('', $user["name"]));
	            } else {
	                $this->wiki->Redirect($this->arguments['loggedinurl']);
	            }
	        }
	   } catch (LoginException $ex) {
	            // on affiche une erreur sur le NomWiki sinon
	            $this->wiki->SetMessage($ex->getMessage());
	            $this->wiki->Redirect($incomingurl);
       } catch (Exception $ex) {
	            // error error
	            flash($ex->getMessage(), 'error');
	            $this->wiki->Redirect($incomingurl);
       }
    }

    private function logout()
    {
        $this->authController->logout();
        $this->wiki->SetMessage(_t('LOGIN_YOU_ARE_NOW_DISCONNECTED'));
        $this->wiki->Redirect(preg_replace('/(&|\\\?)$/m', '', preg_replace('/(&|\\\?)action=logout(&)?/', '$1', $this->arguments['loggedouturl'])));
        $this->wiki->exit();
    }
}
