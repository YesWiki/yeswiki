<?php
// Get configuration
define("WAKKA_VERSION", "0.1.1");
define("WIKINI_VERSION", "0.5.0");
require_once ('tools/libs/class.plugins.php');
require_once ('tools/libs/lib.compat.php');
require_once ('tools/libs/lib.form.php');
require_once ('tools/libs/lib.files.php');
require_once ('tools/libs/lib.buffer.php');
require_once ('tools/libs/class.wiki.php');
include 'includes/i18n.inc.php';

function __($str) {
	return $str;
}

if (!defined('DC_ECRIRE')) {
        define('DC_ECRIRE','');
}

if (!defined('TOOLS_MANAGER')) {
        define('TOOLS_MANAGER','TOOLS_MANAGER');
}

require_once ('tools/libs/Auth.php');
require_once ('tools/libs/Auth/Container.php');

class CustomAuthContainer extends Auth_Container
{
    var $mysql_user;
    var $mysql_password;

    function CustomAuthContainer($mysql_user, $mysql_password )
    {
    	$this->mysql_user=$mysql_user;
	    $this->mysql_password=$mysql_password;
    }

    function fetchData($username, $password, $isChallengeResponse = false)
    {
        if (($username == $this->mysql_user ) && ( $password == $this->mysql_password)) {
            return true;
        }
        return false;
    }
}


$auth_container = new CustomAuthContainer($wakkaConfig['mysql_user'],$wakkaConfig['mysql_password']);

$params = array(
            "advancedsecurity" => "true"
);


$a = new Auth($auth_container,$params);

$a->start();

if (isset($_GET['tools_action']) && $_GET['tools_action'] == "logout" && $a->checkAuth()) {
    $a->logout();
    $a->start();
    exit;
}


if($a->checkAuth()) {

}
else {
	exit;
}

$plugins_root = 'tools/';

$plugins = new plugins($plugins_root);
$plugins->getPlugins(true);
$plugins_list = $plugins->getPluginsList();

$PLUGIN_HEAD = '';
$PLUGIN_BODY = '';

if ((!empty($_REQUEST['p']) && !empty($plugins_list[$_REQUEST['p']])
&& $plugins_list[$_REQUEST['p']]['active']))
{
	$p = $_REQUEST['p'];
	$buffer = new buffer();
	$buffer->init();
	include $plugins_root.$p.'/index.php';
	$PLUGIN_BODY = $buffer->getContent();
	$buffer->clean();

}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
   "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" lang="fr" xml:lang="fr">
<head>
  <title><?php echo _t('YESWIKI_TOOLS_CONFIG'); ?></title>
  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"/>
</head>

<body>


<?php

$tools_url= "http://".$_SERVER["SERVER_NAME"].($_SERVER["SERVER_PORT"] != 80 ? ":".$_SERVER["SERVER_PORT"] : "").dirname($_SERVER["REQUEST_URI"]).'/tools.php';

echo '<a href="'.$tools_url.'?tools_action=logout">'._t('DISCONNECT').'</a>';


if ($PLUGIN_HEAD != '')
{
	echo '<h1>';
	echo $PLUGIN_HEAD;
	echo '</h1>';
}

if ($PLUGIN_BODY != '')
{
	echo '<h1>';
	echo '<a href="'.$tools_url.'">'._t('RETURN_TO_EXTENSION_LIST').'</a>';
	echo '</h1>';
	echo $PLUGIN_BODY;
}
else
{
	if (count($plugins_list) == 0)
	{
		echo '<p>'._t('NO_TOOL_AVAILABLE').'</p>';
	}
	else
	{

		# Tri des plugins par leur nom
		uasort($plugins_list,create_function('$a,$b','return strcmp($a["label"],$b["label"]);'));

		# Liste des plugins
		echo '<h1>';
		echo '<a href="'.$tools_url.'">'._t('LIST_OF_ACTIVE_TOOLS').'</a>';
		echo '</h1>';

		echo '<dl class="plugin-list">';
		foreach ($plugins_list as $k => $v)
		{
			$plink = '<a href="tools.php?p='.$k.'">%s</a>';
			$plabel = (!empty($v['label'])) ? $v['label'] : $v['name'];

			echo '<dt>';
			if (file_exists($plugins_root.$k.'/icon.png')) {
				printf($plink,'<img alt="" src="tools/'.$k.'/icon.png" />');
				echo ' ';
			}

			printf($plink,$plabel);
			echo '</dt>';

			echo '<dd>'.$v['desc'].'</dd>';
		}
		echo '</dl>';
	}
}
?>
</body>
</html>
