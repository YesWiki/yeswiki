<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\PageManager;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\MarkdownFormatterService;

/** `{{pointimage}}` -- converted from the procedural actions/pointimage.php by ticket 06. */
class PointimageAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'pointimage';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        $file = $this->getService(PerformableArguments::class)->get('file');
        if (empty($file)) {
            $file = $this->getService(PerformableArguments::class)->get('srcmap');
            if (empty($file)) {
                echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('ATTACH_ACTION_POINTIMAGE') . '</strong> : ' . _t('ATTACH_PARAM_FILE_NOT_FOUND') . '.</div>' . "\n";

                return;
            }
        }

        $fileManager = $this->getService(FileManager::class);
        $isFileTag = $fileManager->isFileTag($file);
        if ($isFileTag) {
            $fileEntry = $fileManager->getOne($file);
            $extSource = $fileEntry['original_filename'] ?? $file;
        } else {
            $extSource = $file;
        }

        $supported_image_extensions = ['gif', 'jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($extSource, PATHINFO_EXTENSION));
        if (!in_array($ext, $supported_image_extensions)) {
            echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('ATTACH_ACTION_POINTIMAGE') . '</strong> : ' . _t('ATTACH_PARAM_FILE_MUST_BE_IMAGE') . '.</div>' . "\n";

            return;
        }

        $height = $this->getService(PerformableArguments::class)->get('height');
        $width = $this->getService(PerformableArguments::class)->get('width');
        if (empty($height) && empty($width)) {
            $size = 'original';
        }

        $colors = $this->getService(PerformableArguments::class)->get('color');
        if (empty($colors)) {
            $colors = $this->getService(PerformableArguments::class)->get('pointcolor');
            if (empty($colors)) {
                $colors = 'green';
            }
        }
        $colorsList = array_values(array_filter(array_map('trim', explode(',', (string)$colors))));
        if (empty($colorsList)) {
            $colorsList = ['green'];
        }

        $labels = $this->getService(PerformableArguments::class)->get('label');
        if (empty($labels)) {
            $labels = _t('ATTACH_DEFAULT_MARKER');
        }
        $labelsList = array_map('trim', explode(',', (string)$labels));

        $point_size = (int)$this->getService(PerformableArguments::class)->get('pointsize');
        if ($point_size <= 0) {
            $point_size = 10;
        }

        $readonly = $this->getService(PerformableArguments::class)->get('readonly');

        $dbService = $this->getService(DbService::class);
        $baseForPageTag = $isFileTag ? $file : preg_replace('/[^A-Za-z0-9 ]/', '', str_replace('.' . $ext, '', $file));

        $datapagetag = $this->getService(PageContext::class)->getTag() . 'PI' . $baseForPageTag;

        $postedpagetag = isset($_POST['pagetag']) && is_string($_POST['pagetag'])
            ? preg_replace('/[?&].*$/', '', str_replace($this->getService(RuntimeConfig::class)['base_url'], '', $_POST['pagetag']))
            : '';
        if ($postedpagetag === $datapagetag
            && !empty($_POST['title']) && is_string($_POST['title'])
            && !empty($_POST['description']) && is_string($_POST['description'])
            && !empty($_POST['image_x']) && is_string($_POST['image_x'])
            && !empty($_POST['image_y']) && is_string($_POST['image_y'])
            && !empty($_POST['color']) && is_string($_POST['color'])) {
            if (!empty($readonly) && $readonly == 1) {
                echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('ATTACH_ACTION_POINTIMAGE') . '</strong> : ' . _t('ATTACH_MARKER_NOT_ALLOWED') . '</div>' . "\n";

                return;
            }
            try {
                $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);
            } catch (\Throwable $error) {
                echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('ATTACH_ACTION_POINTIMAGE') . '</strong> : ' . htmlspecialchars($error->getMessage(), ENT_QUOTES, YW_CHARSET) . '.</div>' . "\n";

                return;
            }
            if (!$this->getService(AclService::class)->hasAccess('write', $datapagetag)) {
                echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('ATTACH_ACTION_POINTIMAGE') . '</strong> : ' . _t('ATTACH_MARKER_NOT_ALLOWED') . '</div>' . "\n";

                return;
            }
            $marker_x = max(0, (int)$_POST['image_x']);
            $marker_y = max(0, (int)$_POST['image_y']);
            $marker_color = in_array($_POST['color'], $colorsList, true) ? $_POST['color'] : $colorsList[0];
            $marker_title = str_replace(['~~', '<!--', '-->', "\r", "\n"], ' ', $_POST['title']);
            $marker_desc = str_replace(['~~', '<!--', '-->'], ' ', $_POST['description']);
            $chaine = "\n\n~~\"\"<!--" . $marker_x . '-' . $marker_y . '-' . $marker_color . '--><!--title-->' . $marker_title . "<!--/title-->\"\"\n\"\"<!--desc-->\"\"" . $marker_desc . "\"\"<!--/desc-->\n\"\"~~";
            $donneesbody = $this->getService(DbService::class)->loadSingle(
                'SELECT * FROM ' . $this->getService(RuntimeConfig::class)['table_prefix'] . "pages WHERE tag = ? and latest = 'Y' limit 1",
                [$datapagetag]
            );

            $markersBody = PageBody::decode($donneesbody['body'] ?? null);
            $markersBody[PageBody::CONTENT] = PageBody::content($markersBody) . $chaine;
            $this->getService(PageManager::class)->save($datapagetag, $markersBody, '', true);
            $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href());
        }

        $donneesbody = $this->getService(DbService::class)->loadSingle(
            'SELECT * FROM ' . $this->getService(RuntimeConfig::class)['table_prefix'] . "pages WHERE tag = ? and latest = 'Y' limit 1",
            [$datapagetag]
        );

        preg_match_all('/~~(.*)~~/msU', PageBody::content(PageBody::decode($donneesbody['body'] ?? null)), $locations);
        $markers = [];
        foreach ($locations[1] as $location) {
            $marker = [];

            preg_match('/<!--([0-9][0-9]*)-([0-9][0-9]*)-(.*)--><!--title-->(.*)<!--\/title-->.*<!--desc-->\"\"(.*)\"\"<!--\/desc-->/msU', $location, $elements);
            if (!empty($elements[1])) {
                $marker['x'] = (int)$elements[1];
                $marker['y'] = (int)$elements[2];
                $marker['color'] = $elements[3];
                $marker['title'] = $elements[4];
                $marker['description'] = $this->getService(MarkdownFormatterService::class)->format($elements[5]);
            }

            if (count($marker) == 5) {
                $markers[] = $marker;
            }
        }

        $listofmarkers = '';
        if (count($markers) > 0) {
            foreach ($markers as $nb => $marker) {
                $marker['title'] = htmlspecialchars(htmlspecialchars(str_replace(["\r\n", "\r", "\n", PHP_EOL, chr(10), chr(13), chr(10) . chr(13)], '', $marker['title']), ENT_QUOTES, YW_CHARSET), ENT_QUOTES, YW_CHARSET);
                $marker['description'] = htmlspecialchars(str_replace(["\r\n", "\r", "\n", PHP_EOL, chr(10), chr(13), chr(10) . chr(13)], '', $marker['description']), ENT_QUOTES, YW_CHARSET);

                $listofmarkers .= '<a
            class="img-marker"
            style="height:' . $point_size . 'px;width:' . $point_size . 'px;left:' . ($marker['x'] - round($point_size / 2)) . 'px;
            top:' . ($marker['y'] - round($point_size / 2)) . 'px;background:' . htmlspecialchars($marker['color'], ENT_QUOTES, YW_CHARSET) . ';"
            tabindex="0"
            data-yw-popover-title="' . $marker['title'] . '"
            data-yw-popover-content="' . $marker['description'] . '" href="#"></a>' . "\n";
            }
        }

        $modal = '
        	<div class="yw-modal modal-pointimage">
        	  <div class="yw-modal__dialog">
        	    <div class="yw-modal__content">
        	      <div class="yw-modal__header">
        	        <h4 class="yw-modal__title">' . _t('ATTACH_ADD_MARKER') . '</h4>
        	        <button type="button" class="yw-close" data-yw-dismiss="modal" aria-hidden="true">&times;</button>
        	      </div>
        	      <form class="form-pointimage" method="post" action="' . $this->getService(UrlFormatter::class)->href() . '">
        	      <input type="hidden" name="csrf-token" value="' . htmlspecialchars($this->getService(CsrfTokenManager::class)->getToken('main')->getValue(), ENT_QUOTES, YW_CHARSET) . '" />
        	      <div class="yw-modal__body">
        	      	<div class="yw-form-group markers-choice"></div>
        	     	<div class="yw-form-group">
        	        	<input name="title" required="required" class="yw-input" placeholder="' . _t('ATTACH_TITLE') . '" />
        	        </div>
        	        <div class="yw-form-group">
        	        	<textarea name="description" required="required" class="yw-input wiki-textarea" placeholder="' . _t('ATTACH_DESCRIPTION') . '"></textarea>
        	        </div>
        	      </div>
        	      <div class="yw-modal__footer">
        	        <button type="button" class="yw-btn btn-close" data-yw-dismiss="modal">' . _t('ATTACH_CANCEL') . '</button>
        	        <button type="submit" class="yw-btn yw-btn--primary btn-save">' . _t('ATTACH_SAVE') . '</button>
        	      </div>
        	      </form>
        	    </div><!-- /.yw-modal__content -->
        	  </div><!-- /.yw-modal__dialog -->
        	</div><!-- /.yw-modal -->' . "\n";

        $this->getService(AssetRegistry::class)->addJsFile('javascripts/pointimage.js');

        echo $modal . '<div class="pointimage-container no-dblclick" data-readonly="' . ((!empty($readonly) && $readonly == 1) ? 'true' : 'false') . '" data-markerscolor="' . htmlspecialchars((string)json_encode($colorsList), ENT_QUOTES, YW_CHARSET) . '" data-markerslabel="' . htmlspecialchars((string)json_encode($labelsList), ENT_QUOTES, YW_CHARSET) . '" data-markersize="' . $point_size . '" data-pagetag="' . $this->getService(UrlFormatter::class)->href('', $datapagetag) . '">' . "\n";
        if (isset($size)) {
            echo $this->getService(MarkdownFormatterService::class)->format('{{attach file="' . $file . '" desc="image ' . $file . '" size="original" class="pointimage-image" nofullimagelink="1"}}');
        } else {
            echo $this->getService(MarkdownFormatterService::class)->format('{{attach file="' . $file . '" desc="image ' . $file . '"' . (!empty($width) ? ' width="' . $width . '"' : '') . (!empty($height) ? ' height="' . $height . '"' : '') . ' class="pointimage-image" nofullimagelink="1"}}');
        }
        echo $listofmarkers;
        echo '</div> <!-- /.pointimage-container -->' . "\n";
    }
}
