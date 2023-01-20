<?php

namespace YesWiki\Templates\Service;

use Attach;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Wiki;

class Utils
{
    protected $params;
    protected $wiki;

    public function __construct(
        ParameterBagInterface $params,
        Wiki $wiki
    ) {
        $this->params = $params;
        $this->wiki = $wiki;
    }

    /**
     * Get the first image in the page
     *
     * @param array  $page   Page info
     * @param string $width  Width of the image
     * @param string $height Height of the image
     *
     * @return string  link to the image
     */
    public function getImageFromBody(array $page, string $width, string $height): string
    {
        $image = '';
        if (isset($page['body'])) {
            // on cherche les actions attach avec image, puis les images bazar
            $images = [];
            preg_match("/\{\{attach.*file=\"(.*\.(?i)(jpe?g|png))\".*\}\}/U", $page['body'], $images);
            if (!empty($images[1])) {
                $image = $this->getResizedFilename($images[1],$page,$page['tag'],$width, $height,true);
            } else {
                $images = [];
                if(preg_match('/"imagebf_image":"(.*)"/U', $page['body'], $images) &&
                        !empty($images[1])) {
                    $imageFileName = json_decode('"'.$images[1].'"', true);
                    if (!empty($imageFileName)){
                        if (file_exists("files/$imageFileName")){
                            $image = $this->getResizedFilename("files/$imageFileName",$page,$page['tag'],$width, $height,false);
                        }
                    }
                } else {
                    $images = [];
                    if (preg_match("/<img.*src=\"(.*\.(jpe?g|png))\"/U", $page['body'], $images) &&
                        !empty($images[1])) {
                        if (file_exists('files/'.basename($images[1][0]))){
                            $image = $this->getResizedFilename('files/'.basename($images[1]),$page,$page['tag'],$width, $height,false);
                        }
                    }
                }
            }
        }
        if (empty($image)){
            return $this->getDefaultOpenGraphImage();
        }
        return $image;
    }

    protected function getDefaultOpenGraphImage(): string
    {
        $image = '';
        if ($this->params->has('opengraph_image')){
            $opengraphImage = $this->params->get('opengraph_image');
            if (!empty($opengraphImage) &&
                is_string($opengraphImage) &&
                file_exists($opengraphImage)
                ){
                $image = "{$this->wiki->getBaseUrl()}/$opengraphImage";
            }
        }
        return $image;
    }


    protected function getResizedFilename(string $fileName, array $page, string $tag,string $width,string $height, bool $extractFullFileName = false): string
    {
        $attach = $this->getAttach();

        // current page
        $previousTag = $this->wiki->tag;
        $previousPage = $this->wiki->page;
        // fake page
        $this->wiki->tag = $tag;
        $this->wiki->page = $page;
        if ($extractFullFileName){
            if (!empty($fileName)){
                $attach->file = $fileName;
                $fileName = $attach->GetFullFilename(false);
            }
        }
        if (!empty($fileName) && file_exists($fileName)){
            $imageDest = $attach->getResizedFilename($fileName, $width, $height, 'crop');

            if (!empty($imageDest)){
                if(!file_exists($imageDest)){
                    $resizedImage = $attach->redimensionner_image(
                        $fileName,
                        $imageDest,
                        $width,
                        $height,
                        'crop'
                    );

                    if (!empty($resizedImage)){
                        $image = "{$this->wiki->getBaseUrl()}/$resizedImage";
                    }
                } else {
                    $image = "{$this->wiki->getBaseUrl()}/$imageDest";
                }
            }
        }

        // reset params
        unset($attach);
        $this->wiki->tag = $previousTag;
        $this->wiki->page = $previousPage;

        return empty($image) ? '' : $image;
    }

    protected function getAttach()
    {
        if (!class_exists('attach')) {
            include_once 'tools/attach/libs/attach.lib.php';
        }
        return new Attach($this->wiki);
    }
}
