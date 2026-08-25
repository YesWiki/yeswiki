<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Files\Service\ImageResizer;
use YesWiki\Files\Service\Storage;
use YesWiki\Render\Service\MarkdownFormatterService;

/** A page's title and its first picture, for the cards `{{filtertags}}` and friends draw. */
class PageSummary
{
    public function __construct(private readonly ContainerInterface $services)
    {
    }

    /**
     * The page's title: its own if it has one, else its first big heading, else its tag.
     *
     * @param array<string, mixed> $page
     */
    public function title(array $page): string
    {
        $body = $page['body'] ?? [];
        $content = PageBody::content($body);

        $entryTitle = (string)($body[PageBody::TITLE] ?? $body['bf_titre'] ?? '');
        if ($entryTitle !== '') {
            $title = $entryTitle;
        } else {
            preg_match_all("/\={6}(.*)\={6}/U", $content, $titles);
            if (isset($titles[1][0]) && $titles[1][0] != '') {
                $title = $this->services->get(MarkdownFormatterService::class)->format(trim($titles[1][0]));
            } else {
                preg_match_all('/={5}(.*)={5}/U', $content, $titles);
                if (isset($titles[1][0]) && $titles[1][0] != '') {
                    $title = $this->services->get(MarkdownFormatterService::class)->format(trim($titles[1][0]));
                } else {
                    $title = $page['tag'];
                }
            }
        }

        return strip_tags($title);
    }

    /**
     * The first picture the page shows, as an `<img>`, or '' when it shows none.
     *
     * @param array<string, mixed> $page
     */
    public function image(array $page): string
    {
        $body = $page['body'] ?? [];
        $content = PageBody::content($body);
        preg_match_all("/\{\{attach.*file=\".*\.(?i)(jpg|png|gif|bmp).*\}\}/U", $content, $images);
        if (isset($images[0][0]) && $images[0][0] != '') {
            preg_match_all("/.*file=\"(.*\.(?i)(jpg|png|gif|bmp))\".*desc=\"(.*)\".*\}\}/U", $images[0][0], $attachimg);
            $image = $this->thumbnail(
                $this->services->get(AttachedFilePaths::class)->uploadPath()
                    . '/' . $attachimg[1][0],
                $attachimg[3][0] ?? '',
                'filtered-image'
            );
        } else {
            $imagefile = $body['imagebf_image'] ?? '';
            if ($imagefile != '') {
                $image = $this->thumbnail('files/' . $imagefile, '', 'filtered-image img-responsive');
            } else {
                preg_match_all("/\[\[(http.*\.(?i)(jpg|png|gif|bmp)) .*\]\]/U", $content, $image);
                if (isset($image[1][0])) {
                    $image = $this->services->get(MarkdownFormatterService::class)->format('""<img loading="lazy" alt=\'\' class="img-responsive" src="' . trim(str_replace('\\', '', $image[1][0])) . '" />""');
                } else {
                    preg_match_all("/\<img.*src=\"(.*)\"/U", $content, $image);
                    if (isset($image[1][0])) {
                        $image = $this->services->get(MarkdownFormatterService::class)->format('""<img loading="lazy" alt=\'\' class="img-responsive" src="' . trim($image[1][0]) . '" />""');
                    } else {
                        $image = '';
                    }
                }
            }
        }

        return $image;
    }

    /** A resized `<img>` for a file already on disk, or '' when it cannot be produced. */
    private function thumbnail(string $fileName, string $description, string $class): string
    {
        if ($fileName === '' || !$this->services->get(Storage::class)->exists($fileName)) {
            return '';
        }

        $resizer = $this->services->get(ImageResizer::class);
        $thumbnail = $resizer->resizedFilename($fileName, '300', '225', 'fit');
        if (!$this->services->get(Storage::class)->exists($thumbnail) && $resizer->resize($fileName, $thumbnail, 300, 225) !== $thumbnail) {
            $thumbnail = $fileName;
        }

        return '<img loading="lazy" class="' . htmlspecialchars($class, ENT_COMPAT, YW_CHARSET) . '"'
            . ' src="' . htmlspecialchars($thumbnail, ENT_COMPAT, YW_CHARSET) . '"'
            . ' alt="' . htmlspecialchars($description, ENT_COMPAT, YW_CHARSET) . '" />';
    }
}
