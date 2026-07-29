<?php

namespace YesWiki\Admin\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/**
 * The /api discovery page. The monolithic ApiController hand-maintained an HTML
 * catalog here that drifted from reality (it still documented an endpoint that
 * never existed); this version enumerates the live RouteCollection instead, so
 * it cannot go stale (ticket 08).
 */
class DocumentationApiController extends YesWikiController
{
    #[Route('/api', options: ['acl' => ['public']])]
    public function getDocumentation()
    {
        $urlFormatter = $this->getService(UrlFormatter::class);
        $baseUrl = $urlFormatter->href('', '');

        // group /api/* routes by their first path segment after /api
        $groups = [];
        foreach ($this->wiki->getRoutes() as $route) {
            $path = $route->getPath();
            if ($path !== '/api' && !str_starts_with($path, '/api/')) {
                continue;
            }
            $segment = explode('/', trim(substr($path, 4), '/'))[0] ?: '(root)';
            $methods = $route->getMethods() ?: ['GET'];
            $acl = (array)($route->getOption('acl') ?? ['public']);
            $groups[$segment][] = [
                'path' => $path,
                'methods' => implode('|', $methods),
                'acl' => implode(', ', $acl),
            ];
        }
        ksort($groups);

        $output = '<h1>YesWiki API</h1>';
        $output .= '<p>' . _t('ONLY_FOR_ADMINS') . ' : <code>@admins</code></p>';
        foreach ($groups as $segment => $routes) {
            usort($routes, fn ($a, $b) => [$a['path'], $a['methods']] <=> [$b['path'], $b['methods']]);
            $output .= '<h2><code>' . htmlspecialchars($segment) . '</code></h2>' . "\n";
            foreach ($routes as $route) {
                $output .= '<p><code>' . htmlspecialchars($route['methods']) . ' '
                    . htmlspecialchars($baseUrl . ltrim($route['path'], '/')) . '</code>'
                    . ' <small>(acl: ' . htmlspecialchars($route['acl']) . ')</small></p>' . "\n";
            }
        }

        // extensions may still ship their own documentation hook
        foreach ($this->wiki->extensions as $extension => $pluginBase) {
            $response = null;
            if (file_exists($pluginBase . 'controllers/ApiController.php')) {
                $apiClassName = 'YesWiki\\' . ucfirst($extension) . '\\Controller\\ApiController';
                if (!class_exists($apiClassName, false)) {
                    include $pluginBase . 'controllers/ApiController.php';
                }
                if (class_exists($apiClassName, false)) {
                    $apiController = new $apiClassName();
                    $apiController->setWiki($this->wiki);
                    if (method_exists($apiController, 'getDocumentation')) {
                        $response = $apiController->getDocumentation();
                    }
                }
            }
            if (empty($response)) {
                $func = 'documentation' . ucfirst(strtolower($extension));
                if (function_exists($func)) {
                    $output .= $func();
                }
            } else {
                $output .= $response;
            }
        }

        $output = $this->getService(TemplateEngine::class)->header() . '<div class="api-container">' . $output . '</div>' . $this->getService(TemplateEngine::class)->footer();

        return new Response($output);
    }
}
