<?php
namespace YesWiki;
class User
{
	// Obviously needs a group or ACLS class. In the meantime, use of $GLOBALS['wiki']->GetGroupACL and so on

	/* ~~~~~~~~~~~~~~~~PROPERTIES~~~~~~~~~~~~~ */
	// User properties (cf database)
	 // The case is, on purpose, similar to the one in the database
	protected $name = '';
	protected $password = ''; // MD5 encrypted or not (retrieved from DB => encrypted)
	protected $email = '';
	protected $motto = '';
	protected $revisioncount = '';
	protected $changescount = '';
	protected $doubleclickedit = '';
	protected $show_comments = '';
	// End of user properties (cf database)

	public $error = '';

	protected $tablePrefix = '';
	protected $usersTable = '';
//	protected $runnerIsAdmin; // True if the user who actually runs the wiki session is admin, false otherwise

	protected $db;
	protected $session;

	protected $nameMaxLength = 80;
	protected $emailMaxLength = 254;
	protected $passwordMinimumLength = 5;


	/* Constructor
		Requires parameters:
			wiki->config
			queryLog
		Exemple :
			$myUser = new WikiUser($this->wiki->config, $this->queryLog)
		Sets :
			$tablePrefix
			$usersTable
			$dbLink
	*/
	public function __construct($config, &$queryLog, $cookiePath)
	{
		// // Set runnerIsAdmin
		// $this->runnerIsAdmin =  $this->setRunnerIsAdmin();
		// Set default prefix value of MySQL tables name
		$this->tablePrefix =  $config['table_prefix'];
		// Set value of MySQL user table name
		$this->setUsersTable($config['user_table_prefix']);
		require_once 'includes/WikiDB.class.php';
		$this->db = new \YesWiki\Database($config, $queryLog);
		// sets the session with cookiePath
		require_once 'includes/WikiSession.class.php';
		$this->session = new \YesWiki\Session($cookiePath);
	}

