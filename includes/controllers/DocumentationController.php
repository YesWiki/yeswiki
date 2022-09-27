<?php

namespace YesWiki\Core\Controller;

use YesWiki\Core\YesWikiController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DocumentationController extends YesWikiController
{
    /**
     * @Route("/doc",options={"acl":{"public"}})
     */
    public function show()
    {
        return new Response($this->render('@core/documentation.twig', [
          'config' => $this->wiki->config,
          'i18n' => $GLOBALS['translations_js'],
          'locale' => $GLOBALS['prefered_language'],
          'extensions' => $this->getExtensionsWithDocs()
        ]));
    }

    private function getExtensionsWithDocs(): array
    {
        $extensions = [];
        foreach ($this->wiki->extensions as $extName => $extPath) {
            $localizedPath = "{$extPath}docs/{$GLOBALS['prefered_language']}/README.md";
            $path = "{$extPath}docs/README.md";
            $docPath = glob($localizedPath)[0] ?? glob($path)[0] ?? null;
            if ($docPath) $extensions[] = ["name" => $extName, "docPath" => $docPath];
        }
        return $extensions;
    }
}
