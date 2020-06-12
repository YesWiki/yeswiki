<?php
namespace YesWiki;

class User
{
    // Obviously needs a group or ACLS class. In the meantime, use of $this->wiki->GetGroupACL and so on

    /* ~~~~~~~~~~~~~~~~PROPERTIES~~~~~~~~~~~~~ */
    // User properties (cf database)
    // The case is, on purpose, similar to the one in the database
    protected $properties = [
        'name' => '',
        'password' => '', // MD5 encrypted or not (retrieved from DB => encrypted)
        'email' => '',
        'motto' => '',
        'revisioncount' => '',
        'changescount' => '',
        'doubleclickedit' => '',
        'show_comments' => '',
    ];
    // End of user properties (cf database)

    public $error = '';
    protected $wiki = ''; // give access to the main wiki object
    protected $usersTable = '';
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
            $usersTable
            $dbLink
    */
    public function __construct($wiki)
    {
        $this->wiki = $wiki;
        $this->initUsersTable();
        $this->initLimitations();
    }

    /* ~~~~~~~~~~~~~~SETS PROPERTY METHODS ~~~~~~~~~~~~~~~~*/

    /**
     * Tells if the user who actually runs the wiki session is admin
     *
     * @param none
     * 
     * @return boolean True if the user who actually runs the session is admin, false otherwise.
     */
    protected function runnerIsAdmin()
    {
        return $this->wiki->UserIsAdmin();
    }

    /**
     * Sets the users table name
     * 
     * In some cases, multiple wikis share a unique users table.
     * This unique users table prefix is the one specified in config.
     * Therefore we must build $this->userstable using
     * - this unique users table prefix if specified,
     *   or
     * - the wiki default table prefix
     *
     * @param none
     *
     * @return void
     */ 
     protected function initUsersTable()
    {
        // Set value of MySQL user table name
        if (!empty($this->wiki->config['user_table_prefix'])) {
            $usersTablePrefix =  $this->wiki->config['user_table_prefix'];
        } else {
            $usersTablePrefix =  $this->wiki->config['table_prefix'];
        }
        $this->usersTable =  $usersTablePrefix.'users';
    }
    
    /**
     * Initializes object limitation properties using values from the config file
     * 
     * Initialiezd properties are:
     * - $this->nameMaxLength (default value = 80)
     * - $this->emailMaxLength (default value = 254)
     * - $this->passwordMinimumLength (default value = 5)
     * 
     * @param none
     *
     * @return void
     */
    protected function initLimitations()
    {
        if (!empty($this->wiki->config['user_name_max_length'])) {
            if (filter_var($this->wiki->config['user_name_max_length'], FILTER_VALIDATE_INT)) {
                $this->nameMaxLength = $this->wiki->config['user_name_max_length'];
            } else {
                $this->error = _t('USER_NAME_MAX_LENGTH_NOT_INT');
            }
        }
        if (!empty($this->wiki->config['user_email_max_length'])) {
            if (filter_var($this->wiki->config['user_email_max_length'], FILTER_VALIDATE_INT)) {
                $this->emailMaxLength = $this->wiki->config['user_email_max_length'];
            } else {
                $this->error = _t('USER_EMAIL_MAX_LENGTH_NOT_INT');
            }
        }
        if (!empty($this->wiki->config['user_password_min_length'])) {
            if (filter_var($this->wiki->config['user_password_min_length'], FILTER_VALIDATE_INT)) {
                $this->passwordMinimumLength = $this->wiki->config['user_password_min_length'];
            } else {
                $this->error = _t('USER_PASSWORD_MIN_LENGTH_NOT_INT');
            }
        }
    }

