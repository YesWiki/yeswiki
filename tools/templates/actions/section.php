<?php

// Get the action's parameters :

// image's background color
$bgcolor = $this->GetParameter('bgcolor');

// backgournd pattern
$patternreverse = $this->GetParameter('patternreverse') == 'true';
$patternId = $this->GetParameter('pattern');
$patternbg = $patternreverse ? 'var(--main-bg-color)' : $bgcolor;
$patterncolor = $patternreverse ? $bgcolor : 'var(--main-bg-color)';
$patternborder = false;
switch ($patternId) {
    case 'border-solid':
    case 'border-dashed':
    case 'border-dotted':
        $patternborder = true;
        $pattern = <<<css
            border-color: $bgcolor;
            background-color: var(--main-bg-color);
        css;
        break;
    case 'point':
        $pattern = <<<css
            background-image: radial-gradient($patterncolor 2.5px, transparent 2.5px);
            background-size: 31px 31px;
        css;
        break;
    case 'point2':
        $pattern = <<<css
            background-image: radial-gradient($patterncolor 2px, transparent 2px), radial-gradient($patterncolor 2px, transparent 2px);
            background-size: 25px 25px;
            background-position: 0 0, 12.5px 12.5px;
        css;
        break;
    case 'cross':
        $pattern = <<<css
            background: radial-gradient(circle, transparent 20%, $patternbg 30%, $patternbg 70%, transparent 70%, transparent) 0% 0% / 30px 30px, radial-gradient(circle, transparent 20%, $patternbg 40%, $patternbg 75%, transparent 70%, transparent) 30px 30px / 30px 30px, linear-gradient($patterncolor 2px, transparent 2px) 0px -1px / 30px 30px, linear-gradient(90deg, $patterncolor 2px, $patternbg 2px) -1px 0px / 30px 30px $patternbg;
            background-position-y: 7px;
        css;
        break;
    case 'cross2':
        $pattern = <<<css
            background: radial-gradient(circle, transparent 20%, $patternbg 20%, $patternbg 80%, transparent 80%, transparent) 0% 0% / 46px 46px, radial-gradient(circle, transparent 20%, $patternbg 20%, $patternbg 80%, transparent 80%, transparent) 23px 23px / 46px 46px, linear-gradient($patterncolor 2px, transparent 2px) 0px -1px / 23px 23px, linear-gradient(90deg, $patterncolor 2px, $patternbg 2px) -1px 0px / 23px 23px $patternbg;
        css;
        break;
    case 'zigzag':
        $pattern = <<<css
            background: linear-gradient(135deg, $patterncolor 25%, transparent 25%) -10px 0, linear-gradient(225deg, $patterncolor 25%, transparent 25%) -10px 0, linear-gradient(315deg, $patterncolor 25%, transparent 25%), linear-gradient(45deg, $patterncolor 25%, transparent 25%);
            background-size: 20px 20px;
        css;
        break;
    case 'diagonal':
        $pattern = <<<css
            background-image: repeating-linear-gradient(45deg, $patterncolor 0, $patterncolor 3.5px, transparent 0, transparent 50%);
            background-size: 18px 18px;
        css;
        break;
    default:
        $pattern = '';
        break;
}
if ($pattern && !$patternborder) {
    $pattern .= <<<css
    background-color: $patternbg !important;
    background-repeat: repeat;
css;
}

// image's filename
$file = $this->GetParameter('file');
$backgroundimg = true;
if (empty($file) && empty($bgcolor)) {
    $bgcolor = false;
    $backgroundimg = false;
}

if (!empty($file)) {
    if (!class_exists('attach')) {
        include 'tools/attach/libs/attach.lib.php';
    }
    $att = new attach($this);

    // test of image extension
    if (!$att->isPicture($file)) {
        echo '<div class="alert alert-danger"><strong>' . _t('ATTACH_ACTION_BACKGROUNDIMAGE') . '</strong> : '
              . _t('ATTACH_PARAM_FILE_MUST_BE_IMAGE') . '.</div>' . "\n";

        return;
    }
    // image size
    $height = $this->GetParameter('height');
    $width = $this->GetParameter('width');
    if (empty($width)) {
        $width = 1920;
    }

    //recuperation des parametres necessaires
    $att->file = $file;
    $att->desc = 'background image ' . $file;
    $att->height = $height;
    $att->width = $width;
    $fullFilename = $att->GetFullFilename();
}

// container class
$class = $this->GetParameter('class');

// container id
$id = $this->GetParameter('id');

// container data attributes
$data = $this->services->get(\YesWiki\Templates\Service\Utils::class)->getDataParameter();

$pagetag = $this->GetPageTag();
if (!isset($GLOBALS['check_' . $pagetag])) {
    $GLOBALS['check_' . $pagetag] = [];
}
if (!isset($GLOBALS['check_' . $pagetag]['section'])) {
    $GLOBALS['check_' . $pagetag]['section'] = $this->services->get(\YesWiki\Templates\Service\Utils::class)->checkGraphicalElements('section', $pagetag, $this->page['body'] ?? '');
}
if ($GLOBALS['check_' . $pagetag]['section']) {
    // specify the role to be checked ( *, +, %, @admins)
    $role = $this->GetParameter('visibility');
    $role = empty($role) ? $role : str_replace('\\n', "\n", $role);
    $visible = !$role || ($GLOBALS['wiki']->CheckACL($role, null, false));
    $class = ($backgroundimg ? 'background-image' : '')
     . ($patternId && !$patternborder ? ' with-bg-pattern' : '')
     . ($patternborder ? ' pattern-border' : '')
     . ($visible ? '' : ' remove-this-div-on-page-load ')
     . " pattern-$patternId"
     . (!empty($class) ? ' ' . $class : '');

    echo '<!-- start of section -->
    <section' . (!empty($id) ? ' id="' . $id . '"' : '') . ' class="' . $class . '" data-file="' . $file . '" style="'
        . (!empty($bgcolor) ? 'background-color:' . $bgcolor . '; ' : '')
        . (!empty($height) ? 'height:' . $height . 'px; ' : '')
        . (!empty($pattern) ? $pattern : '')
        . (isset($fullFilename) ? 'background-image:url(' . $fullFilename . ');' : '') . '"'
    ;
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            echo ' data-' . $key . '="' . $value . '"';
        }
    }
    echo '>' . "\n";

    $nocontainer = $this->GetParameter('nocontainer');
    if (empty($nocontainer)) {
        echo '<div class="container">' . "\n";
    } else {
        echo '<div>';
    }
    //test d'existance du fichier
    if (isset($fullFilename) and (!file_exists($fullFilename) or $fullFilename == '')) {
        $att->showFileNotExits();
        //return;
    }
} else {
    echo '<div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_SECTION') . '</strong> : '
        . _t('TEMPLATE_ELEM_SECTION_NOT_CLOSED') . '.</div>' . "\n";

    return;
}
