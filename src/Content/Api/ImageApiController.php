<?php

namespace YesWiki\Content\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Content\Attach;
use YesWiki\Content\Service\FileManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\CsrfTokenChecker;

class ImageApiController extends YesWikiController
{
    /**
     * Generate/serve a resized cached copy of an image (ticket 17, relocated from
     * tools/attach). $filename is a raw legacy filename (Bazar's own image/file fields
     * upload through the same {pageTag}_{name}_{dates}.{ext} convention tools/attach
     * always used, and were never migrated to a file-entry tag) -- this route used to
     * perform NO ownership check at all beyond `file_exists()`, the same vulnerability
     * class as the download route above. Fixed the same way: recover the owning page
     * tag from the filename's legacy prefix and deny access unless its read ACL grants
     * this requester read.
     */
    #[Route('/api/images/{filename}/cache/{width}/{height}/{mode}', methods: ['POST'], options: ['acl' => ['public']])]
    public function getCacheUrlImageViaPost($filename, $width, $height, $mode)
    {
        try {
            $this->checkParamsGetCacheUrlImageViaPost($filename, $width, $height, $mode);
            $newToken = $this->checkTokenForGetCacheUrlImageViaPost($width, $height, $mode);

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

            // fail closed: every legitimate caller's filename follows the legacy
            // {pageTag}_{name}_{dates}.{ext} convention, so if we can't recover an owner
            // page tag from it, there's no ACL we could possibly check -- deny rather
            // than silently serving the image unauthenticated
            $ownerPageTag = $this->getService(FileManager::class)->guessOwnerPageTagFromLegacyFilename($filename);
            if (empty($ownerPageTag)) {
                throw new AccessDeniedHttpException();
            }
            $this->denyAccessUnlessGranted('read', $ownerPageTag);

            try {
                $cachefilename = $this->getCacheFileName($filename, $width, $height, $mode);
            } catch (\Exception $e) {
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
            return new ApiResponse(['error' => $th->getMessage()], Response::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return new ApiResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function checkParamsGetCacheUrlImageViaPost(string $filename, string &$width, string &$height, string $mode)
    {
        if (strval($width) != strval(intval($width))) {
            throw new \Exception('width should be an integer for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        $width = intval($width);
        if (empty($width)) {
            throw new \Exception('width should not be 0 or null for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        if (strval($height) != strval(intval($height))) {
            throw new \Exception('height should be an integer for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        $height = intval($height);
        if (empty($height)) {
            throw new \Exception('height should not be 0 or null for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        if (!in_array($mode, ['fit', 'crop'], true)) {
            throw new \Exception("mode should be in ['fit','mode'] for " . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        if (empty(trim($filename))) {
            throw new \Exception('filename should not be empty for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
    }

    /**
     * use $_POST['csrftoken'].
     */
    private function checkTokenForGetCacheUrlImageViaPost(int $width, int $height, string $mode): string
    {
        $csrfTokenManager = $this->getService(CsrfTokenManager::class);
        $csrfTokenChecker = $this->getService(CsrfTokenChecker::class);

        $tokenId = str_replace(
            ['{width}', '{height}', '{mode}'],
            [$width, $height, $mode],
            self::POST_CACHE_URLIMAGE_TOKEN_ID
        );

        if (!$csrfTokenChecker->checkToken($tokenId, 'POST', 'csrftoken', false)) {
            // Falling through here used to return null from a `: string` method, i.e. a
            // TypeError -- an \Error, so neither of the caller's catches (TokenNotFoundException
            // -> 401, \Exception -> 400) caught it and a bad token produced an uncaught 500.
            // It failed closed, but it crashed instead of erroring. Throw the exception the
            // caller already maps to 401, as the other two routes in this controller do.
            throw new TokenNotFoundException('invalid csrftoken for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }

        $csrfTokenManager->removeToken($tokenId);

        return $csrfTokenManager->getToken($tokenId)->getValue();
    }

    private function getCacheFileName(string $filename, int $width, int $height, string $mode): string
    {
        $attach = new Attach($this->wiki);
        $newFileName = $attach->getResizedFilename("files/$filename", $width, $height, $mode);
        if (file_exists($newFileName)) {
            return $newFileName;
        }
        $attach->redimensionner_image("files/$filename", $newFileName, $width, $height, $mode);

        return $newFileName;
    }
}
