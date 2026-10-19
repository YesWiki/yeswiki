<?php

namespace YesWiki\Content\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FileManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Files\Service\ImageResizer;
use YesWiki\Files\Service\RemoteImageCache;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Service\SignedTokens;

class ImageApiController extends YesWikiController
{
    public const POST_CACHE_URLIMAGE_TOKEN_ID = 'POST api/images/cache/{width}/{height}/{mode}';

    /** Serve a resized copy of an uploaded image from the cache. */
    #[Route('/api/images/{filename}/cache/{width}/{height}/{mode}', methods: ['POST'], options: ['acl' => ['public']])]
    public function getCacheUrlImageViaPost(string $filename, string $width, string $height, string $mode): ApiResponse
    {
        try {
            ['width' => $width, 'height' => $height] = $this->checkParamsGetCacheUrlImageViaPost($filename, $width, $height, $mode);
            $newToken = $this->checkTokenForGetCacheUrlImageViaPost($width, $height, $mode);

            if (!$this->getService(Storage::class)->exists("files/$filename")) {
                return new ApiResponse([
                    'error' => _t('ATTACH_GET_CACHE_URLIMAGE_NO_FILE'),
                    'filename' => $filename,
                    'width' => $width,
                    'height' => $height,
                    'mode' => $mode,
                    'newToken' => $newToken,
                ], Response::HTTP_BAD_REQUEST);
            }

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

    /** Serve a resized copy of the picture an entry's image field links to on another site. */
    #[Route('/api/images/entry/{entryId}/{field}/cache/{width}/{height}/{mode}', methods: ['POST'], options: ['acl' => ['public']])]
    public function getCacheUrlRemoteImageViaPost(string $entryId, string $field, string $width, string $height, string $mode): ApiResponse
    {
        try {
            ['width' => $width, 'height' => $height] = $this->checkParamsGetCacheUrlImageViaPost($field, $width, $height, $mode);
            $newToken = $this->checkTokenForGetCacheUrlImageViaPost($width, $height, $mode);
            $this->denyAccessUnlessGranted('read', $entryId);
            $entry = $this->getService(EntryManager::class)->getOne($entryId);
            $url = $entry[$field] ?? null;
            if (!RemoteImageCache::isRemote($url)) {
                return new ApiResponse([
                    'error' => _t('ATTACH_GET_CACHE_URLIMAGE_NO_FILE'),
                    'newToken' => $newToken,
                ], Response::HTTP_BAD_REQUEST);
            }

            return new ApiResponse([
                'url' => $this->getService(RemoteImageCache::class)->localUrl($url, $width, $height),
                'width' => $width,
                'height' => $height,
                'mode' => $mode,
                'newToken' => $newToken,
            ], Response::HTTP_OK);
        } catch (TokenNotFoundException $th) {
            return new ApiResponse(['error' => $th->getMessage()], Response::HTTP_UNAUTHORIZED);
        } catch (AccessDeniedHttpException $denied) {
            return new ApiResponse(['error' => _t('ATTACH_GET_CACHE_URLIMAGE_NO_FILE')], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return new ApiResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @return array{width: int, height: int}
     */
    private function checkParamsGetCacheUrlImageViaPost(string $filename, string $width, string $height, string $mode): array
    {
        if (strval($width) != strval(intval($width))) {
            throw new \Exception('width should be an integer for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        $intWidth = intval($width);
        if (empty($intWidth)) {
            throw new \Exception('width should not be 0 or null for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        if (strval($height) != strval(intval($height))) {
            throw new \Exception('height should be an integer for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        $intHeight = intval($height);
        if (empty($intHeight)) {
            throw new \Exception('height should not be 0 or null for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        if (!in_array($mode, ['fit', 'crop'], true)) {
            throw new \Exception("mode should be in ['fit','mode'] for " . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        if (empty(trim($filename))) {
            throw new \Exception('filename should not be empty for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }

        return ['width' => $intWidth, 'height' => $intHeight];
    }

    /** Check the signed or session token sent with the request and return the next one. */
    private function checkTokenForGetCacheUrlImageViaPost(int $width, int $height, string $mode): string
    {
        $tokenId = str_replace(
            ['{width}', '{height}', '{mode}'],
            [(string)$width, (string)$height, $mode],
            self::POST_CACHE_URLIMAGE_TOKEN_ID
        );
        $signed = $this->getService(SignedTokens::class);
        if ($signed->verify($tokenId, (string)($_POST['csrftoken'] ?? ''))) {
            return $signed->sign($tokenId);
        }

        $csrfTokenManager = $this->getService(CsrfTokenManager::class);
        if (!$this->getService(CsrfTokenChecker::class)->checkToken($tokenId, 'POST', 'csrftoken', false)) {
            throw new TokenNotFoundException('invalid csrftoken for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        $csrfTokenManager->removeToken($tokenId);

        return $csrfTokenManager->getToken($tokenId)->getValue();
    }

    private function getCacheFileName(string $filename, int $width, int $height, string $mode): string
    {
        $resizer = $this->getService(ImageResizer::class);
        $newFileName = $resizer->resizedFilename("files/$filename", (string)$width, (string)$height, $mode);
        if ($this->getService(Storage::class)->exists($newFileName)) {
            return $newFileName;
        }
        $resizer->resize("files/$filename", $newFileName, $width, $height, $mode);

        return $newFileName;
    }
}
