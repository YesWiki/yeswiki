<?php

namespace YesWiki\Core\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Service\ArchiveService;
use YesWiki\Core\YesWikiController;

class ArchiveController extends YesWikiController
{
    protected $archiveService;
    protected $params;

    public function __construct(
        ArchiveService $archiveService,
        ParameterBagInterface $params
    ) {
        $this->archiveService = $archiveService;
        $this->params = $params;
    }

    public function getArchive(string $id)
    {
        $filePath = $this->archiveService->getFilePath($id);
        if (empty($filePath)) {
            return new ApiResponse(
                ['error' => "Not existing file ".htmlspecialchars($id)],
                Response::HTTP_BAD_REQUEST
            );
        } else {
            $zipContent = file_get_contents($filePath) ;
            $zipSize = filesize($filePath);
            // to prevent existing headers because of handlers /show or others
            $nbObLevels = ob_get_level();
            for ($i=1; $i < $nbObLevels; $i++) {
                ob_end_clean();
            }
            for ($i=1; $i < $nbObLevels; $i++) {
                ob_start();
            }

            return new Response(
                $zipContent, // content
                Response::HTTP_OK,
                [   // headers
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Credentials' => 'true',
                    'Access-Control-Allow-Headers' => 'X-Requested-With, Location, Slug, Accept, Content-Type',
                    'Access-Control-Expose-Headers' => 'Location, Slug, Accept, Content-Type',
                    'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS, DELETE, PUT, PATCH',
                    'Access-Control-Max-Age' => '86400',
                    // end of part inspired from ApiResponse
                    //Set the Content-Type, Content-Disposition and Content-Length headers.
                    "Content-Type" => "application/zip",
                    "Content-Disposition" => "attachment; filename=$id",
                    "Content-Length" => $zipSize
                ]
            );
        }
    }

    public function manageArchiveAction(?string $id = null)
    {
        $action = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW);
        $action = in_array($action, [false,null], true) ? "" : htmlspecialchars(strip_tags($action));
        switch ($action) {
            case 'delete':
                if (!empty($id)) {
                    $filenames = [$id];
                } elseif (isset($_POST['filesnames']) && is_array($_POST['filesnames'])) {
                    $filenames = $_POST['filesnames'];
                } else {
                    return new ApiResponse(
                        ['error' => "\$_POST['filesnames'] should be set and be an array for action 'delete'"],
                        Response::HTTP_BAD_REQUEST
                    );
                }
                $results = $this->archiveService->deleteArchives($filenames);
                return new ApiResponse(
                    $results,
                    $results['main'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
                );
                break;
            case 'startArchive':
                return new ApiResponse(
                    [],
                    Response::HTTP_OK
                );
                break;
            case 'stopArchive':
                return new ApiResponse(
                    [],
                    Response::HTTP_OK
                );
                break;
            case 'archiveStatus':
                return new ApiResponse(
                    [],
                    Response::HTTP_OK
                );
                break;
            
            default:
                return new ApiResponse(
                    ['error' => "Not supported action : $action"],
                    Response::HTTP_BAD_REQUEST
                );
                break;
        }
    }
}
