<?php

namespace YesWiki\Templates\Controller;

use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Service\ThemeManager;
use YesWiki\Core\YesWikiController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api/templates/custom-presets/{presetFilename}", methods={"DELETE"},options={"acl":{"public","@admins"}})
     */
    public function deleteCustomCSSPreset($presetFilename)
    {
        $result = $this->getService(ThemeManager::class)->deleteCustomCSSPreset($presetFilename);
        $code = ($result['status'])
            ? 200 // 'OK'
            : 400; // 'not OK

        return new ApiResponse(['code' => $code, 'message' => $result['message']], $code);
    }

    /**
     * @Route("/api/templates/custom-presets/{presetFilename}", methods={"POST"},options={"acl":{"public","+"}})
     */
    public function addCustomCSSPreset($presetFilename)
    {
        $result = $this->getService(ThemeManager::class)->addCustomCSSPreset($presetFilename, $_POST);
        $code = ($result['status'])
            ? 200 // 'OK'
            : (
                (in_array($result['errorCode'], [3, 4]))
                    ? 500 // server error
                    : 400 // bad request error
            ); // 'not OK

        return new ApiResponse(['code' => $code, 'message' => $result['message'], 'errorCode' => $result['errorCode']], $code);
    }

    /**
     * Display Auth api documentation.
     *
     * @return string
     */
    public function getDocumentation()
    {
        $output = '<h2>Extension Templates</h2>' . "\n";

        $output .= '
        <p>
        <b><code>POST ' . $this->wiki->href('', 'api/templates/custom-presets/{presetFilename}') . '</code></b><br />
        ' . _t('TEMPLATE_ADD_CSS_PRESET_API_HINT') . '.
        </p>';

        $output .= '
        <p>
        <b><code>DELETE ' . $this->wiki->href('', 'api/templates/custom-presets/{presetFilename}') . '</code></b><br />
        ' . _t('TEMPLATE_DELETE_CSS_PRESET_API_HINT') . '.
        </p>';

        return $output;
    }
}
