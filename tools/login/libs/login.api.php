<?php
/**
 * Function library for login
 *
 * @category Wiki
 * @package  YesWiki
 * @author   Florian Schmitt <mrflos@lilo.org>
 * @license  GNU/GPL version 3
 * @link     https://yeswiki.net
 */

/**
 * Get all users or one user's information
 *
 * @param string $username specify username 
 * 
 * @return string json
 */
function getAuth($username = '')
{
    global $wiki;
    if (!empty($username[0])) {
        if ($wiki->UserIsAdmin() or $wiki->GetUserName() == $username[0]) {
            $user = $wiki->LoadUser($username[0]);
            if ($user) {
                return json_encode($user);
            } else {
                return json_encode(
                    array('error' => array('User '.$username[0].' not found.'))
                );
            }
        } else {
            return json_encode(
                array('error' => array('Unauthorized'))
            );
        }
    } else {
        $users = $wiki->LoadUsers();
        return json_encode($users);
    }
}

/**
 * Display login api documentation
 *
 * @return void
 */
function documentationLogin()
{
    global $wiki;
    $urlAuth = $wiki->href('', 'api/auth');
    $output = '<h2>Extension login</h2>'."\n".
    'GET <code><a href="'.$urlAuth.'">'.$urlAuth.'</a></code><br />';
    return $output;
}
