<?php

namespace YesWiki\Admin\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Route as SymfonyRoute;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Service\RouteProvider;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\TemplateEngine;

/** The /api discovery page, and the route table behind it. */
class DocumentationApiController extends YesWikiController
{
    use DashboardShell;

    #[Route('/api', options: ['acl' => ['public']])]
    public function getDocumentation(): Response
    {
        $baseUrl = (string)$this->getService(RuntimeConfig::class)['base_url'];

        $groups = [];
        $order = 0;
        foreach ($this->getService(RouteProvider::class)->get() as $route) {
            $path = $route->getPath();
            ++$order;
            if ($path !== '/api' && !str_starts_with($path, '/api/')) {
                continue;
            }
            $segment = explode('/', trim(substr($path, 4), '/'))[0] ?: '(root)';
            $groups[$segment][] = [
                'path' => $path,
                'url' => $baseUrl . ltrim($path, '/'),
                'methods' => $route->getMethods() ?: ['GET'],
                'acl' => implode(', ', (array)($route->getOption('acl') ?? [])),
                'params' => $this->parametersOf($route),
                'order' => $order,
            ];
        }
        ksort($groups);
        foreach ($groups as &$routes) {
            usort($routes, fn ($a, $b) => [$a['path'], $a['methods']] <=> [$b['path'], $b['methods']]);
        }
        unset($routes);

        if ($this->getRequest()->query->get('output') === 'json') {
            return new ApiResponse(['routes' => array_merge([], ...array_values($groups))]);
        }

        $output = '';

        foreach ($this->services->get(\YesWiki\Kernel\Service\ExtensionRegistry::class)->all() as $extension => $pluginBase) {
            $response = null;
            if ($this->getService(LocalFiles::class)->exists($pluginBase . 'controllers/ApiController.php')) {
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

        $templateEngine = $this->getService(TemplateEngine::class);

        return new Response($templateEngine->renderPage($templateEngine->render('@core/dashboard/api.twig', $this->dashboardShell('api', [
            'groups' => $groups,
            'extensionDocs' => $output,
        ]))));
    }

    /**
     * The placeholders a route's path carries, with what the route says about each.
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
