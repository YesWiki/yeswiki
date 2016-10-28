<?php

// Remplacement de apache_request_headers pour les vieux serveurs
// http://stackoverflow.com/questions/2916232/call-to-undefined-function-apache-request-headers
if(!function_exists('apache_request_headers')) {
	function apache_request_headers() {
		// Based on: http://www.iana.org/assignments/message-headers/message-headers.xml#perm-headers
		$arrCasedHeaders = array(
				// HTTP
				'Dasl'             => 'DASL',
				'Dav'              => 'DAV',
				'Etag'             => 'ETag',
				'Mime-Version'     => 'MIME-Version',
				'Slug'             => 'SLUG',
				'Te'               => 'TE',
				'Www-Authenticate' => 'WWW-Authenticate',
				// MIME
				'Content-Md5'      => 'Content-MD5',
				'Content-Id'       => 'Content-ID',
				'Content-Features' => 'Content-features',
		);
		$arrHttpHeaders = array();

		foreach($_SERVER as $strKey => $mixValue) {
			if('HTTP_' !== substr($strKey, 0, 5)) {
				continue;
			}

			$strHeaderKey = strtolower(substr($strKey, 5));

			if(0 < substr_count($strHeaderKey, '_')) {
				$arrHeaderKey = explode('_', $strHeaderKey);
				$arrHeaderKey = array_map('ucfirst', $arrHeaderKey);
				$strHeaderKey = implode('-', $arrHeaderKey);
			}
			else {
				$strHeaderKey = ucfirst($strHeaderKey);
			}

			if(array_key_exists($strHeaderKey, $arrCasedHeaders)) {
				$strHeaderKey = $arrCasedHeaders[$strHeaderKey];
			}

			$arrHttpHeaders[$strHeaderKey] = $mixValue;
		}
		return $arrHttpHeaders;
	}
}

class identificationSso {

	private $wiki = null;
	private $config = null;

	private $cookie_tentative_identification = "";
	private $delai_tentative_identification = 60;
	
	private $auth_header = 'Authorization';

	public function __construct($wiki) {
		$this->wiki = $wiki;
		$this->config = $wiki->config;
		$this->auth_header = !empty($this->config['sso_auth_header']) ? $this->config['sso_auth_header'] : $this->auth_header;
		$this->cookie_tentative_identification = 'wikini_sso_tentative_identification';
	}

	function getToken() {
		// Premier essai, dans le header
		$headers = @apache_request_headers();
		$token = !empty($headers['Authorization']) ? $headers['Authorization'] : null;
		// Eventuellement, le jeton a pu être passé dans un header non standard, comme dans 
		// le cas où le header Authorization est supprimé par le mod cgi d'apache
		// Dans ce cas là on vérifie aussi dans un header alternatif si celui ci a été renseigné
		if($token == null && $this->auth_header != 'Authorization') {
			$token = !empty($headers[$this->auth_header]) ? $headers[$this->auth_header] : null;
		}

		// Sinon dans $_REQUEST ?
		if($token == null) {
			$token = !empty($_REQUEST['Authorization']) ? $_REQUEST['Authorization'] : null;
		}
		
		// Sinon dans $_COOKIE ?
		if($token == null) {
			$token = !empty($_COOKIE['tb_auth']) ? $_COOKIE['tb_auth'] : null;
		}

		return $token;
	}

	function decoderToken($token) {
		$token_parts = explode('.', $token);
		return json_decode(base64_decode($token_parts[1]), true);
	}

	function getPage() {
		return !empty($this->wiki->page) ? $this->wiki->page['tag'] : 'PagePrincipale';
	}

	// http://stackoverflow.com/questions/1251582/beautiful-way-to-remove-get-variables-with-php?lq=1
	function supprimerUrlVar($url, $var) {
		 return rtrim(preg_replace('/([?&])'.$var.'=[^&]+(&|$)/','$1',$url), '&?');
	}

	function getInfosCookie() {
		$infos = null;
		if(!empty($_COOKIE[$this->cookie_tentative_identification])) {
			$infos = json_decode($_COOKIE[$this->cookie_tentative_identification], true);
		}
		return $infos;
	}

	function setInfosCookie($infos) {
		$infos['expire'] = !empty($infos['expire']) ? $infos['expire'] : 0;
		setcookie($this->cookie_tentative_identification, json_encode($infos), $infos['expire'], $this->wiki->CookiePath);
	}

	function verifierEtInsererUtilisateurParJeton($jeton_rafraichi) {
		if(!empty($jeton_rafraichi['session']) && $jeton_rafraichi['session'] == true) {
			$token_decode = $this->decoderToken($jeton_rafraichi['token']);
			
			$nom_wiki = $token_decode['nomWiki'];
			$courriel = $token_decode['sub'];
			
			$utilisateur_wiki_existe = $this->wiki->LoadAll("SELECT * FROM  ".$this->wiki->config["table_prefix"]."users ".
					"WHERE ".
					"name = '".mysql_escape_string($nom_wiki)."' OR ".
					"email = '".mysql_escape_string($courriel)."'");
			
			// pas inscrit ? on l'ajout à la base de données
			if(empty($utilisateur_wiki_existe)) {
				// mot de passe généré à l'arrache, le mieux serait de trouver celui de tela encodé
				// mais en gérant bien le sso on peut s'en passer car l'utilisateur ne devrait jamais avoir 
				// à s'identifier par le wiki
				$pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
				$pass = substr(str_shuffle(str_repeat($pool, 16)), 0, 16);
				
				$this->wiki->Query("insert into ".$this->wiki->config["table_prefix"]."users set ".
						"signuptime = now(), ".
						"name = '".mysql_escape_string($token_decode['nomWiki'])."', ".
						"email = '".mysql_escape_string($token_decode['sub'])."', ".
						"password = md5('".mysql_escape_string($pass)."')");
			} else {
				// Un utilisateur peut déjà s'être inscrit sur le wiki avec un autre nom que son pseudo
				$nom_wiki = $utilisateur_wiki_existe[0]['name'];
				// s'il existe un enregistrement avec ce mail et un autre avec ce nomWiki on garde celui qui correspond au bon courriel
				foreach($utilisateur_wiki_existe as $utilisateur_wiki) {
					if($utilisateur_wiki['email'] == $courriel) {
						  $nom_wiki = $utilisateur_wiki['name'];
					}
				}
			}
		}

		return $nom_wiki;
	}

