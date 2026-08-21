<?php

namespace YesWiki\Admin\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Route as SymfonyRoute;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\RouteProvider;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\TemplateEngine;

/**
 * The /api discovery page. The monolithic ApiController hand-maintained an HTML
 * catalog here that drifted from reality (it still documented an endpoint that
 * never existed); this version enumerates the live RouteCollection instead, so
 * it cannot go stale (ticket 08).
 */
class DocumentationApiController extends YesWikiController
{
    use DashboardShell;

    #[Route('/api', options: ['acl' => ['public']])]
    public function getDocumentation(): Response
    {
        // the configured base URL, NOT href('', '') -- an empty tag there falls back to
        // the *current* page's tag, which on this very route is `api`, so every URL on
        // this page came out as `?apiapi/...`
        $baseUrl = (string)$this->getService(RuntimeConfig::class)['base_url'];

        // group /api/* routes by their first path segment after /api
        $groups = [];
        foreach ($this->getService(RouteProvider::class)->get() as $route) {
            $path = $route->getPath();
            if ($path !== '/api' && !str_starts_with($path, '/api/')) {
                continue;
            }
            $segment = explode('/', trim(substr($path, 4), '/'))[0] ?: '(root)';
            $groups[$segment][] = [
                'path' => $path,
                'url' => $baseUrl . ltrim($path, '/'),
                'methods' => $route->getMethods() ?: ['GET'],
                'acl' => implode(', ', (array)($route->getOption('acl') ?? ['public'])),
                'params' => $this->parametersOf($route),
            ];
        }
        ksort($groups);
        foreach ($groups as &$routes) {
            usort($routes, fn ($a, $b) => [$a['path'], $a['methods']] <=> [$b['path'], $b['methods']]);
        }
        unset($routes);

        $output = '';

        // extensions may still ship their own documentation hook
        foreach ($this->services->get(\YesWiki\Kernel\Service\ExtensionRegistry::class)->all() as $extension => $pluginBase) {
            $response = null;
            if (file_exists($pluginBase . 'controllers/ApiController.php')) {
                $apiClassName = 'YesWiki\\' . ucfirst($extension) . '\\Controller\\ApiController';
                if (!class_exists($apiClassName, false)) {
                    include $pluginBase . 'controllers/ApiController.php';
                }
                if (class_exists($apiClassName, false)) {
                    /** @var YesWikiController $apiController */
                    $apiController = new $apiClassName();
                    $apiController->setServices($this->services);
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

        // the same shell as /doc, /dashboard/* and /admin/*: this list is one of the
        // wiki's public routes, so it is reachable from their sidebar and shows it too
        $templateEngine = $this->getService(TemplateEngine::class);

        return new Response($templateEngine->renderPage($templateEngine->render('@core/dashboard/api.twig', $this->dashboardShell('api', [
            'groups' => $groups,
            'extensionDocs' => $output,
        ]))));
    }

    /**
     * The placeholders a route's path carries, with what the route says about each.
     *
     * A reader of this page has to know that `/api/forms/{formId}/entries/{output}` wants
     * two values and which ones may be left out, and the route itself is the only thing
     * that knows -- so it is read rather than restated.
     *
     * @return list<array{name: string, optional: bool, default: scalar|null, pattern: string|null}>
     */
    private function parametersOf(SymfonyRoute $route): array
    {
        preg_match_all('/\{!?(\w+)\}/', $route->getPath(), $matches);
        $defaults = $route->getDefaults();
        $requirements = $route->getRequirements();

        $params = [];
        foreach ($matches[1] as $name) {
            $default = $defaults[$name] ?? null;
            $params[] = [
                'name' => $name,
                'optional' => array_key_exists($name, $defaults),
                'default' => is_scalar($default) ? $default : null,
                'pattern' => isset($requirements[$name]) ? (string)$requirements[$name] : null,
            ];
        }

        return $params;
    }
}