    /* ~~~~~~~~~~~~~PROPERTY ACCESS METHODS~~~~~~~~~~~~~~~~~~~ */
    /**
     * Checks that a given value complies with the requirements for a given property
     * 
     * In case of failure $this->error contains the error message
     *
     * @param string $propertyName Name of the property to check against.
     * @param string $newValue Value to check.
     * @return boolean true if worked all right and false otherwise
     */
    public function checkProperty($propertyName, $newValue)
    {
        $result = false;
        $newValue = trim($newValue);
        switch ($propertyName) {
            case 'name':
                $result = $this->checkName($newValue);
                break;
            case 'email':
                $result = $this->checkEmail($newValue);
                break;
            case 'password':
                $result = $this->checkPassword($newValue);
                break;
            case 'revisioncount':
            case 'changescount':
                $newValue = intval($newValue);
                if (empty($newValue) || !filter_var($newValue, FILTER_VALIDATE_INT) || $newValue < 0) {
                    $this->error = _t('USER_YOU_MUST_SPECIFY_A_POSITIVE_INTEGER_FOR').' '.$propertyName.'.';
                } else {
                    $result = true;
                }
                break;
            case 'show_comments':
            case 'doubleclickedit':
                $value = strtolower($newValue);
                if (in_array($value, ['o', 'oui', 'y', 'yes', 'n', 'non', 'no', '0', '1'])) {
                    $this->error = _t('USER_YOU_MUST_SPECIFY_YES_OR_NO').' '.$propertyName.'.';
                } else {
                    $result = true;
                }
                break;
            default:
                $result = !empty($newValue);
        }
        return $result;
    }


    /**
     * Sets a given property to a given value
     * 
     * In case of failure $this->error contains the error message
     *
     * @param string $propertyName Name of the property to set.
     * @param string $newValue Value to set the property with.
     * @param string $confValue optional Only used when property name egals 'password'. Confirmation value of the password.
     * @return boolean true if worked all right and false otherwise
     */
    public function setProperty($propertyName, $newValue, $confValue = '')
    {
        $this->error ='';
        $newValue = trim($newValue);
        if ($this->checkProperty($propertyName, $newValue)) {
            switch ($propertyName) {
                case 'password':
                    if (!empty($confValue)) {
                        $OK = !$this->passwordIsIncorrect($newValue, $confValue);
                    } else {
                        $OK = !$this->passwordIsIncorrect($newValue);
                    } // $result is true if password IS correct and $this->error contains error if any
                    if ($OK) { // password is correct
                        $newValue = MD5($newValue);
                    }
                    break;
                case 'revisioncount':
                case 'changescount':
                    $newValue = intval($newValue);
                    break;
                case 'show_comments':
                case 'doubleclickedit':
                    $value = strtolower($newValue);
                    if (in_array($value, ['o', 'oui', 'y', 'yes', '1'])) {
                        $newValue = 'Y';
                    } else {
                        $newValue = 'N';
                    }
                    break;
            }
            $this->$propertyName = $newValue;
            $result = true;
        } else {
            $result = false;
        }
        return $result;
    }

     /**
     * Gets the value of a given property
     * 
     * @param string $propertyName Name of the property from which the value is retrieved.
     * @return mixed The property value (string) or false in case of failure.
     */
    public function getProperty($propertyName)
    {
        if (isset($this->properties[$propertyName])) {
            return $this->properties[$propertyName];
        } else {
            return false;
        }
    }

    /**
     * Checks if a value is fit for name property.
     *
     * Name must be set and its lenght must be less than nameMaxLength characters
     * In case of failure $this->error contains the error message
     * 
     * @param string $newName
     * @return boolean True if OK or false if any problems
     */	
    protected function checkName($newName)
    {
        $this->error = '';
        $result = false;
        if (empty($newName)) {
            $this->error = _t('USER_YOU_MUST_SPECIFY_A_NAME').'.';
        } elseif (strlen($newName) > $this->nameMaxLength) {
            $this->error = _t('USER_NAME_S_MAXIMUM_LENGTH_IS').' '.$this->nameMaxLength.'.';
        } else {
            $result = true;
        }
        return $result;
    }