	function recupererIdentiteConnectee() {
		$infos_cookie = $this->getInfosCookie();
		if($infos_cookie == null || $infos_cookie['tentative_identification'] == false) {	
			// peut importe si l'annuaire répond oui ou non, on a fait une tentative d'identification
			// et si on a trouvé quelqu'un on ne réésaiera pas jusqu'à la fermeture du navigateur
			$infos_cookie = array('tentative_identification' => true, 'expire' => 0);
 			$this->setInfosCookie($infos_cookie);

			$annuaire_url = $this->wiki->config['sso_url'].'identite';
			// Attention si le paramètre wiki de l'url est vide, la redirection de retour pose des problèmes
			$url = $annuaire_url.'?redirect_url='.urlencode($this->wiki->config['base_url'].$this->getPage());

			header('Location: '.$url);
			exit;
		} else {
			$token = $this->getToken();

			if($token != null) {
				// On demande à l'annuaire si le jeton est bien valide
				$jeton_rafraichi = json_decode(file_get_contents($this->wiki->config['sso_url'].'rafraichir?token='.$token), true);
				$nom_wiki = $this->verifierEtInsererUtilisateurParJeton($jeton_rafraichi);
				$token_decode = $this->decoderToken($jeton_rafraichi['token']);

				// dans le pire des cas, si on se déconnecte dans une autre application, on sera déconnecté 
				// lorsque le jeton expirera
				$infos_cookie = array('tentative_identification' => true, 'expire' => time()+$jeton_rafraichi['duration']);
				$this->setInfosCookie($infos_cookie);

				$this->wiki->SetUser($this->wiki->LoadUser($nom_wiki));
			} else {
				// personne n'a été trouvé ? on remplace le cookie par un de durée plus courte 
				// pour rééssayer dans delai_tentative_identification si on en a pas déjà un
				if($infos_cookie['expire'] == 0) { 
					$infos_cookie['expire'] = time()+$this->delai_tentative_identification;
					$this->setInfosCookie($infos_cookie);
				}
			}
		}
	}

	function recupererIdentiteConnecteePourApi() {		
		$token = $this->getToken();
		if($token != null) {
			// On demande à l'annuaire si le jeton est bien valide
			$jeton_rafraichi = json_decode(file_get_contents($this->wiki->config['sso_url'].'rafraichir?token='.$token), true);
			$nom_wiki = $this->verifierEtInsererUtilisateurParJeton($jeton_rafraichi);
			$token_decode = $this->decoderToken($jeton_rafraichi['token']);
			$this->wiki->SetUser($this->wiki->LoadUser($nom_wiki));
		}
	}

	function connecterUtilisateur($login, $pass, $url_redirect = null) {
		if(strpos($login, '@') === false) {
			$utilisateur_wiki = $this->wiki->LoadSingle("SELECT email FROM  ".$this->wiki->config["table_prefix"]."users ".
			"WHERE name = '".mysql_escape_string($login)."'");

			$login = !empty($utilisateur_wiki) ? $utilisateur_wiki['email'] : $login;
			// TODO: si le courriel a changé dans l'annuaire, on devrait mettre à jour les informations 
			// si on a utilisé le nom wiki pour s'identifier mais le flow du programme rend cela complexe
		}

		$url_redirect = ($url_redirect == null) ? $this->wiki->config['base_url'].'PagePrincipale' : $url_redirect;

		// le cookie de tentative d'identification est remis à zéro pour qu'au rechargement de la page il vérifie l'identité 
		// connectée du nouvel utilisateur
		$infos_cookie = array('tentative_identification' => false, 'expire' => 0);
		$this->setInfosCookie($infos_cookie);
		// On demande à l'annuaire si l'utilisateur est bien valide
		$annuaire_url = $this->wiki->config['sso_url'].'connexion?login='.$login.'&password='.$pass;
		$url = $annuaire_url.'&redirect_url='.urlencode($url_redirect);

		header('Location: '.$url);
		exit;
	}

	function deconnecterUtilisateur($url_redirect = null) {
		$url_redirect = ($url_redirect == null) ? $this->wiki->config['base_url'].'PagePrincipale' : $url_redirect;
		// Suppression d'un eventuel jeton contenu dans l'url
		$url_redirect = $this->supprimerUrlVar($url_redirect, 'Authorization');
		
		$infos_cookie = array('tentative_identification' => false, 'expire' => 0);
 		$this->setInfosCookie($infos_cookie);
		// On demande à l'annuaire si l'utilisateur est bien valide
		$annuaire_url = $this->wiki->config['sso_url'].'deconnexion';
		$url = $annuaire_url.'?redirect_url='.urlencode($url_redirect);
		header('Location: '.$url);
		exit;
	}
}
?>