<?php
namespace AutoUpdate;

use YesWiki\Core\Service\ThemeManager;

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

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

if ($endUpdate) {

    // update unknown release
    $params = $this->services->getParameterBag();
    $releaseInConfig = $params->get('yeswiki_release');
    if ($releaseInConfig == _t('AU_UNKNOW') || !preg_match("/^\d{1,4}[.-].*/", $releaseInConfig)) {
        $autoUpdate = new AutoUpdate($this);
        $configFromFile = $autoUpdate->getWikiConfiguration();
        $configFromFile['yeswiki_release'] = YESWIKI_RELEASE;
        $configFromFile->write();
    }
    
    // specific message when updating from cercopitheque
    if (isset($data['fromCercopitheque']) && $data['fromCercopitheque']) {
        unset($data['fromCercopitheque']);
        $fromCercopitheque = true;
        $newMessages = [];
        $newMessages[0]['text'] = _t('AU_YESWIKI_DORYPHORE_POSTINSTALL');
        $newMessages[0]['status'] = _t('AU_OK');
        foreach ($data['messages'] as $message) {
            $newMessages[] = $message;
        }
        $data['messages'] = $newMessages;

        // check presence of theme margot
        $themeManager = $this->services->get(ThemeManager::class) ;
      
        // check favorite_theme in wakka.config.php
        $autoUpdate = new AutoUpdate($this);
        $configFromFile = $autoUpdate->getWikiConfiguration();
        $favoriteThemefromFile = $configFromFile['favorite_theme'] ?? '';
        
        if (empty($favoriteThemefromFile) || $favoriteThemefromFile == 'yeswiki') {
            // upgrade yeswikicerco theme
            $get['upgrade'] = 'yeswikicerco';
            $messages = new Messages();
            $controller = new Controller($autoUpdate, $messages, $this);
            $upgradeThemeMessage = $controller->run($get);
            
            if (!empty($upgradeThemeMessage)) {
                if (empty($configFromFile['favorite_theme'])) {
                    $configFromFile['favorite_theme'] = 'yeswikicerco';
                    $configFromFile['favorite_style'] = 'gray.css';
                    $configFromFile['favorite_squelette'] = 'responsive-1col.tpl.html';
                } else {
                    $configFromFile['favorite_theme'] = 'yeswikicerco';
                    $configFromFile['favorite_style'] = $configFromFile['favorite_style'] ?? 'gray.css';
                    $configFromFile['favorite_squelette'] = $configFromFile['favorite_squelette'] ?? 'responsive-1col.tpl.html';
                }
                $configFromFile->write();
                // reload wiki with rights values

                // title
                $data['messages'][] = [
                    'status'=>_t('AU_OK'),
                    'text'=> '=== yeswikicerco theme update ==='
                ];
                // extract messages
                $matches = [];
                if (preg_match_all(
                    '/<li class="list-group-item">\s*<span class="pull-right label [^"]*">([^<]*)<\/span>\s([^<]*)/',
                    $upgradeThemeMessage,
                    $matches
                )) {
                    foreach ($matches[0] as $index => $match) {
                        $data['messages'][] = [
                            'status'=>$matches[1][$index],
                            'text'=> str_replace("&#039;", "'", $matches[2][$index])
                        ];
                    }
                }
                $_SESSION['updateMessage'] = json_encode($data);
                $newAdress = $data['baseURL'];
                header("Location: ".$newAdress);
                exit();
            }
        }
    }
    // check presence of specific files
    $filesToCheck = ['templates/edit-config.twig']; // only present if templates folder is updated
    $filesToCheck = $filesToCheck + PackageCore::FILES_TO_ADD_TO_IGNORED_FOLDERS;
    $notExistingFiles = array_filter($filesToCheck, function ($file) {
        return !file_exists($file);
    });
    if (!empty($notExistingFiles) && !($_SESSION['updateAlreadyForced'] ?? false)) {
        // check if previous update is ok
        $okText = _t('AU_OK');
        $allok = empty(array_filter($data['messages'], function ($message) use ($okText) {
            return !isset($message['status']) || ($message['status'] !== $okText);
        }));
        if ($allok) {
            $_SESSION['updateAlreadyForced'] = true;
            // redo update
            $autoUpdate = new AutoUpdate($this);
            $get['upgrade'] = 'yeswiki';
            $messages = new Messages();
            // udate message with previous one
            foreach ($data['messages'] as $message) {
                $messages->add($message['text'], $message['status']);
            }
            // add message for second update
            $messages->add('=== '._t('AU_YESWIKI_SECOND_TIME_UPDATE').' ===', _t('AU_OK'));
            $controller = new Controller($autoUpdate, $messages, $this);
            $controller->run($get);
        } else {
            $data['messages'][] = [
                'status'=>_t('AU_ERROR'),
                'text'=> _t('AU_SECOND_UPDATE_NOT_POSSIBLE')
            ];
        }
    } else {
        unset($_SESSION['updateAlreadyForced']);
        foreach ($notExistingFiles as $file) {
            $data['messages'][] = [
                'status'=>_t('AU_ERROR'),
                'text'=> str_replace('{{file}}', $file, _t('AU_FILE_NOT_POSSIBLE_TO_UPDATE')),
            ];
        }
    }

    if ($data['messages'][0]['text'] == _t('AU_YESWIKI_DORYPHORE_POSTINSTALL')) {
        $fromCercopitheque = true;
        $newMessages = [];
        for ($i=1; $i < count($data['messages']); $i++) {
            $newMessages[] = $data['messages'][$i];
        }
        $data['messages'] = $newMessages;
    }
    $output = ($fromCercopitheque ?? false)
        ? '<h1>'._t('AU_YESWIKI_DORYPHORE_POSTINSTALL').'</h1>'."\n"
        : '';

    // finished rendering of autoupdate
    $output .= $this->render("@autoupdate/update.twig", [
        'messages' => $data['messages'],
        'baseUrl' => $data['baseURL'],
    ]);
    echo $output ;
} else {
    $this->addJavascriptFile('javascripts/vendor/datatables-full/jquery.dataTables.min.js');
    $this->addCSSFile('styles/vendor/datatables-full/dataTables.bootstrap.min.css');

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