    /**
     * Checks if a value is fit for email property.
     *
     * In case of failure $this->error contains the error message
     * 
     * @param string $newEmail
     * @return boolean True if OK or false if any problems
     */	
    protected function checkEmail($newEmail)
    {
        // NOTE: Can we change name ?
        $this->error ='';
        $result = false;
        if (empty($newEmail)) {
            $this->error = _t('USER_YOU_MUST_SPECIFY_AN_EMAIL').'.';
        } elseif ($newEmail == $this->properties['email']) { // if email is the current user's email
            $result = true;
        } elseif ($this->emailExistsInDB($newEmail)) {
            $this->error = _t('USER_THIS_EMAIL_IS_ALLREADY_USED_ON_THIS_WIKI').'.';
        } elseif (strlen($newEmail) > $this->emailMaxLength) {
            $this->error = _t('USER_EMAIL_S_MAXIMUM_LENGTH_IS').' '.$this->emailMaxLength.'.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error = _t('USER_THIS_IS_NOT_A_VALID_EMAIL').'.';
        } else {
            $result = true;
        }
        return $result;
    }


    /**	sets user properties using an associative array
     * 
     * In case of failure $this->error contains the error message
     * parameter $newValues = Array(
     *      ['name'] 			=> string()	Optional
     *      ['email']	 		=> string()	Optional
     *      ['password']		=> string()	Optional
     *      ['motto']			=> string()	Optional
     *      ['revisioncount']	=> integer()Optional
     *      ['changescount']	=> integer()Optional
     *      ['doubleclickedit'] => string()	Optional
     *      ['show_comments']	=> string()	Optional
     *      )
     * 
     * @param string[] $newValues Associative array containing object property values
     * @return boolean True if OK or false if any problems
    */
    public function setByAssociativeArray($newValues)
    {
        $this->error = '';
        $error = '';
        $result = true;
        if (isset($newValues['name']) && (trim($newValues['name']) != '')) {
            if (!$this->setProperty('name', $newValues['name'])) {
                $result = false;
                $error .= $this->error;
            }
        }
        if (isset($newValues['email']) && (trim($newValues['email']) != '')) {
            if (!$this->setProperty('email', $newValues['email'])) {
                $result = false;
                $error .= '\n'.$this->error;
            }
        }
        if (isset($newValues['password']) && (trim($newValues['password']) != '')) {
            if (!$this->setProperty('password', $newValues['password'])) {
                $result = false;
                $error .= '\n'.$this->error;
            }
        }
        if (isset($newValues['motto']) && trim($newValues['motto']) != '') {
            $this->setProperty('motto', $newValues['motto']);
        }
        if (isset($newValues['revisioncount']) && (trim($newValues['revisioncount']) != '')) {
            if (!$this->setProperty('revisioncount', $newValues['revisioncount'])) {
                $result = false;
                $error .= '\n'.$this->error;
            }
        }
        if (isset($newValues['changescount']) && (trim($newValues['changescount']) != '')) {
            if (!$this->setProperty('changescount', $newValues['changescount'])) {
                $result = false;
                $error .= '\n'.$this->error;
            }
        }
        if (isset($newValues['doubleclickedit']) && (trim($newValues['doubleclickedit']) != '')) {
            $this->setProperty('doubleclickedit', $newValues['doubleclickedit']);
        }
        if (isset($newValues['show_comments']) && (trim($newValues['show_comments']) != '')) {
            $this->setProperty('show_comments', $newValues['show_comments']);
        }
        $this->error = $error;
        return $result;
    }

