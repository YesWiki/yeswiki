<?php

/** Legacy class loader for `YesWiki\Core\*`, `YesWiki\Custom\*` and extension namespaces. */
spl_autoload_register(function ($className) {
    $sourceDir = defined('YESWIKI_SOURCE_DIR') ? YESWIKI_SOURCE_DIR : \dirname(__DIR__);

    if (!preg_match('/^YesWiki\\\\([^\\\\]+)(?:\\\\([^\\\\]+))?(?:\\\\([^\\\\]+))?$/', $className, $matches)) {
        return;
    }

    if (empty($matches[2])) {
        return;
    }

    if (empty($matches[3])) {
        if ($matches[1] === 'Core' && file_exists($sourceDir . '/src/' . $matches[2] . '.php')) {
            require_once $sourceDir . '/src/' . $matches[2] . '.php';
        }

        return;
    }

    switch ($matches[1]) {
        case 'Core':
            return;
        case 'Custom':
            $basePath = 'custom';
            break;
        default:
            $extension = strtolower($matches[1]);

            if (is_dir("custom/extensions/{$extension}")) {
                $basePath = "custom/extensions/{$extension}";
            } elseif (is_dir($sourceDir . "/extensions/{$extension}")) {
                $basePath = $sourceDir . "/extensions/{$extension}";
            } else {
                return;
            }
            break;
    }

    $directories = [
        'Service' => 'services',
        'Controller' => 'controllers',
        'Field' => 'fields',
        'Commands' => 'commands',
        'Entity' => 'entities',
        'Exception' => 'exceptions',
        'Trait' => 'traits',
    ];

    if (!isset($directories[$matches[2]])) {
        return;
    }

    $file = "{$basePath}/{$directories[$matches[2]]}/{$matches[3]}.php";
    if (file_exists($file)) {
        require_once $file;
    }
});
