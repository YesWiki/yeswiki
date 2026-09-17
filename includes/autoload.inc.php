<?php

spl_autoload_register(function ($className) {
    if (!preg_match('/^YesWiki\\\\([^\\\\]+)(?:\\\\([^\\\\]+))?(?:\\\\([^\\\\]+))?$/', $className, $matches)) {
        return;
    }

    if (empty($matches[2])) {
        return;
    }

    if (empty($matches[3])) {
        if ($matches[1] === 'Core' && file_exists('includes/' . $matches[2] . '.php')) {
            require_once 'includes/' . $matches[2] . '.php';
        }

        return;
    }

    switch ($matches[1]) {
        case 'Core':
            $basePath = 'includes';
            break;
        case 'Custom':
            $basePath = 'custom';
            break;
        default:
            $extension = strtolower($matches[1]);
            $basePath = is_dir("custom/tools/{$extension}")
                ? "custom/tools/{$extension}"
                : "tools/{$extension}";
            break;
    }

    switch ($matches[2]) {
        case 'Service':
            $folder = 'services';
            break;
        case 'Controller':
            $folder = 'controllers';
            break;
        case 'Field':
            $folder = $matches[1] === 'Core' ? null : 'fields';
            break;
        case 'Commands':
            $folder = 'commands';
            break;
        case 'Entity':
            $folder = 'entities';
            break;
        case 'Exception':
            $folder = 'exceptions';
            break;
        case 'Trait':
            $folder = 'traits';
            break;
        default:
            $folder = null;
            break;
    }

    if ($folder === null) {
        return;
    }

    $file = "{$basePath}/{$folder}/{$matches[3]}.php";
    if (file_exists($file)) {
        require_once $file;
    }
});
