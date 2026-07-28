<?php

/**
 * Legacy class loader for `YesWiki\Core\*`, `YesWiki\Custom\*` and extension namespaces.
 *
 * This is registered BEFORE Composer's autoloader (see YesWikiLoader), so it runs first for
 * every class in the YesWiki\ namespace. That matters for wave-two ticket 05: as modules
 * migrate to Composer PSR-4 one at a time, this loader must *decline* the namespaces PSR-4
 * now owns rather than guessing a path for them. It used to `require` unconditionally in the
 * three-segment branch, so an unknown top-level namespace (`YesWiki\Search\Service\X`) was
 * resolved to `extensions/search/services/X.php` and required a file that does not exist --
 * a fatal error rather than a graceful miss.
 *
 * Every lookup is now file_exists-guarded, so any class this loader cannot place falls
 * through to Composer. Once every module is PSR-4 (end of ticket 05) all that should remain
 * here is the extensions lookup, which is genuinely dynamic: `custom/extensions/{ext}`
 * shadows `extensions/{ext}` per instance, and static PSR-4 cannot express that.
 */
spl_autoload_register(function ($className) {
    // code lives in the (possibly shared) source tree; custom/ belongs to the instance (cwd)
    $sourceDir = defined('YESWIKI_SOURCE_DIR') ? YESWIKI_SOURCE_DIR : \dirname(__DIR__);

    if (!preg_match('/^YesWiki\\\\([^\\\\]+)(?:\\\\([^\\\\]+))?(?:\\\\([^\\\\]+))?$/', $className, $matches)) {
        return;
    }

    if (empty($matches[2])) {
        // not currently managed
        return;
    }

    if (empty($matches[3])) {
        if ($matches[1] === 'Core' && file_exists($sourceDir . '/src/' . $matches[2] . '.php')) {
            require_once $sourceDir . '/src/' . $matches[2] . '.php';
        }
        // anything else: actions/handlers (managed by Performer) or a PSR-4 namespace

        return;
    }

    switch ($matches[1]) {
        case 'Core':
            $basePath = $sourceDir . '/src';
            break;
        case 'Custom':
            $basePath = 'custom';
            break;
        default:
            $extension = strtolower($matches[1]);

            // instance-local custom/extensions/{ext} shadows the shared
            // extensions/{ext} (ticket 25, same precedence as the old
            // custom/tools/ vs tools/ lookup)
            if (is_dir("custom/extensions/{$extension}")) {
                $basePath = "custom/extensions/{$extension}";
            } elseif (is_dir($sourceDir . "/extensions/{$extension}")) {
                $basePath = $sourceDir . "/extensions/{$extension}";
            } else {
                // not an extension -- most likely a PSR-4 module namespace; let Composer answer
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