    /**	gets every user properties and put them into an associative array
     * 
     * If parameter $format is set to 'array', then returns an associative array:
     *  Array(
     *      ['name'] 			=> string()	Optional
     *      ['email']	 		=> string()	Optional
     *      ['password']		=> string()	Optional
     *      ['motto']			=> string()	Optional
     *      ['revisioncount']	=> integer()Optional
     *      ['changescount']	=> integer()Optional
     *      ['doubleclickedit'] => string()	Optional
     *      ['show_comments']	=> string()	Optional
     *      )
     * 
     * @param string $format optional describes the type of return (array by default or json)
     * @return mixed An array or a json depending on parameter value
    */
    public function getAllProperties($format = 'array')
    {
        if ($format == 'array') {
            return $this->properties;
        } elseif ($format == 'json') {
            return json_encode($this->properties);
        }
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
    /**
     * check if passed string is the user's password
     *
     * @param [string] $pwd
     * @return boolean
     */
    public function checkPassword($pwd)
    {
        if ($this->properties['password'] != md5($pwd)) {
            $this->error = _t('USER_WRONG_PASSWORD').' !';
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
        if (strlen($pwd) < $this->passwordMinimumLength) {
            $this->error = _t('USER_PASSWORD_TOO_SHORT').'. '._t('USER_PASSWORD_MINIMUM_NUMBER_OF_CHARACTERS_IS').' ' .$this->passwordMinimumLength.'.';
            $incorrect = true;
        }
        return $incorrect;
    }

    public function sendPasswordRecoveryEmail()
    {
        // Generate the password recovery key
        $key = md5($this->properties['name'] . '_' . $this->properties['email'] . rand(0, 10000) . date('Y-m-d H:i:s') . PW_SALT);
        // Store the (name, vocabulary, key) triple in triples table
        $res = $this->wiki->InsertTriple($this->properties['name'], 'http://outils-reseaux.org/_vocabulary/key', $key, '', '');

        // Generate the recovery email
        $passwordLink = $this->wiki->Href() . '&a=recover&email=' . $key . '&u=' . urlencode(base64_encode($this->properties['name']));
        $pieces = parse_url($this->wiki->GetConfigValue('base_url'));
        $domain = isset($pieces['host']) ? $pieces['host'] : '';

        $message = _t('LOGIN_DEAR').' ' . $this->properties['name'] . ",\n";
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
        return send_mail($this->wiki->config['BAZ_ADRESSE_MAIL_ADMIN'], $this->wiki->config['BAZ_ADRESSE_MAIL_ADMIN'], $this->properties['email'], $subject, $message);
    }

    /* replaces updateUserPassword($userID, $password, $key) from login.functions.php
        resetPassword is used when retrieving the password from a recovery email.
        See Password recovery process above
        returns
            if success	=> true
            otherwise	=> false
                                $this->error describes the error
    */
    public function resetPassword($user, $key, $password, $confPassword='')
    {
        $this->error = '';
        if ($this->checkEmailKey($key, $user) === false) { // The password recovery key does not match
            $this->error = _t('USER_INCORRECT_PASSWORD_KEY').'.';
            $OK = false;
        } else { // The password recovery key matches
            if (isset($confPassword) && ($confPassword != '')) {
                $OK = $this->updatePassword($password, $confPassword);
            } else {
                $OK = $this->updatePassword($password);
            }
            if ($OK) {// Was able to update password => Remove the key from triples table
                $res = $this->wiki->DeleteTriple($userID, 'http://outils-reseaux.org/_vocabulary/key', $key, '', '');
            }
        }
        return $OK;
    }

    /* replaces checkEmailKey($hash, $key) from login.functions.php
        See Password recovery process above
        returns
            if success	=> true
            otherwise	=> false
        TODO : Add error handling
    */
    public function checkEmailKey($hash, $u)
    {
        // Pas de detournement possible car utilisation de _vocabulary/key ....
        $res = $this->wiki->GetTripleValue(base64_decode($u), 'http://outils-reseaux.org/_vocabulary/key', '', '');
        if ($res == $hash) {
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
            $sql .= 'WHERE name = "'.$this->properties['name'].'" LIMIT 1;';
            $OK = $this->wiki->query($sql); // true or false depending on the query execution
            if ($OK) {
                $this->properties['password'] = md5($password);
            } else {
                $this->error = _t('USER_PASSWORD_UPDATE_FAILED').'.';
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
            'name'				=> $this->properties['name'],
            'password'			=> $this->properties['password'],
            'email'				=> $this->properties['email'],
            'motto'				=> $this->properties['motto'],
            'revisioncount' 	=> $this->properties['revisioncount'],
            'changescount'		=> $this->properties['changescount'],
            'doubleclickedit'	=> $this->properties['doubleclickedit'],
            'show_comments' 	=> $this->properties['show_comments'],
        );
        $this->wiki->setPersistentCookie('name', $this->properties['name'], $remember);
        $this->wiki->setPersistentCookie('password', $this->properties['password'], $remember);
        $this->wiki->setPersistentCookie('remember', $remember, $remember);
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
            $this->error = _t('USER_NOT_LOGGED_IN_CANT_LOG_OUT').'.';
            $OK = false;
        }
        if ($OK && !$this->isRunner()) { // The user who actually runs this session is not $user. Don't want to log the wrong one out
            $this->error = _t('USER_TRYING_TO_LOG_WRONG_USER_OUT').'.';
            $OK = false;
        }
        if ($OK) {
            $_SESSION['user'] = '';
            $this->wiki->session->deleteCookie('name');
            $this->wiki->session->deleteCookie('password');
            $OK = true;
        }
        return $OK;
    }

    public function loadFromSession()
    {
        if (isset($_SESSION['user']) && $_SESSION['user'] != '') {
            $this->properties['name']			= $_SESSION['user']['name'];
            $this->properties['password']		= $_SESSION['user']['password'];
            $this->properties['email']			= $_SESSION['user']['email'];
            $this->properties['motto']			= $_SESSION['user']['motto'];
            $this->properties['revisioncount']	= $_SESSION['user']['revisioncount'];
            $this->properties['changescount']	= $_SESSION['user']['changescount'];
            $this->properties['doubleclickedit']= $_SESSION['user']['doubleclickedit'];
            $this->properties['show_comments']	= $_SESSION['user']['show_comments'];
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
            'name = "'.mysqli_real_escape_string($this->wiki->dblink, $this->properties['name']).'", '.
            'email = "'.mysqli_real_escape_string($this->wiki->dblink, $this->properties['email']).'", '.
            'password = "'.mysqli_real_escape_string($this->wiki->dblink, $this->properties['password']).'"'; // has already been md5ed.
        if (isset($this->properties['motto'])) {
            $sql .= ', motto = "'.mysqli_real_escape_string($this->wiki->dblink, $this->properties['motto']).'"';
        }
        if (isset($this->properties['revisioncount'])) {
            $sql .= ', revisioncount = "'.$this->properties['revisioncount'].'"';
        }
        if (isset($this->properties['changescount'])) {
            $sql .= ', changescount = "'.$this->properties['changescount'].'"';
        }
        if (isset($this->properties['doubleclickedit'])) {
            $sql .= ', doubleclickedit = "'.mysqli_real_escape_string($this->wiki->dblink, $this->properties['doubleclickedit']).'"';
        }
        if (isset($this->properties['show_comments'])) {
            $sql .= ', show_comments = "'.mysqli_real_escape_string($this->wiki->dblink, $this->properties['show_comments']).'"';
        }
        $sql .= ';';
        $queryResult = $this->wiki->query($sql);
        if ($queryResult) {
            $result = true;
        } else {
            $this->error = _t('USER_CREATION_FAILED').'.';
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
        $sql = 'SELECT * FROM '.$this->usersTable.' WHERE name = "'.mysqli_real_escape_string($this->wiki->dblink, $name).'" ';
        if ($password == 0) {
            $sql .= '';
        } else { // If password has been specified
            $sql .= 'AND password = MD5("'.mysqli_real_escape_string($this->wiki->dblink, $password).'")';
        }
        $sql .= ' LIMIT 1;'; // fetches the first corresponding line, and only that one
        $row = $this->wiki->loadSingle($sql);
        if ($row) {
            $this->properties['name']			= $row['name'];
            $this->properties['password']		= $row['password'];
            $this->properties['email']			= $row['email'];
            $this->properties['motto']			= $row['motto'];
            $this->properties['revisioncount']	= $row['revisioncount'];
            $this->properties['changescount']	= $row['changescount'];
            $this->properties['doubleclickedit']= $row['doubleclickedit'];
            $this->properties['show_comments']	= $row['show_comments'];
            $result = true;
        } elseif ($row === false) {
            $this->error = _t('USER_LOAD_BY_NAME_QUERY_FAILED').'.';
            $result = false;
        } else {
            $this->error = _t('USER_NO_USER_WITH_THAT_NAME').'.';
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
    public function loadByEmailFromDB($email, $password = 0)
    {
        $this->error = '';
        $sql = 'SELECT * FROM '.$this->usersTable.' WHERE email = "'.mysqli_real_escape_string($this->wiki->dblink, $email).'" ';
        if ($password == 0) {
            $sql .= '';
        } else { // If password has been specified
            $sql .= 'AND password = MD5("' . mysqli_real_escape_string($this->wiki->dblink, $password) . '")';
        }
        $sql .= ' LIMIT 1;'; // fetches the first corresponding line, and only that one
        $row = $this->wiki->loadSingle($sql);
        if ($row) {
            $this->properties['name']				= $row['name'];
            $this->properties['password']			= $row['password'];
            $this->properties['email']				= $row['email']; 
            $this->properties['motto']				= $row['motto'];
            $this->properties['revisioncount']		= $row['revisioncount'];
            $this->properties['changescount']		= $row['changescount'];
            $this->properties['doubleclickedit']  	= $row['doubleclickedit'];
            $this->properties['show_comments']		= $row['show_comments'];
            $result = true;
        } elseif ($row === false) {
            $this->error = _t('USER_LOAD_BY_EMAIL_QUERY_FAILED').'.';
            $result = false;
        } else {
            $this->error = _t('USER_NO_USER_WITH_THAT_EMAIL').'.';
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
        $fieldsTab = array_map('trim', explode(',', $fieldsToUpdate));
        if ((count($fieldsTab) == 0) || ((count($fieldsTab) == 1) && ($fieldsTab[0] == ''))) { // Obviously empty => Then we update all but name and pwd
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
                case 'email':
                    if ($prefixe) {
                        $setClause .= ',';
                    }
                    $setClause .= ' email = "'.$this->properties['email'].'"';
                    $prefixe = true;
                    break;
                case 'motto':
                    if ($prefixe) {
                        $setClause .= ',';
                    }
                    $setClause .= ' motto = "'.$this->properties['motto'].'"';
                    $prefixe = true;
                    break;
                case 'revisioncount':
                    if ($prefixe) {
                        $setClause .= ',';
                    }
                    $setClause .= ' revisioncount = "'.$this->properties['revisioncount'].'"';
                    $prefixe = true;
                    break;
                case 'changescount':
                    if ($prefixe) {
                        $setClause .= ',';
                    }
                    $setClause .= ' changescount = "'.$this->properties['changescount'].'"';
                    $prefixe = true;
                    break;
                case 'doubleclickedit':
                    if ($prefixe) {
                        $setClause .= ',';
                    }
                    $setClause .= ' doubleclickedit = "'.$this->properties['doubleclickedit'].'"';
                    $prefixe = true;
                    break;
                case 'show_comments':
                    if ($prefixe) {
                        $setClause .= ',';
                    }
                    $setClause .= ' show_comments = "'.$this->properties['show_comments'].'"';
                    $prefixe = true;
                    break;
            } //End switch
        } // End foreach
        if ($prefixe) { // At least one field to update
            $sql = 'UPDATE '.$this->usersTable.' SET '.$setClause;
            $sql .= ' WHERE name = "'.mysqli_real_escape_string($this->wiki->dblink, $this->properties['name']).'" LIMIT 1;';
            $result = $this->wiki->query($sql);
            if ($result) {
                $error = '';
            } else {
                $this->error = _t('USER_UPDATE_QUERY_FAILED').'.';
                $result = false;
            }
        } else {
            $this->error = _t('USER_UPDATE_MISSPELLED_PROPERTIES').'.';
            $result = false;
        }
        return $result;
    }

    /*	NOTE: Doesn't make any sense in a user class (singular)
        code comes from tools/login/libs/login.class.inc.php
        and should stay there for the time being
    public function loadUsers()
    {
         return $this->wiki->loadAll("select * from " . $this->usersTable . " order by name");
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
            $this->error = _t('USER_CANT_DELETE_ONESELF').'.';
            $OK = false;
        }

        if ($OK) {
            $grouptab = $this->listGroupMemberships(); // All the groups with this user in
            foreach ($grouptab as $group) {
                $groupmembers = $this->wiki->GetGroupACL($group);
                $groupmembers = explode('\n', $groupmembers);
                $groupmembers = array_map('trim', $groupmembers);
                if (count($groupmembers) == 1) { // Only one user in (this user then)
                    $this->error = _t('USER_DELETE_LONE_MEMBER_OF_GROUP').'.';
                    //				$this->error .= 'La suppression de cet utilisateur est impossible car c\'est l\'unique membre du groupe @'.$group.'. Faîtes en sorte que ce ne soit plus le cas avant de tenter à nouveau de le supprimer.';
                    $OK = false;
                }
            }
            if ($OK) {
                // Delete user in every group
                $triplesTable = $this->wiki->config['table_prefix'].'triples';
                $searched_value = '%' . $this->properties['name'] . '%';
                $seek_value_bf = '' . $this->properties['name'] . '\n'; // username to delete can be followed by another username
                $seek_value_af = '\n' . $this->properties['name']; // username to delete can follow another username
                // get rid of this username everytime it's followed by another
                $sql  = 'UPDATE '.$triplesTable.'';
                $sql .= ' SET value = REPLACE(value, "'.$seek_value_bf.'", "")';
                $sql .= ' WHERE resource LIKE "'.GROUP_PREFIX.'%" and value LIKE "'.$searched_value.'";';
                $OK = $this->wiki->query($sql);
                if (!$OK) {
                    $this->error = _t('USER_DELETE_QUERY_FAILED').'.';
                }
                // in the remaining get rid of this username everytime it follows another
                if ($OK) {
                    $sql  = 'UPDATE `'.$triplesTable.'`';
                    $sql .= ' SET `value` = REPLACE(`value`, "'.$seek_value_af.'", "")';
                    $sql .= ' WHERE `resource` LIKE "'.GROUP_PREFIX.'%" and `value` LIKE "'.$searched_value.'";';
                    $OK = $this->wiki->query($sql);
                    if (!$OK) {
                        $this->error = _t('USER_DELETE_QUERY_FAILED').'.';
                    }
                }
                // For each page belonging to the user, set the ownership to null
                if ($OK) {
                    $pagesTable =$this->wiki->config['table_prefix'].'pages';
                    $sql = 'UPDATE `'.$pagesTable.'`';
                    // $sql .= ' SET `owner` = NULL';
                    $sql .= ' SET `owner` = "" ';
                    $sql .= ' WHERE `owner` = "'.$this->properties['name'].'";';
                    $OK = $this->wiki->query($sql);
                    if (!$OK) {
                        $this->error = _t('USER_DELETE_QUERY_FAILED').'.';
                    }
                }
                // Delete the user row from the user table
                if ($OK) {
                    $sql = 'DELETE FROM `'.$this->usersTable.'`';
                    $sql .= ' WHERE `name` = "'.$this->properties['name'].'";';
                    $OK = $this->wiki->query($sql);
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
    public function isRunner()
    {
        if (!empty($_SESSION['user']) && isset($this->properties['name']) && ($_SESSION['user']['name'] == $this->properties['name'])) {
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
        $results = $this->wiki->loadAll($sql);
        return $results; // If the password does not already exist in DB, $result is an empty table => false
    }

    public function isInGroup($groupName)
    {
        //	public function UserIsInGroup($group, $user = null, $admincheck = true)
        return $this->wiki->CheckACL($this->wiki->GetGroupACL($groupName), $this->properties['name'], false);
    }

    // returns an array of group names
    public function listGroupMemberships()
    {
        /* Build sql query*/
        $triplesTable = $this->wiki->config['table_prefix'].'triples';
        $sql  = 'SELECT resource FROM '.$triplesTable;
        $sql .= ' WHERE resource LIKE "'.GROUP_PREFIX.'%"';
        $sql .= ' AND property LIKE "'.WIKINI_VOC_ACLS_URI.'"';
        $sql .= ' AND value LIKE "%'.$this->properties['name'].'%";';
        /* Execute query */
        $results = array();
        if ($groups = $this->wiki->loadAll($sql)) {
            foreach ($variable as $key => $groupName) {
                $results[] = ltrim($groupName, "@ \t\n\r\0\xOB");
            }
            return $results;
        } else {
            $error = _t('USER_LISTGROUPMEMBERSHIPS_QUERY_FAILED').'.';
            return $error;
        }
    }
} //end User class