	/* ~~~~~~~~~~~~~~SETS PROPERTY METHODS ~~~~~~~~~~~~~~~~*/
	/*	True if the user who actually runs this wiki session is admin
		false otherwise
	*/
//	protected Function setRunnerIsAdmin()
	protected Function runnerIsAdmin()
	{
		$runner = $this->runner();
		if ($runner) {
			$adminAcl = $GLOBALS['wiki']->GetGroupACL(ADMIN_GROUP);
			if(preg_match('/\b'.$runner.'\b/',$adminAcl)) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	protected Function runner()
	{
		if (isset($_SESSION['user'])) {
			return $_SESSION['user']['name'];
		} else {
			return false;
		}
	}


	/* Sets the user table name
		In some cases, multiple wikis share a unique users table.
		This unique users table prefix is the specified in config.
		Therefore we must build $this->userstable using
			- this unique users table prefix if specified,
			or
			- the wiki default table prefix
	*/
	protected Function setUsersTable($users_table_prefix ='')
	{
		if (isset($users_table_prefix) && !empty($users_table_prefix)) {
			$usersTablePrefix =  $users_table_prefix;
		} else {
			$usersTablePrefix =  $this->tablePrefix;
		}
		$this->usersTable =  $usersTablePrefix.'users';
	}

	/* ~~~~~~~~~~~~~PROPERTY ACCESS METHODS~~~~~~~~~~~~~~~~~~~ */

	/*	Name must be set and its lenght must be less than 254 characters
	 	returns
			true if OK
			or false if any problems
		In case of failure $this->error contains the error message
	*/
	protected function checkName($newName)
	{
		$this->error ='';
		$result = false;
		if (!$newName or trim($newName) == '') {
			$this->error = _t('USER_YOU_MUST_SPECIFY_A_NAME').'.';
		} elseif (strlen($newName) > $this->nameMaxLength) {
			$this->error = _t('USER_NAME_S_MAXIMUM_LENGTH_IS').' '.$this->nameMaxLength.'.';
		} else {
			$result = true;
		}
		return $result;
	}

	/*	Name must be set and its lenght must be less than 254 characters
	 	returns
			true if OK
			or false if any problems
		In case of failure $this->error contains the error message
	*/
	public function setName($newName)
	{
		// NOTE: Can we change name ?
		$this->error ='';
		$newName = trim($newName);
		if ($this->checkName($newName)) {
			$this->name = $newName;
			$result = true;
		} else { // if checkName returns false, $this->error is set to the corresponding error message
			$result = false;
		}
		return $result;
	}

	public function getName()
	{
		if (isset($this->name)) {
			return $this->name;
		} else {
			return false;
		}
	}

	/*	returns
			true if OK
			or false if any problems
		In case of failure $this->error contains the error message
	*/
	protected function checkEmail($newEmail)
	{
		// NOTE: Can we change name ?
		$this->error ='';
		$result = false;
		if (!$newEmail or trim($newEmail) == '') {
			$this->error = _t('USER_YOU_MUST_SPECIFY_AN_EMAIL').'.';
		} elseif ($this->emailExistsInDB($newEmail)) {
			$this->error = _t('USER_THIS_EMAIL_IS_ALLREADY_USED_ON_THIS_WIKI').'.';
		} elseif (strlen($newEmail) > $this->emailMaxLength) {
			$this->error = _t('USER_EMAIL_S_MAXIMUM_LENGTH_IS').' '.$this->emailMaxLength.'.';
//		} elseif (!preg_match("/^.+?\@.+?\..+$/", $newEmail)) {
		} elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
			$this->error = _t('USER_THIS_IS_NOT_A_VALID_EMAIL').'.';
		} else {
			$result = true;
		}
		return $result;
	}

	/*	returns
			true if OK
			or false if any problems
		In case of failure $this->error contains the error message
	*/
	public function setEmail($newEmail)
	{
		// NOTE: Can we change email ?
		$this->error ='';
		$newEmail = trim($newEmail);
		$result = false;
		if ($newEmail == $this->email) { // That's the same email, do nothing and return True
			$result = true;
		} elseif ($this->checkEmail($newEmail)) { // New email, we check
			$result = true;
			$this->email = $newEmail;
		} else { // if checkEmail returns false, $this->error is set to the corresponding error message
			$result = false;
		}
		return $result;
	}

	public function getEmail()
	{
		if (isset($this->email)) {
			return $this->email;
		} else {
			return false;
		}
	}

	public function setMotto($newMotto)
	{
		$this->motto = trim($newMotto);
	}

	public function getMotto()
	{
		return $this->motto;
	}

	/*	Returns true if worked all right and false otherwise
		In case of failure $this->error contains the error message
	*/
	public function setRevisionsCount($newRevisionsCount)
	{
		$this->error = '';
		if (intval($newRevisionsCount) >= 0) {
			$this->revisioncount = intval($newRevisionsCount);
			$result = true;
		} else {
			$this->error = _t('USER_REVISIONS_COUNT_MUST_BE_A_POSITIVE_INTEGER').'.';
			$result = false;
		}
		return $result;
	}

	public function getRevisionsCount()
	{
		return $this->revisioncount;
	}

	/*	Returns true if worked all right and false otherwise
		In case of failure $this->error contains the error message
	*/
	public function setChangesCount($newChangesCount)
	{
		$this->error = '';
		if (intval($newChangesCount) >= 0) {
			$this->changescount = intval($newChangesCount);
			$result = true;
		} else {
			$this->error = _t('USER_CHANGES_COUNT_MUST_BE_A_POSITIVE_INTEGER').'.';
			$result = false;
		}
		return $result;
	}

	public function getChangesCount()
	{
		return $this->changescount;
	}

	public function setDoubleClickEdit($newDoubleClickEdit)
	{
		if ((strtolower($newDoubleClickEdit) == 'n') || (strtolower($newDoubleClickEdit) == 'no') || (strtolower($newDoubleClickEdit) == 'non')) {
			$this->doubleclickedit = 'N';
		} else {
			$this->doubleclickedit = 'Y';
		}
	}

	public function getDoubleClickEdit()
	{
		return $this->doubleclickedit;
	}

	public function setShowComments($newShowComments)
	{
		if ((strtolower($newShowComments) == 'o') || (strtolower($newShowComments) == 'oui') || (strtolower($newShowComments) == 'y') || (strtolower($newShowComments) == 'yes')) {
			$this->show_comments = 'Y';
		} else {
			$this->show_comments = 'N';
		}
	}

	public function getShowComments()
	{
		return $this->show_comments;
	}

	/*	sets user properties using an associative array
		Requires parametre $newValues => array()
			['name'] 				=> string()	Optional
			['email']	 			=> string()	Optional
			['password']			=> string()	Optional
			['motto']				=> string()	Optional
			['revisioncount']		=> integer()Optional
			['changescount']		=> integer()Optional
			['doubleclickedit']	=> string()	Optional
			['show_comments']		=> string()	Optional
	 	returns
			if OK 	 => true
			otherwise => false
							 $this->error contains the concatenation of the error messages
	*/
	public function setByAssociativeArray($newValues)
	{
		$this->error = '';
		$error = '';
		$result = true;
		if (isset($newValues['name']) && (trim($newValues['name']) != '')) {
			if (!$this->setName($newValues['name'])) {
				$result = false;
				$error .= $this->error;
			}
		}
		if (isset($newValues['email']) && (trim($newValues['email']) != '')) {
			if (!$this->setEmail($newValues['email'])) {
				$result = false;
				$error .= '\n'.$this->error;
			}
		}
		if (isset($newValues['password']) && (trim($newValues['password']) != '')) {
			if (!$this->setPassword($newValues['password'])) {
				$result = false;
				$error .= '\n'.$this->error;
			}
		}
		if (isset($newValues['motto'])) {
			$this->setMotto($newValues['motto']);
		}
		if (isset($newValues['revisioncount']) && (trim($newValues['revisioncount']) != '')) {
			if (!$this->setRevisionsCount($newValues['revisioncount'])) {
				$result = false;
				$error .= '\n'.$this->error;
			}
		}
		if (isset($newValues['changescount']) && (trim($newValues['changescount']) != '')) {
			if (!$this->setChangesCount($newValues['changescount'])) {
				$result = false;
				$error .= '\n'.$this->error;
			}
		}
		if (isset($newValues['doubleclickedit']) && (trim($newValues['doubleclickedit']) != '')) {
			$this->setDoubleClickEdit($newValues['doubleclickedit']);
		}
		if (isset($newValues['show_comments']) && (trim($newValues['show_comments']) != '')) {
			$this->setShowComments($newValues['show_comments']);
		}
		$this->error = $error;
		return $result;
 	}

	public function getAllInAssociativeArray()
	{
		$userValues = array(
			'name'				=> $this->name,
			'password'			=> $this->password,
			'email'				=> $this->email,
			'motto'				=> $this->motto,
			'revisioncount'	=> $this->revisioncount,
			'changescount'		=> $this->changescount,
			'doubleclickedit'	=> $this->doubleclickedit,
			'show_comments'	=> $this->show_comments,
		);
		return $userValues;
 	}

	/* ~~~~~~~~~~~~~PASSWORD HANDLING METHODS~~~~~~~~~~~~~~~~~~~ */
	/* Password recovery process (AKA reset password)
			1. A key is generated using name, email alongside with other stuff
			2. The triple (user's name, specific key "vocabulary",key) is stored in triples table.
			3. In order to update h·er·is password, the user must provided that key.
			4. The new password is accepted only if the key matches with the value in triples table.
			5. The corresponding row is removed from triples table.
		Normal change of password (requested via the usersettings page)(AKA update password)
	*/

	public function checkPassword($pwd)
	{
		if ($this->password != md5($pwd)) {
			$this->error = _t('USER_WRONG_PASSWORD').' !'; // NOTE: New message
			$result = false;
		} else {
			$result = true;
		}
		return $result;
	}

	/*	BEWARE returns true or false the other way around from other functions
		Returns
			True
				If the password is not compliant, ie
					contains spaces
					is too short ($passwordMinimumLength defines the minimum length)
				If $confPasswordis set and different from $pwd
				$this->error contains the error message
			False
				if the password looks good
	*/
	public function passwordIsIncorrect($pwd, $confPassword = '')
	{
		$incorrect = false;
		if (isset($confPassword) && (trim($confPassword) !='')) {
			if ($confPassword != $pwd) {
				$this->error = _t('USER_PASSWORDS_NOT_IDENTICAL').'.';
				$incorrect = true;
			}
		}
		if (preg_match('/ /', $pwd)) {
			$this->error = _t('USER_NO_SPACES_IN_PASSWORD').'.';
			$incorrect = true;
		} elseif (strlen($pwd) < $this->passwordMinimumLength) {
			$this->error = _t('USER_PASSWORD_TOO_SHORT').'. '._t('USER_PASSWORD_MINIMUM_NUMBER_OF_CHARACTERS_IS').' ' .$this->passwordMinimumLength.'.'; // NOTE: New message
			$incorrect = true;
		}
		return $incorrect;
	}

	/* replaces sendPasswordEmail() from login.functions.php
		See Password recovery process above
		TODO : Add error handling
	*/
	/*	Returns true if worked all right and false otherwise
		In case of failure $this->error contains the error message
	*/
	protected function setPassword($password, $confPassword = '')
	{
		$this->error = '';
		if (isset($confPassword) && ($confPassword != '')) {
			$OK = !$this->passwordIsIncorrect($password, $confPassword);
		} else {
			$OK = !$this->passwordIsIncorrect($password);
		} // $result is true if password IS correct and $this->error contains error if any
		if ($OK) { // password is correct
			$this->password = MD5($password);
		}
		return $OK;
	}

	public function sendPasswordRecoveryEmail()
	{
		$wiki = $GLOBALS['wiki'];
		// Generate the password recovery key
		$key = md5($this->name . '_' . $this->email . rand(0, 10000) . date('Y-m-d H:i:s') . PW_SALT);
		// Store the (name, vocabulary, key) triple in triples table
		$res = $wiki->InsertTriple($this->name, 'http://outils-reseaux.org/_vocabulary/key', $key);

		// Generate the recovery email
		$passwordLink = $wiki->Href() . '&a=recover&email=' . $key . '&u=' . urlencode(base64_encode($this->name));
		$pieces = parse_url($wiki->GetConfigValue('base_url'));
		$domain = isset($pieces['host']) ? $pieces['host'] : '';

		$message = _t('LOGIN_DEAR').' ' . $this->name . ",\n";
		$message .= _t('LOGIN_CLICK_FOLLOWING_LINK').' :' . "\n";
		$message .= '-----------------------' . "\n";
		$message .= $passwordLink . "\n";
		$message .= '-----------------------' . "\n";
		$message .= _t('LOGIN_THE_TEAM').' ' . $domain . "\n";

		$subject = _t('LOGIN_PASSWORD_LOST_FOR').' ' . $domain;
		// Send the email
		if (!function_exists('send_mail')) {
		require_once('includes/email.inc.php');
		}
		send_mail($wiki->config['BAZ_ADRESSE_MAIL_ADMIN'], $wiki->config['BAZ_ADRESSE_MAIL_ADMIN'], $this->email, $subject, $message);
	}

	/* replaces updateUserPassword($userID, $password, $key) from login.functions.php
		resetPassword is used when retrieving the password from a recovery email.
		See Password recovery process above
		returns
			if success	=> true
			otherwise	=> false
								$this->error describes the error
	*/
	public function resetPassword($key, $password, $confPassword='')
	{
		$this->error = '';
		if ($this->checkEmailKey($key) === false) { // The password recovery key does not match
			$this->error = _t('USER_INCORRECT_PASSWORD_KEY').'.'; // NOTE: New message
			$OK = false;
		} else { // The password recovery key matches
			if (isset($confPassword) && ($confPassword != '')) {
				$OK = $this->updatePassword($password, $confPassword);
			} else {
				$OK = $this->updatePassword($password);
			}
			if ($OK)// Was able to update password => Remove the key from triples table
			$res = $GLOBALS['wiki']->DeleteTriple($userID, 'http://outils-reseaux.org/_vocabulary/key', $key);
		}
		return $OK;
	}

	/* replaces checkEmailKey($key, $userID) from login.functions.php
		See Password recovery process above
		returns
			if success	=> true
			otherwise	=> false
		TODO : Add error handling
	*/
	public function checkEmailKey($key)
	{
		// Pas de detournement possible car utilisation de _vocabulary/key ....
		$res = $GLOBALS['wiki']->TripleExists($this->name, 'http://outils-reseaux.org/_vocabulary/key', $key);
		if ($res > 0) {
			$result = true;
		} else {
			$result = false;
		}
		return $result;
	}

	public function updatePassword($password, $confPassword='')
	{
		$this->error = '';
		if (isset($confPassword) && ($confPassword != '')) {
			$OK = !$this->passwordIsIncorrect($password, $confPassword);
		} else {
			$OK = !$this->passwordIsIncorrect($password);
		} // $result is true if password IS correct and $this->error contains error if any
		if ($OK) { // password is correct
			// Update user's password
			$sql	= 'UPDATE '.$this->usersTable;
			$sql .= ' SET password = "'.MD5($password).'" ';
			$sql .= 'WHERE name = "'.$this->name.'" LIMIT 1;';
			$OK = $this->db->query($sql); // true or false depending on the query execution
			if ($OK) {
				$this->password = md5($password);
			} else {
				$this->error = _t('USER_PASSWORD_UPDATE_FAILED').'.'; // NOTE: New message
			}
		}
		return $OK;
	}


	/* ~~~~~~~~~~~~~WEB SESSION METHODS~~~~~~~~~~~~~~~~~~~ */
	/* WEB SESSION
		Replaces $wiki->SetUser
		For a user to be logged in requires:
		-	a corresponding http session record
			(ie containing the database row converted to an associative array)
		-	a cookie set to the corresponding name and password
		Parameter
		-	$remember sets the cookie duration (0 means "ends with the session")
		Sets the http session and the cookie
		// TODO: Error handling
	*/
	public function logIn($remember = 0)
	{
		$_SESSION['user'] = array(
			'name'				=> $this->name,
			'password'			=> $this->password,
			'email'				=> $this->email,
			'motto'				=> $this->motto,
			'revisioncount'	=> $this->revisioncount,
			'changescount'		=> $this->changescount,
			'doubleclickedit'	=> $this->doubleclickedit,
			'show_comments'	=> $this->show_comments,
		);
		$this->session->setPersistentCookie('name', $this->name, $remember);
		$this->session->setPersistentCookie('password', $this->password, $remember);
		$this->session->setPersistentCookie('remember', $remember, $remember);
	}

	/* WEB SESSION
		Replaces $wiki->logOut()
		To log a user out, we:
		-	delete the corresponding http session record
		-	delete the cookie set to the corresponding name and password
		returns
			if success	=> true
			otherwise	=> false
								$this->error contains error message
		TODO check if $this is the current user
	*/
	public function logOut()
	{
		$OK = true;
		if (!isset($_SESSION['user'])) { // No one is logged in
			$this->error = _t('USER_NOT_LOGGED_IN_CANT_LOG_OUT').'.'; // NOTE: NEw message
			$OK = false;
		}
		if ($OK && !$this->isRunner()) { // The user who actually runs this session is not $user. Don't want to log the wrong one out
			$this->error = _t('USER_TRYING_TO_LOG_WRONG_USER_OUT').'.'; // NOTE: NEw message
			$OK = false;
		}
		if ($OK) {
			$_SESSION['user'] = '';
			$this->session->deleteCookie('name');
			$this->session->deleteCookie('password');
			$OK = true;
		}
		return $OK;
	}

	public function loadFromSession()
	{
		if(isset($_SESSION['user']) && $_SESSION['user'] != '') {
			$this->name					= $_SESSION['user']['name'];
			$this->password			= $_SESSION['user']['password'];
			$this->email				= $_SESSION['user']['email'];
			$this->motto				= $_SESSION['user']['motto'];
			$this->revisioncount		= $_SESSION['user']['revisioncount'];
			$this->changescount		= $_SESSION['user']['changescount'];
			$this->doubleclickedit	= $_SESSION['user']['doubleclickedit'];
			$this->show_comments		= $_SESSION['user']['show_comments'];
			$result = true;
		} else {
			$result = false;
		}
		return $result;
	}

	/*~~~~~~~~~~~ DATABASE METHODS ~~~~~~~~~~~~~~~*/

	/*	Create the row correponding to the user in database user table
		Returns true if worked all right and false otherwise
		In case of failure $this->error contains the error message
	*/
	public function createIntoDB()
	{
		$this->error = '';
		$result = false;
		$sql = 'insert into '.$this->usersTable.' set '.
			'signuptime = now(), '.
			'name = "'.$this->db->escapeString($this->name).'", '.
			'email = "'.$this->db->escapeString($this->email).'", '.
//			'password = md5("'.$this->db->escapeString($this->password).'")';
			'password = "'.$this->db->escapeString($this->password).'"'; // $this->password has already been md5ed.
		if (isset($this->motto)) {
			$sql .= ', motto = "'.$this->db->escapeString($this->motto).'"';
		}
		if (isset($this->revisioncount)) {
			$sql .= ', revisioncount = "'.$revisioncount.'"';
		}
		if (isset($this->changescount)) {
			$sql .= ', changescount = "'.$changescount.'"';
		}
		if (isset($this->doubleclickedit)) {
			$sql .= ', doubleclickedit = "'.$this->db->escapeString($this->doubleclickedit).'"';
		}
		if (isset($this->show_comments)) {
			$sql .= ', show_comments = "'.$this->db->escapeString($this->show_comments).'"';
		}
		$sql .= ';';
		$queryResult = $this->db->query($sql);
		if ($queryResult) {
			$result = true;
		} else {
			$this->error = _t('USER_CREATION_FAILED').'.'; // NOTE: New message remplaces
		}
		return $result;
	}

	/*	Populates the user's properties from the database.
		The array contains the row corresponding to the user in the database.
		Each database col corresponds to an array item.
		returns
			if successful => true
			otherwise	  => false
								  $this->error contains the error code when accurate
	*/
	public function loadByNameFromDB($name, $password = 0)
	{
		$this->error = '';
		$sql = 'SELECT * FROM '.$this->usersTable.' WHERE name = "'.$this->db->escapeString($name).'" ';
		if ($password == 0){
			$sql .= '';
		} else { // If password has been specified
			$sql .= 'AND password = MD5("'.$this->db->escapeString($password).'")';
		}
		$sql .= ' LIMIT 1;'; // fetches the first corresponding line, and only that one
		$row = $this->db->loadSingle($sql);
		if ($row){
			$this->name					= $row['name'];
			$this->password			= md5($row['password']);
			$this->email				= $row['email']; // THe case is, on purpose, similar to the one in the database
			$this->motto				= $row['motto'];
			$this->revisioncount		= $row['revisioncount'];
			$this->changescount		= $row['changescount'];
			$this->doubleclickedit	= $row['doubleclickedit'];
			$this->show_comments		= $row['show_comments'];
			$result = true;
		} elseif ($row === false) {
			$this->error = _t('USER_LOAD_BY_NAME_QUERY_FAILED').'.'; // NOTE: new message
			$result = false;
		} else {
			$this->error = _t('USER_NO_USER_WITH_THAT_NAME').'.'; // NOTE: new message
			$result = false;
		}
		return $result;
	}

	/*	Populates the user's properties from the database.
		The array contains the row corresponding to the user in the database.
		Each database col corresponds to an array item.
		returns true if successful
		false otherwise
		$this->error contains the error code when accurate
	*/
	function loadByEmailFromDB($email, $password = 0)
	{
		$this->error = '';
		$sql = 'SELECT * FROM '.$this->usersTable.' WHERE email = "'.$this->db->escapeString($email).'" ';
		if ($password == 0){
			$sql .= '';
		} else { // If password has been specified
			$sql .= 'AND password = MD5("' . $this->db->escapeString($password) . '")';
		}
		$sql .= ' LIMIT 1;'; // fetches the first corresponding line, and only that one
		$row = $this->db->loadSingle($sql);
		if ($row){
			$this->name					= $row['name'];
			$this->password			= md5($row['password']);
			$this->email				= $row['email']; // THe case is, on purpose, similar to the one in the database
			$this->motto				= $row['motto'];
			$this->revisioncount		= $row['revisioncount'];
			$this->changescount		= $row['changescount'];
			$this->doubleclickedit	= $row['doubleclickedit'];
			$this->show_comments		= $row['show_comments'];
			$result = true;
		} elseif ($row === false) {
			$this->error = _t('USER_LOAD_BY_EMAIL_QUERY_FAILED').'.'; // NOTE: new message
			$result = false;
		} else {
			$this->error = _t('USER_NO_USER_WITH_THAT_EMAIL').'.'; // NOTE: new message
			$result = false;
		}
		return $result;
	}

	/*	Updates the row corresponding to the user in the database.
		BEWARE
		You cannot modify password using that fonction, use updatePassword() instead.
		Parameter
			$fieldsToUpdate is a string, listing the fields to update in the DB using $this properties
			values are comma separated i.e.: 'motto, changescount'
	*/
	public function updateIntoDB($fieldsToUpdate = '')
	{
		// NOTE: Can we update name ?
		$this->error = '';
		$fieldsTab = array_map('trim', explode(',',$fieldsToUpdate));
		if ((count($fieldsTab) == 0) || ((count($fieldsTab) == 1) && ($fieldsTab[0] == ''))){ // Obviously empty => Then we update all but name and pwd
			$fieldsTab = array(
				'email',
				'motto',
				'revisioncount',
				'changescount',
				'doubleclickedit',
				'show_comments',
			);
		}
		$prefixe = false;
		$setClause ='';
		foreach ($fieldsTab as $field) {
			switch ($field) {
				case '':
					if ($prefixe){
						$setClause .= ',';
					}
					$setClause .= ' email = "'.$this->email.'"';
					$prefixe = true;
					break;
				case 'motto':
					if ($prefixe){
						$setClause .= ',';
					}
					$setClause .= ' motto = "'.$this->motto.'"';
					$prefixe = true;
					break;
				case 'revisioncount':
					if ($prefixe){
						$setClause .= ',';
					}
					$setClause .= ' revisioncount = "'.$this->revisioncount.'"';
					$prefixe = true;
					break;
				case 'changescount':
					if ($prefixe){
						$setClause .= ',';
					}
					$setClause .= ' changescount = "'.$this->changescount.'"';
					$prefixe = true;
					break;
				case 'doubleclickedit':
					if ($prefixe){
						$setClause .= ',';
					}
					$setClause .= ' doubleclickedit = "'.$this->doubleclickedit.'"';
					$prefixe = true;
					break;
				case 'show_comments':
					if ($prefixe){
						$setClause .= ',';
					}
					$setClause .= ' show_comments = "'.$this->show_comments.'"';
					$prefixe = true;
					break;
			} //End switch
		} // End foreach
		if ($prefixe) { // At least one field to update
			$sql = 'UPDATE '.$this->usersTable.' SET '.$setClause;
			$sql .= ' WHERE name = "'.$this->db->escapeString($this->name).'" LIMIT 1;';
			$result = $this->db->query($sql);
			if ($result) {
				$error = '';
			} else {
				$this->error = _t('USER_UPDATE_QUERY_FAILED').'.';
			}
		} else {
			$this->error = _t('USER_UPDATE_MISSPELLED_PROPERTIES').'.'; // NOTE: New message
			$result = false;
		}
		return $result;
	}

	/*	NOTE: Doesn't make any sense in a user class (singular)
		code comes from tools/login/libs/login.class.inc.php
		and should stay there for the time being
	public function loadUsers()
	{
		 return $this->db->loadAll("select * from " . $this->usersTable . " order by name");
	}
	*/

	/*	Only Admins can delete a user and can't delete themselves

		More than a row in users database table, users may
		appear in groups and as owners of pages.
		If the user is the only member of at least one group,
			an error is raised and the deletion is not performed.
		Otherwise,
			The user is removed from every group
			The ownership of each page owned by this user is set to NULL
			The user row is deleted from user table

		Returns
			If success => true
			Otherwise  => false
							  $this->error contains the error message
	*/
	public function delete()
	{
		$this->error = '';
		$OK = true;
		if (!$this->runnerIsAdmin()) { // actual user is not admin
			$this->error = _t('USER_MUST_BE_ADMIN_TO_DELETE').'.';
			$OK = false;
		}
		if ($this->isRunner()) { // actual user is trying to delete oneself
			$this->error = _t('USER_CANT_DELETE_ONESELF').'.'; // NOTE: New message
			$OK = false;
		}

		if ($OK) {
			$grouptab = $this->listGroupMemberships(); // All the groups with this user in
			foreach ($grouptab as $group) {
				$groupmembers = $GLOBALS['wiki']->GetGroupACL($group);
				$groupmembers = explode('\n', $groupmembers);
				$groupmembers = array_map('trim', $groupmembers);
				if (count($groupmembers) == 1) { // Only one user in (this user then)
					$this->error = _t('USER_DELETE_LONE_MEMBER_OF_GROUP').'.'; // NOTE New message
					//				$this->error .= 'La suppression de cet utilisateur est impossible car c\'est l\'unique membre du groupe @'.$group.'. Faîtes en sorte que ce ne soit plus le cas avant de tenter à nouveau de le supprimer.';
					$OK = false;
				}
			}
			if ($OK) {
				// Delete user in every group
				$triplesTable = $this->tablePrefix.'triples';
				$searched_value = '%' . $this->name . '%';
				$seek_value_bf = '' . $this->name . '\n'; // username to delete can be followed by another username
				$seek_value_af = '\n' . $this->name; // username to delete can follow another username
				// get rid of this username everytime it's followed by another
				$sql  = 'UPDATE '.$triplesTable.'';
				$sql .= ' SET value = REPLACE(value, "'.$seek_value_bf.'", "")';
				$sql .= ' WHERE resource LIKE "'.GROUP_PREFIX.'%" and value LIKE "'.$searched_value.'";';
				$OK = $this->db->query($sql);
				if (!$OK) {
					$this->error = _t('USER_DELETE_QUERY_FAILED').'.';
				}
				// in the remaining get rid of this username everytime it follows another
				if ($OK) {
					$sql  = 'UPDATE `'.$triplesTable.'`';
					$sql .= ' SET `value` = REPLACE(`value`, "'.$seek_value_af.'", "")';
					$sql .= ' WHERE `resource` LIKE "'.GROUP_PREFIX.'%" and `value` LIKE "'.$searched_value.'";';
					$OK = $this->db->query($sql);
					if (!$OK) {
						$this->error = _t('USER_DELETE_QUERY_FAILED').'.';
					}
				}
				// For each page belonging to the user, set the ownership to null
				if ($OK) {
					$pagesTable = $this->tablePrefix.'pages';
					$sql = 'UPDATE `'.$pagesTable.'`';
					// $sql .= ' SET `owner` = NULL';
					$sql .= ' SET `owner` = "" ';
					$sql .= ' WHERE `owner` = "'.$this->name.'";';
					$OK = $this->db->query($sql);
					if (!$OK) {
						$this->error = _t('USER_DELETE_QUERY_FAILED').'.';
					}
				}
				// Delete the user row from the user table
				if ($OK) {
					$sql = 'DELETE FROM `'.$this->usersTable.'`';
					$sql .= ' WHERE `name` = "'.$this->name.'";';
					$OK = $this->db->query($sql);
					if (!$OK) {
						$this->error = _t('USER_DELETE_QUERY_FAILED').'.';
					}
				}
			}
		}
		return $OK;
	}

	/* ~~~~~~~~~~~~~~~~~INFO METHODS~~~~~~~~~~~~~~~~~~*/
	/*	True if $user is the one who actually runs this wiki session
		false otherwise
	*/
	public Function isRunner()
	{
		if (isset($_SESSION['user']) && ($_SESSION['user']['name'] == $this->name)) {
			return true;
		} else {
			return false;
		}
	}

	public function isAdmin()
	{
		return $this->isInGroup(ADMIN_GROUP);
	}

	/* returns an array of user names => true
	or false if no matches */
	protected function emailExistsInDB($email)
	{
		/* Build sql query*/
		$sql  = 'SELECT * FROM '.$this->usersTable;
		$sql .= ' WHERE email = "'.$email.'";';
		/* Execute query */
		$results = $this->db->loadAll($sql);
		return $results; // If the password does not already exist in DB, $result is an empty table => false
	}

	public function isInGroup($groupName)
	{
	//	public function UserIsInGroup($group, $user = null, $admincheck = true)
		return $GLOBALS['wiki']->CheckACL($GLOBALS['wiki']->GetGroupACL($groupName), $this->name, false);
	}

	// returns an array of group names
	public function listGroupMemberships()
	{
		/* Build sql query*/
		$triplesTable = $this->tablePrefix.'triples';
		$sql  = 'SELECT resource FROM '.$triplesTable;
		$sql .= ' WHERE resource LIKE "'.GROUP_PREFIX.'%"';
		$sql .= ' AND property LIKE "'.WIKINI_VOC_ACLS_URI.'"';
		$sql .= ' AND value LIKE "%'.$this->name.'%";';
		/* Execute query */
		$results = array();
		if ($groups = $this->db->loadAll($sql)) {
			foreach ($variable as $key => $groupName) {
				$results[] = ltrim($groupName, "@ \t\n\r\0\xOB");
			}
			return $results;
		} else {
			$error = _t('USER_LISTGROUPMEMBERSHIPS_QUERY_FAILED').'.';
			return $error;
		}
	}

} //end WikiUser class
?>
