<?php
namespace AutoUpdate;

use YesWiki\Core\Service\ThemeManager;

$loader = require __DIR__ . '/../vendor/autoload.php';

// display update's message
// search data in session message
$endUpdate = false ;
if (isset($_SESSION['updateMessage'])) {
    $message = $_SESSION['updateMessage'];
    $_SESSION['updateMessage'] = '';
    if (!empty($message)) {
        // check integrity of data
        $data = json_decode($message, true);
        $endUpdate = is_array($data) && isset($data['messages']) && isset($data['baseURL'])
            && $this->UserIsAdmin();
    }
}

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if ($endUpdate) {
    
    // specific message when updating from cercopitheque
    if (isset($data['fromCercopitheque']) && $data['fromCercopitheque']) {
        $output = '<h1>'._t('AU_YESWIKI_DORYPHORE_POSTINSTALL').'</h1>'."\n";

        // check presence of theme margot
        $themeManager = $this->services->get(ThemeManager::class) ;
        if (!$themeManager->loadTheme()) {
            // upgrade margot
            $get = $_GET;
            $get['upgrade'] = THEME_PAR_DEFAUT;
            $autoUpdate = new AutoUpdate($this);
            $messages = new Messages();
            $controller = new Controller($autoUpdate, $messages, $this);
            $resUpgradeMargot = $controller->run($get);
            $resUpgradeMargot = (!empty($resUpgradeMargot)) ? '<hr><h3>'.THEME_PAR_DEFAUT.' update</h3>'."\n" .$resUpgradeMargot : null ;
        }
    } else {
        $output = '' ;
    }
    // finished rendering of autoupdate
    $output .= $this->render("@autoupdate/update.twig", [
        'messages' => $data['messages'],
        'baseUrl' => $data['baseURL'],
    ]);
    $output .= (!empty($resUpgradeMargot)) ? $resUpgradeMargot :'';
    echo $output ;
} else {
    $this->addJavascriptFile('tools/templates/libs/vendor/datatables/jquery.dataTables.min.js');
    $this->addJavascriptFile('tools/templates/libs/vendor/datatables/dataTables.bootstrap.min.js');
    $this->addCSSFile('tools/templates/libs/vendor/datatables/dataTables.bootstrap.min.css');

    // tries to give 5 minutes time for the script to execute
    @ini_set('max_execution_time', 300);
    @set_time_limit(300);

    $autoUpdate = new AutoUpdate($this);
    $messages = new Messages();
    $controller = new Controller($autoUpdate, $messages, $this);

    //$controller->run($_GET, $this->getParameter('filter'));
    // Can't see where filter is used => getting rid of it
    $requestedVersion = $this->getParameter('version');
    if (isset($requestedVersion) && $requestedVersion != '') {
        echo $controller->run($_GET, $requestedVersion);
    } else {
        echo $controller->run($_GET);
    }
}
