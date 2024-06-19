<?php

namespace YesWiki\Attach\Controller;

use Attach;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\YesWikiController;

class ApiController extends YesWikiController
{
    public const POST_CACHE_URLIMAGE_TOKEN_ID = 'POST api/images/cache/{width}/{height}/{mode}';

    /**
     * @Route("/api/images/{filename}/cache/{width}/{height}/{mode}", methods={"POST"}, options={"acl":{"public"}})
     */
    public function getCacheUrlImageViaPost($filename, $width, $height, $mode)
    {
        try {
            $this->checkParamsgetCacheUrlImageViaPost($filename, $width, $height, $mode);
            $newToken = $this->checkTokenForgetCacheUrlImageViaPost($width, $height, $mode);
            // check file
            if (!file_exists("files/$filename")) {
                return new ApiResponse([
                    'error' => _t('ATTACH_GET_CACHE_URLIMAGE_NO_FILE'),
                    'filename' => $filename,
                    'width' => $width,
                    'height' => $height,
                    'mode' => $mode,
                    'newToken' => $newToken,
                ], Response::HTTP_BAD_REQUEST);
            }
            // process new file
            try {
                $cachefilename = $this->getCacheFileName($filename, $width, $height, $mode);
            } catch (Exception $e) {
                return new ApiResponse([
                    'error' => $e->getMessage(),
                    'cachefilename' => '',
                    'filename' => $filename,
                    'width' => $width,
                    'height' => $height,
                    'mode' => $mode,
                    'newToken' => $newToken,
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return new ApiResponse([
                'cachefilename' => $cachefilename,
                'filename' => $filename,
                'width' => $width,
                'height' => $height,
                'mode' => $mode,
                'newToken' => $newToken,
            ], Response::HTTP_OK);
        } catch (TokenNotFoundException $th) {
            $errorMessage = $th->getMessage();

            return new ApiResponse([
                'error' => $errorMessage,
            ], Response::HTTP_UNAUTHORIZED);
        } catch (Exception $e) {
            return new ApiResponse([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    private function checkParamsgetCacheUrlImageViaPost(string $filename, string &$width, string &$height, string $mode)
    {
        if (strval($width) != strval(intval($width))) {
            throw new Exception('width should be an integer for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        $width = intval($width);
        if (empty($width)) {
            throw new Exception('width should not be 0 or null for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        if (strval($height) != strval(intval($height))) {
            throw new Exception('height should be an integer for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        $height = intval($height);
        if (empty($height)) {
            throw new Exception('height should not be 0 or null for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        if (!in_array($mode, ['fit', 'crop'], true)) {
            throw new Exception("mode should be in ['fit','mode'] for " . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        if (empty(trim($filename))) {
            throw new Exception('filename should not be empty for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
    }

    /**
     * use $_POST['csrftoken'].
     *
     * @return string $newToken
     */
    private function checkTokenForgetCacheUrlImageViaPost(int $width, int $height, string $mode): string
    {
        $csrfTokenManager = $this->getService(CsrfTokenManager::class);
        $csrfTokenController = $this->getService(CsrfTokenController::class);

        $tokenId = str_replace(
            ['{width}', '{height}', '{mode}'],
            [$width, $height, $mode],
            self::POST_CACHE_URLIMAGE_TOKEN_ID
        );

        if ($csrfTokenController->checkToken($tokenId, 'POST', 'csrftoken', false)) {
            $csrfTokenManager->removeToken($tokenId);
            $newToken = $csrfTokenManager->getToken($tokenId)->getValue();

            return $newToken;
        }
    }

    private function getCacheFileName(string $filename, int $width, int $height, string $mode): string
    {
        if (!class_exists('attach')) {
            include 'tools/attach/libs/attach.lib.php';
        }
        $attach = new attach($this->wiki);
        $newFileName = $attach->getResizedFilename("files/$filename", $width, $height, $mode);
        if (file_exists($newFileName)) {
            return $newFileName;
        } else {
            $returnedFileName = $attach->redimensionner_image("files/$filename", $newFileName, $width, $height, $mode);
            if ($returnedFileName != $newFileName) {
                // TODO see what to do with error
            }

            return $newFileName;
        }
    }

    /**
     * Display Bazar api documentation.
     *
     * @return string
     */
    public function getDocumentation()
    {
        $output = '<h2>Attach</h2>' . "\n";

        $output .= '<p><b>' .
        "<code>GET {$this->wiki->href('', 'api/images/{filename}/cache/{width}/{height}/{mode}', ['csrftoken' => 'xxxx'], false)}</code></b><br />" .
        nl2br(_t('ATTACH_GET_URLIMAGE_CACHE_API_HELP')) . '</p>';

        return $output;
    }
}
