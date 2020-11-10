<?php
namespace YesWiki;

class Session
{
    // Properties
    protected $cookiePath = '';
    protected $missingArg = ''; // If empty False, otherwise True and contains the name of the class missing attributes
    public $error = '';

    /* Constructor
        Requires parameter:
            wiki->cookiePath
        Exemple :
            $mySession = new WikiSession($this->wiki->cookiePath)
        Sets :
            $cookiePath
    */
    public function __construct($cookiePath = null)
    {
        if ($cookiePath == null) {
            $this->missingArg = 'cookiePath';
        } else {
            $this->cookiePath = $cookiePath;
        }
    }

    public function setCookiePath($cookiePath)
    {
        $this->cookiePath = $cookiePath;
        $this->missingArg = '';
    }

    /* Returns true if successful
        Returns false otherwise.
        In case of failure $this->error contains the error message
    */
    public function setSessionCookie($name, $value)
    {
        $this->error = '';
        if ($this->missingArg) {
            $this->error = _t('SESSION_YOU_MUST_FIRST_SET_ARGUMENT').' '.$this->missingArg.'.';
            $result = false;
        } else {
            SetCookie(
                $name,
                $value,
                0,									// Expires at the end of the session
                $this->cookiePath,
                '', 								// Domain
                !empty($_SERVER['HTTPS']),	// the cookie will only be set if a secure connection exists (https)
                true
            );							// cookie won't be accessible by scripting languages
            $_COOKIE[$name] = $value;
            $result = true;
        }
        return $result;
    }

    /* Returns true if successful
        Returns false otherwise.
        In case of failure $this->error contains the error message
    */
    public function setPersistentCookie($name, $value, $remember = 0)
    {
        $this->error = '';
        if ($this->missingArg) {
            $this->error = _t('SESSION_YOU_MUST_FIRST_SET_ARGUMENT').' '.$this->missingArg.'.';
            $result = false;
        } else {
            SetCookie(
                $name,
                $value,
                time() + ($remember ? 90 * 24 * 60 * 60 : 60 * 60), // If $remember is set and different from 0, 90 days, 1 hour otherwise
                $this->cookiePath,
                '', 								// Domain
                !empty($_SERVER['HTTPS']),	// the cookie will only be set if a secure connection exists (https)
                true
            );							// cookie won't be accessible by scripting languages
            $_COOKIE[$name] = $value;
            $result = true;
        }
        return $result;
    }

    /* Returns true if successful
        Returns false otherwise.
        In case of failure $this->error contains the error message
    */
    public function deleteCookie($name)
    {
        $this->error = '';
        if ($this->missingArg) {
            $this->error = _t('SESSION_YOU_MUST_FIRST_SET_ARGUMENT').' '.$this->missingArg.'.';
            $result = false;
        } else {
            SetCookie(
                $name,
                '',
                1,									// Expires 1 seconde later => ie forced to deletion
                $this->cookiePath,
                '', 								// Domain
                !empty($_SERVER['HTTPS']),	// the cookie will only be set if a secure connection exists (https)
                true
            );							// cookie won't be accessible by scripting languages
            $_COOKIE[$name] = '';
            $result = true;
        }
        return $result;
    }

    public function getCookie($name)
    {
        return $_COOKIE[$name];
    }

    // HTTP/REQUEST/LINK RELATED
    public function setMessage($message)
    {
        $_SESSION['message'] = $message;
    }

    public function getMessage()
    {
        if (isset($_SESSION['message'])) {
            $message = $_SESSION['message'];
        } else {
            $message = '';
        }

        $_SESSION['message'] = '';
        return $message;
    }
} //end Session class
