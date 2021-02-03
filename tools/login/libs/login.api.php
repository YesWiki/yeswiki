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
