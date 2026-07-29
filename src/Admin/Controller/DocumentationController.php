<?php

namespace YesWiki\Admin\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\RuntimeConfig;

class DocumentationController extends YesWikiController
{
    #[Route('/doc', options: ['acl' => ['public']])]
    public function show()
    {
        return new Response($this->render('@core/documentation.twig', [
            'config' => $this->getService(RuntimeConfig::class)->all(),
            'i18n' => $GLOBALS['translations_js'],
            'locale' => $GLOBALS['prefered_language'],
            'extensions' => $this->getExtensionsWithDocs(),
        ]));
    }

    private function getExtensionsWithDocs(): array
    {
        $extensions = [];
        foreach ($this->wiki->extensions as $extName => $extPath) {
            $localizedPath = "{$extPath}docs/{$GLOBALS['prefered_language']}/README.md";
            $path = "{$extPath}docs/README.md";
            $docPath = glob($localizedPath)[0] ?? glob($path)[0] ?? null;
            if ($docPath) {
                $extensions[] = ['name' => $extName, 'docPath' => $docPath];
            }
        }

        return $extensions;
    }
}
