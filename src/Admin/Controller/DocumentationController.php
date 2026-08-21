<?php

namespace YesWiki\Admin\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\TemplateEngine;

/** `/doc` -- the documentation, rendered as a page of this wiki. */
class DocumentationController extends YesWikiController
{
    use DashboardShell;

    #[Route('/doc', options: ['acl' => ['public']])]
    public function show()
    {
        $templateEngine = $this->getService(TemplateEngine::class);

        return new Response($templateEngine->renderPage($templateEngine->render('@core/doc.twig', $this->dashboardShell('doc', [
            'config' => $this->getService(RuntimeConfig::class)->all(),
            'i18n' => $GLOBALS['translations_js'],
            'locale' => $this->getService(LanguageService::class)->preferredLanguage(),
            'extensions' => $this->getExtensionsWithDocs(),
        ]))));
    }

    private function getExtensionsWithDocs(): array
    {
        $extensions = [];
        foreach ($this->getService(\YesWiki\Kernel\Service\ExtensionRegistry::class)->all() as $extName => $extPath) {
            $localizedPath = "{$extPath}docs/{$this->getService(LanguageService::class)->preferredLanguage()}/README.md";
            $path = "{$extPath}docs/README.md";
            $docPath = glob($localizedPath)[0] ?? glob($path)[0] ?? null;
            if ($docPath) {
                $extensions[] = ['name' => $extName, 'docPath' => $docPath];
            }
        }

        return $extensions;
    }
}
