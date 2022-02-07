<?php

/*
section.php

2017 Florian Schmitt

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

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
if ($pattern && !$patternborder) $pattern .= <<<css
    background-color: $patternbg !important;
    background-repeat: repeat;
css;

// image's filename
$file = $this->GetParameter('file');
$backgroundimg = true;
if (empty($file) && empty($bgcolor)) {
    $bgcolor = false;
    $backgroundimg = false;
}

if (!empty($file)) {
    // test of image extension
    $supported_image_extensions = array('svg', 'gif', 'jpg', 'jpeg', 'png');
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    // Using strtolower to overcome case sensitive
    if (!in_array($ext, $supported_image_extensions)) {
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

    if (!class_exists('attach')) {
        include('tools/attach/libs/attach.lib.php');
    }
    $att = new attach($this);

    //recuperation des parametres necessaires
    $att->file = $file;
    $att->desc = 'background image ' . $file;
    $att->height = $height;
    $att->width = $width;
    $fullFilename = $att->GetFullFilename();

    // test d'existance du fichier
    if (isset($fullFilename) and (!file_exists($fullFilename) or $fullFilename == '')) {
        $att->showFileNotExits();
    }
}

$pagetag = $this->GetPageTag();
if (!isset($GLOBALS['check_' . $pagetag]['section'])) {
    $GLOBALS['check_' . $pagetag]['section'] = check_graphical_elements('section', $pagetag, $this->page['body']);
}

if ($GLOBALS['check_' . $pagetag]['section']) {

    // specify the role to be checked ( *, +, %, @admins)
    $role = $this->GetParameter('visibility');
    $role = empty($role) ? $role : str_replace("\\n", "\n", $role);
    $visible = !$role || ($GLOBALS['wiki']->CheckACL($role, null, false));
    
    $class = ($backgroundimg ? 'background-image' : '') 
     . ($patternId && !$patternborder ? ' with-bg-pattern' : '') 
     . ($patternborder ? ' pattern-border' : '') 
     . ($visible ? '' : ' remove-this-div-on-page-load ') 
     . " pattern-$patternId "
     . " {$this->GetParameter('class')}";

    $style = (!empty($bgcolor) ? 'background-color:' . $bgcolor .'; ' : '')
     . (!empty($height) ? 'height:' . $height . 'px; ' : '')
     . (!empty($pattern) ? $pattern : '')
     . (isset($fullFilename) ? 'background-image:url(' . $fullFilename . ');' : '').'"';
    
    // HTML attributes
    $attributes = [ 
        'class' => $class, 
        'style' => $style
    ];
    if (!empty($this->GetParameter('id'))) {
        $attributes['id'] = $this->GetParameter('id');
    }
    if (!empty($file)) {
        $attributes['data-file'] = $file;
    }
    $data = getDataParameter();
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $attributes["data-$key"] = $value;
        }
    }
    $attributesString = "";
    foreach($attributes as $attr => $value) {
        $attributesString .= "$attr='$value'";
    }
    $containterClass = empty($this->GetParameter('nocontainer')) ? 'container' : '';
    
    echo <<<HTML
        <!-- start of section -->
        <section $attributesString>
            <div class="$containterClass">
    HTML;
} else {
    echo <<<HTML
        <div class="alert alert-danger">
            <strong>{$this->t('TEMPLATE_ACTION_SECTION')}</strong>: {$this->t('TEMPLATE_ELEM_SECTION_NOT_CLOSED')}
        </div>
    HTML;
}
