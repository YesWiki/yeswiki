<?php

spl_autoload_register(function ($className) {
    // Autoload services
    if (preg_match('/^YesWiki\\\\([^\\\\]+)(?:\\\\([^\\\\]+))?(?:\\\\([^\\\\]+))?$/', $className, $matches)) {
        if (empty($matches[2])) {
            // not currently managed
        } elseif (empty($matches[3])) {
            switch ($matches[1]) {
                case 'Core':
                    if (file_exists('includes/' . $matches[2] . '.php')) {
                        require 'includes/' . $matches[2] . '.php';
                    }
                    break;
                default:
                    // actions or handlers, directly managed by Performer
                    break;
            }
        } else {
            // basePath
            switch ($matches[1]) {
                case 'Core':
                    $basePath = 'includes';
                    break;
                case 'Custom':
                    $basePath = 'custom';
                    break;
                default:
                    $extension = strtolower($matches[1]);
                    $basePath = "tools/$extension";
                    break;
            }
            // Autoload services
            switch ($matches[2]) {
                case 'Service':
                    require "$basePath/services/{$matches[3]}.php";
                    break;
                case 'Controller':
                    require "$basePath/controllers/{$matches[3]}.php";
                    break;
                case 'Field':
                    if ($matches[1] != 'Core') {
                        require "$basePath/fields/{$matches[3]}.php";
                    }
                    break;
                case 'Commands':
                    require "$basePath/commands/{$matches[3]}.php";
                    break;
                case 'Entity':
                    require "$basePath/entities/{$matches[3]}.php";
                    break;
                case 'Exception':
                    require "$basePath/exceptions/{$matches[3]}.php";
                    break;
                case 'Trait':
                    require "$basePath/traits/{$matches[3]}.php";
                    break;
                default:
                    // do nothing
                    break;
            }
        }
    }
});
