<?php

namespace YesWiki\Templates\Controller;

use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Core\Service\ThemeManager;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api/templates/custom-presets/{presetFilename}", methods={"DELETE"},options={"acl":{"public","@admins"}})
     */
    public function deleteCustomCSSPreset($presetFilename)
    {
        return new ApiResponse(['status'=>
            ($this->getService(ThemeManager::class)->deleteCustomCSSPreset($presetFilename))
                ? 1 // 'OK'
                : 0 // 'not OK
            ]);
    }

    /**
     * @Route("/api/templates/custom-presets/{presetFilename}", methods={"POST"},options={"acl":{"public","+"}})
     */
    public function addCustomCSSPreset($presetFilename)
    {
        return new ApiResponse(['status'=>
            ($this->getService(ThemeManager::class)->addCustomCSSPreset($presetFilename, $_POST))
                ? 1 // 'OK'
                : 0 // 'not OK
            ]);
    }

    /**
     * Display Auth api documentation
     *
     * @return string
     */
    public function getDocumentation()
    {
        $output = '<h2>Extension Templates</h2>' . "\n";

        $output .= '
        <p>
        <b><code>POST ' . $this->wiki->href('', 'api/templates/custom-presets/{presetFilename}') . '</code></b><br />
        '._t('TEMPLATE_ADD_CSS_PRESET_API_HINT').'.
        </p>';

        $output .= '
        <p>
        <b><code>DELETE ' . $this->wiki->href('', 'api/templates/custom-presets/{presetFilename}') . '</code></b><br />
        '._t('TEMPLATE_DELETE_CSS_PRESET_API_HINT').'.
        </p>';
        return $output;
    }
}
