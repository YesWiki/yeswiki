<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"image"})
 */
class ImageField extends FileField
{
    protected $thumbnailHeight;
    protected $thumbnailWidth;
    protected $imageHeight;
    protected $imageWidth;
    protected $imageClass;

    protected const FIELD_THUMBNAIL_HEIGHT = 3;
    protected const FIELD_THUMBNAIL_WIDTH = 4;
    protected const FIELD_IMAGE_HEIGHT = 5;
    protected const FIELD_IMAGE_WIDTH = 6;
    protected const FIELD_IMAGE_CLASS = 7;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->thumbnailHeight = $values[self::FIELD_THUMBNAIL_HEIGHT];
        $this->thumbnailWidth = $values[self::FIELD_THUMBNAIL_WIDTH];
        $this->imageHeight = $values[self::FIELD_IMAGE_HEIGHT];
        $this->imageWidth = $values[self::FIELD_IMAGE_WIDTH];
        $this->imageClass = $values[self::FIELD_IMAGE_CLASS];

        // We can have no default for images
        $this->default = null;
    }

    public function renderInput($entry)
    {
        $value = $this->getValue($entry);
        $maxSize = $GLOBALS['wiki']->config['BAZ_TAILLE_MAX_FICHIER'] ;

        // javascript pour gerer la previsualisation
        $js = 'function getOrientation(file, callback) {
          var reader = new FileReader();
          reader.onload = function(e) {
        
            var view = new DataView(e.target.result);
            if (view.getUint16(0, false) != 0xFFD8) return callback(-2);
            var length = view.byteLength, offset = 2;
            while (offset < length) {
              var marker = view.getUint16(offset, false);
              offset += 2;
              if (marker == 0xFFE1) {
                if (view.getUint32(offset += 2, false) != 0x45786966) return callback(-1);
                var little = view.getUint16(offset += 6, false) == 0x4949;
                offset += view.getUint32(offset + 4, little);
                var tags = view.getUint16(offset, little);
                offset += 2;
                for (var i = 0; i < tags; i++)
                  if (view.getUint16(offset + (i * 12), little) == 0x0112)
                    return callback(view.getUint16(offset + (i * 12) + 8, little));
              }
              else if ((marker & 0xFF00) != 0xFF00) break;
              else offset += view.getUint16(offset, false);
            }
            return callback(-1);
          };
          reader.readAsArrayBuffer(file.slice(0, 64 * 1024));
        }


        function handleFileSelect(evt) {
          var target = evt.target || evt.srcElement;
          var id = target.id;
          var files = target.files; // FileList object

          // Loop through the FileList and render image files as thumbnails.
          for (var i = 0, f; f = files[i]; i++) {

            // Only process image files.
            if (!f.type.match(\'image.*\')) {
              continue;
            }';

        // si une taille maximale est indiquée, on teste
        if (!empty($maxSize)) {
            $js .= 'if (f.size>'.$maxSize.') {
                    alert("L\'image est trop grosse, maximum '.$maxSize.' octets");
                    document.getElementById(id).type = \'\';
                    document.getElementById(id).type = \'file\';
                    continue ;
                  }';
        }

        $js .= 'var reader = new FileReader();
            // Closure to capture the file information.
            reader.onload = (function(theFile) {
              return function(e) {
                getOrientation(theFile, function(orientation) {
                  var css = \'\';
                  if (orientation === 6) {
                    css = \'transform:rotate(90deg);\';
                  } else if (orientation === 8) {
                    css = \'transform:rotate(270deg);\';
                  } else if (orientation === 3) {
                    css = \'transform:rotate(180deg);\';
                  } else {
                    css = \'\';
                  }
                  // TODO: rotate image
                  //console.log(\'orientation: \' + css);
                  css = \'\';
                  // Render thumbnail.
                  var span = document.createElement(\'span\');
                  span.innerHTML = [\'<img class="img-responsive" style="\', css, \'" src="\', e.target.result,
                  \'" title="\', escape(theFile.name), \'"/>\'].join(\'\');
                  document.getElementById(\'img-\'+id).innerHTML = span.innerHTML;
                  document.getElementById(\'data-\'+id).value = e.target.result;
                  document.getElementById(\'filename-\'+id).value = theFile.name;
                });

              };
            })(f);

            // Read in the image file as a data URL.
            reader.readAsDataURL(f);
          }
        }

        var imageinputs = document.getElementsByClassName(\'yw-image-upload\');
        for (var i = 0; i < imageinputs.length; i++)
        {
           imageinputs.item(i).addEventListener(\'change\', handleFileSelect, false);
        }';
        $GLOBALS['wiki']->addJavascript($js);

        if (isset($value) && $value != '') {
            if (isset($_GET['suppr_image']) && $_GET['suppr_image'] == $value) {
                if (baz_a_le_droit('supp_fiche', (isset($entry['createur']) ? $entry['createur'] : ''))) {
                    if (file_exists(BAZ_CHEMIN_UPLOAD . $value)) {
                        unlink(BAZ_CHEMIN_UPLOAD . $value);
                    }

                    // Update entry
                    // TODO use EntryManager
                    unset($entry[$this->propertyName]);
                    $updatedEntry = $entry;
                    $updatedEntry['date_maj_fiche'] = date('Y-m-d H:i:s', time());
                    $updatedEntry['id_fiche'] = $entry['id_fiche'];
                    if (YW_CHARSET != 'UTF-8') {
                        $updatedEntry = array_map('utf8_encode', $updatedEntry);
                    }
                    $updatedEntry = json_encode($updatedEntry);
                    $GLOBALS["wiki"]->SavePage($entry['id_fiche'], $updatedEntry);

                    return '<div class="alert alert-info">' . _t('BAZ_FICHIER') . $value . _t('BAZ_A_ETE_EFFACE') . '</div>';
                } else {
                    return '<div class="alert alert-info">' . _t('BAZ_DROIT_INSUFFISANT') . '</div>' . "\n";
                }
            }

            if (file_exists(BAZ_CHEMIN_UPLOAD . $value)) {
                return $this->render('@bazar/inputs/image.twig', [
                    'value' => $value,
                    'downloadUrl' => BAZ_CHEMIN_UPLOAD . $value,
                    'deleteUrl' => $GLOBALS['wiki']->href('edit', $GLOBALS['wiki']->GetPageTag(), 'suppr_image=' . $value, false),
                    'image' => afficher_image(
                        $this->name,
                        $value,
                        $this->label,
                        'img-responsive',
                        $this->thumbnailWidth,
                        $this->thumbnailHeight,
                        $this->imageWidth,
                        $this->imageHeight
                    )
                ]);
            } else {
                // TODO update entry (see above)

                return '<div class="alert alert-danger">' . _t('BAZ_FICHIER') . $value . _t('BAZ_FICHIER_IMAGE_INEXISTANT') . '</div>';
            }
        } else {
            return $this->render('@bazar/inputs/image.twig');
        }
    }

    public function formatValuesBeforeSave($entry)
    {
        if (!empty($_POST['data-'.$this->propertyName]) and !empty($_POST['filename-'.$this->propertyName])) {
            $fileName = $entry['id_fiche'] . '_' . sanitizeFilename($_POST['filename-'.$this->propertyName]);
            $filePath = BAZ_CHEMIN_UPLOAD . $fileName;

            if (preg_match("/(gif|jpeg|png|jpg)$/i", $fileName)) {
                if (!file_exists($filePath)) {
                    file_put_contents($filePath, file_get_contents($_POST['data-'.$this->propertyName]));
                    chmod($filePath, 0755);

                    // Generate thumbnails
                    if ($this->thumbnailWidth != '' && $this->thumbnailHeight != '' && !file_exists('cache/vignette_' . $fileName)) {
                        redimensionner_image($filePath, 'cache/vignette_' . $fileName, $this->thumbnailWidth, $this->thumbnailHeight);
                    }

                    // Adapt image dimensions
                    if ($this->imageWidth != '' && $this->imageWidth != '' && !file_exists('cache/image_' . '_' . $fileName)) {
                        redimensionner_image($filePath, 'cache/image_' . $fileName, $this->imageWidth, $this->imageHeight);
                    }
                } else {
                    echo '<div class="alert alert-danger">L\'image ' . $fileName . ' existait d&eacute;ja, elle n\'a pas &eacute;t&eacute; remplac&eacute;e.</div>';
                }
            } else {
                echo '<div class="alert alert-danger">Extension non autoris&eacute;.</div>';
            }

            return [
                $this->propertyName => $fileName,
                'fields-to-remove' => ['filename-'.$this->propertyName, 'data-'.$this->propertyName, 'oldimage_'.$this->propertyName]
            ];
        } elseif (isset($entry['oldimage_' . $this->propertyName]) && $entry['oldimage_' . $this->propertyName] != '') {
            return [
                $this->propertyName => $entry['oldimage_' . $this->propertyName],
                'fields-to-remove' => ['filename-'.$this->propertyName, 'data-'.$this->propertyName, 'oldimage_' . $this->propertyName]
            ];
        }
    }

    public function renderStatic($entry)
    {
        $value = $this->getValue($entry);

        if (isset($value) && $value != '' && file_exists(BAZ_CHEMIN_UPLOAD . $value)) {
            return afficher_image(
                $this->name,
                $value,
                $this->label,
                $this->imageClass,
                $this->thumbnailWidth,
                $this->thumbnailHeight,
                $this->imageWidth,
                $this->imageHeight
            );
        }

        return null;
    }
}
