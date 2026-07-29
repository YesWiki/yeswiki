<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\FileManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * `{{pointimage}}` -- converted from the procedural actions/pointimage.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
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
            // Several of these bodies end in $this->exit(), which throws. The old
            // runFileInBuffer() accumulated output into a by-reference variable, so a throw
            // did not discard what had already been printed; keep that by flushing into the
            // shared output before rethrowing -- and close the buffer either way.
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        // {{pointimage}} action (ticket 17, relocated from tools/attach/actions/pointimage.php).
        // Bootstrap modal/popover + jQuery converted to vanilla JS per ADR-0005 -- see
        // javascripts/pointimage.js. `file=` now accepts either a legacy raw filename or a
        // FileManager tag (same dual-path convention as Attach::CheckParams()).

        // Get the action's parameters :

        // image's filename or file tag
        $file = $this->wiki->GetParameter('file');
        if (empty($file)) {
            // former parameter from filename
            $file = $this->wiki->GetParameter('srcmap');
            if (empty($file)) {
                echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('ATTACH_ACTION_POINTIMAGE') . '</strong> : ' . _t('ATTACH_PARAM_FILE_NOT_FOUND') . '.</div>' . "\n";

                return;
            }
        }

        $fileManager = $this->wiki->services->get(FileManager::class);
        $isFileTag = $fileManager->isFileTag($file);
        if ($isFileTag) {
            $fileEntry = $fileManager->getOne($file);
            $extSource = $fileEntry['original_filename'] ?? $file;
        } else {
            $extSource = $file;
        }

        // test of image extension
        $supported_image_extensions = ['gif', 'jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($extSource, PATHINFO_EXTENSION)); // Using strtolower to overcome case sensitive
        if (!in_array($ext, $supported_image_extensions)) {
            echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('ATTACH_ACTION_POINTIMAGE') . '</strong> : ' . _t('ATTACH_PARAM_FILE_MUST_BE_IMAGE') . '.</div>' . "\n";

            return;
        }

        // image size
        $height = $this->wiki->GetParameter('height');
        $width = $this->wiki->GetParameter('width');
        if (empty($height) && empty($width)) {
            $size = 'original';
        }

        // colors of markers
        $colors = $this->wiki->GetParameter('color');
        if (empty($colors)) {
            // older parameter
            $colors = $this->wiki->GetParameter('pointcolor');
            if (empty($colors)) {
                $colors = 'green';
            }
        }
        $colors = '["' . str_replace(',', '","', $colors) . '"]';

        // labels of markers
        $labels = $this->wiki->GetParameter('label');
        if (empty($labels)) {
            $labels = _t('ATTACH_DEFAULT_MARKER');
        }
        $labels = '["' . str_replace(',', '","', $labels) . '"]';

        // default size of marker : 10 pixels
        $point_size = $this->wiki->GetParameter('pointsize');
        if (empty($point_size)) {
            $point_size = 10;
        }

        // readonly (no add of markers)
        $readonly = $this->wiki->GetParameter('readonly');

        // get an unique pagename based on the image (tag if a FileManager entry, filename otherwise)
        $dbService = $this->wiki->services->get(\YesWiki\Kernel\Service\DbService::class);
        $baseForPageTag = $isFileTag ? $file : preg_replace('/[^A-Za-z0-9 ]/', '', str_replace('.' . $ext, '', $file));
        $datapagetag = $dbService->escape($this->wiki->GetPageTag() . 'PI' . $baseForPageTag);

        // save the posted data
        if (isset($_POST['title']) && !empty($_POST['title'])
            && isset($_POST['description']) && !empty($_POST['description'])
            && isset($_POST['pagetag']) && !empty($_POST['pagetag'])
            && isset($_POST['image_x']) && !empty($_POST['image_x'])
            && isset($_POST['image_y']) && !empty($_POST['image_y'])
            && isset($_POST['color']) && !empty($_POST['color'])) {
            $pagetag = $dbService->escape(str_replace($this->wiki->config['base_url'], '', $_POST['pagetag']));
            $chaine = "\n\n~~\"\"<!--" . $_POST['image_x'] . '-' . $_POST['image_y'] . '-' . $_POST['color'] . '--><!--title-->' . $_POST['title'] . "<!--/title-->\"\"\n\"\"<!--desc-->\"\"" . $_POST['description'] . "\"\"<!--/desc-->\n\"\"~~";
            $donneesbody = $this->wiki->LoadSingle('SELECT * FROM ' . $this->wiki->config['table_prefix'] . "pages WHERE tag = '" . $pagetag . "'and latest = 'Y' limit 1");
            $this->wiki->SavePage($pagetag, $donneesbody['body'] . $chaine, '', true);
            $this->wiki->Redirect($this->getService(UrlFormatter::class)->href());
        }

        // get the data for the image
        $donneesbody = $this->wiki->LoadSingle('SELECT * FROM ' . $this->wiki->config['table_prefix'] . "pages WHERE tag = '" . $datapagetag . "'and latest = 'Y' limit 1");

        // search for markers info
        preg_match_all('/~~(.*)~~/msU', $donneesbody['body'] ?? '', $locations);
        $markers = [];
        foreach ($locations[1] as $location) {
            // extract all informations if present
            preg_match('/<!--([0-9][0-9]*)-([0-9][0-9]*)-(.*)--><!--title-->(.*)<!--\/title-->.*<!--desc-->\"\"(.*)\"\"<!--\/desc-->/msU', $location, $elements);
            if ($elements[1]) {
                $marker['x'] = round($elements[1]);
                $marker['y'] = round($elements[2]);
                $marker['color'] = $elements[3];
                $marker['title'] = $elements[4];
                $marker['description'] = $this->wiki->Format($elements[5]);
            }

            if (count($marker) == 5) {
                $markers[] = $marker;
            }
        }

        // create markers links
        $listofmarkers = '';
        if (count($markers) > 0) {
            foreach ($markers as $nb => $marker) {
                // all informations must be written in one line and escaped from html chars
                $marker['title'] = htmlspecialchars(str_replace(["\r\n", "\r", "\n", PHP_EOL, chr(10), chr(13), chr(10) . chr(13)], '', $marker['title']), ENT_QUOTES, YW_CHARSET);
                $marker['description'] = htmlspecialchars(str_replace(["\r\n", "\r", "\n", PHP_EOL, chr(10), chr(13), chr(10) . chr(13)], '', $marker['description']), ENT_QUOTES, YW_CHARSET);

                $listofmarkers .= '<a
            class="img-marker"
            style="height:' . $point_size . 'px;width:' . $point_size . 'px;left:' . ($marker['x'] - round($point_size / 2)) . 'px;
            top:' . ($marker['y'] - round($point_size / 2)) . 'px;background:' . $marker['color'] . ';"
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

        // adds the javascript just one time
        $this->wiki->addJavascriptFile('javascripts/pointimage.js');

        // output the image on the page

        echo $modal . '<div class="pointimage-container no-dblclick" data-readonly="' . ((!empty($readonly) && $readonly == 1) ? 'true' : 'false') . '" data-markerscolor=\'' . $colors . '\' data-markerslabel=\'' . $labels . '\' data-markersize="' . $point_size . '" data-pagetag="' . $this->getService(UrlFormatter::class)->href('', $datapagetag) . '">' . "\n";
        if (isset($size)) {
            echo $this->wiki->Format('{{attach file="' . $file . '" desc="image ' . $file . '" size="original" class="pointimage-image" nofullimagelink="1"}}');
        } else {
            echo $this->wiki->Format('{{attach file="' . $file . '" desc="image ' . $file . '"' . (!empty($width) ? ' width="' . $width . '"' : '') . (!empty($height) ? ' height="' . $height . '"' : '') . ' class="pointimage-image" nofullimagelink="1"}}');
        }
        echo $listofmarkers;
        echo '</div> <!-- /.pointimage-container -->' . "\n";
    }
}
