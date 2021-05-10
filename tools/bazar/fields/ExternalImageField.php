<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"externalimagefield"})
 */
class ExternalImageField extends ImageField
{
    protected $JSONFormAddress ;

    public const FIELD_JSON_FORM_ADDR = 13 ;// replace nothing

    public function __construct(array $values, ContainerInterface $services)
    {
        $values[self::FIELD_TYPE] = 'image';
        $this->JSONFormAddress = $values[self::FIELD_JSON_FORM_ADDR];
        $values[self::FIELD_JSON_FORM_ADDR] = '';

        parent::__construct($values, $services);
    }

    protected function renderInput($entry)
    {
        return null;
    }

    public function formatValuesBeforeSave($entry)
    {
        return null;
    }

    protected function renderStatic($entry)
    {
        // inspired from parent but with different href
        $value = $this->getValue($entry);

        if (isset($value) && $value != '' && $this->isExistingUrl($entry['external-data']['baseUrl'].BAZ_CHEMIN_UPLOAD . $value)) {
            return $this->displayImageWithoutCache(
                $entry['external-data']['baseUrl'],
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

    /**
     * test is url exits
     * @param string $url
     * @return bool
     */
    private function isExistingUrl(string $url):bool
    {
        $url_headers = @get_headers($url);
        if(!$url_headers || $url_headers[0] == 'HTTP/1.1 404 Not Found') {
            return false;
        }
        else {
            return true;
        }
    }

    /**
     * display image inspired from afficher_image() - but without cache only test of existing files
     * @param    string $baseUrl
     * @param    string champ de la base
     * @param    string nom du fichier image
     * @param    string label pour l'image
     * @param    string classes html supplementaires
     * @param    int        largeur en pixel de la vignette
     * @param    int        hauteur en pixel de la vignette
     * @param    int        largeur en pixel de l'image redimensionnee
     * @param    int        hauteur en pixel de l'image redimensionnee
     * @param    string $method
     * @param    bool $show_vignette
     * @return   string
     */
    private function displayImageWithoutCache(
        string $baseUrl,
        string $champ,
        string $nom_image,
        string $label,
        string $class,
        int $largeur_vignette,
        int $hauteur_vignette,
        int $largeur_image,
        int $hauteur_image,
        string $method = 'fit',
        bool $show_vignette = true
    ):string 
    {
        // l'image initiale existe t'elle et est bien avec une extension jpg ou png et bien formatee
        $destimg = sanitizeFilename($nom_image);
        // If we have a full URL, remove the base URL first
        $nom_image = str_replace($baseUrl . BAZ_CHEMIN_UPLOAD, '', $nom_image);
        if ($this->isExistingUrl($baseUrl .BAZ_CHEMIN_UPLOAD . $nom_image)
        && preg_match('/^.*\.(jpg|jpe?g|png|gif)$/i', strtolower($nom_image))) {
            // vignette?
            if ($hauteur_vignette != '' && $largeur_vignette != '') {
                $adr_vignette = $baseUrl .'cache/vignette_' . $destimg;

                //faut il redimensionner l'image?
                if ($hauteur_image != '' && $largeur_image != '') {
                    $adr_newsize = $baseUrl .'cache/image_' . $destimg;

                    $src_Addr = $show_vignette ? $adr_vignette : $adr_newsize;
                    //on renvoit l'image en vignette, avec quand on clique, l'image redimensionnee
                    return '<a data-id="' . $champ . '" class="modalbox ' . $class
                        .'" href="' . $adr_newsize . '" title="' . htmlentities($nom_image) . '">' . "\n"
                        .'<img src="' . $src_Addr . '" alt="' . $destimg . '"'
                        . 'onerror="this.src=\''.$baseUrl . BAZ_CHEMIN_UPLOAD . $nom_image.'\'"'
                        .' />'."\n"
                        .'</a> <!-- ' . $nom_image . ' -->' . "\n";
                } else {
                    //on renvoit l'image en vignette, avec quand on clique, l'image originale
                    return '<a data-id="' . $champ . '" class="modalbox ' . $class
                        . '" href="' . $baseUrl . BAZ_CHEMIN_UPLOAD . $nom_image . '" title="' . htmlentities($nom_image) . '">' . "\n"
                        . '<img class="img-responsive" src="' . $adr_vignette
                        . '" alt="' . $nom_image . '"'
                        . 'onerror="this.src=\''.$baseUrl . BAZ_CHEMIN_UPLOAD . $nom_image.'\'"'
                        . ' />' . "\n"
                        . '</a> <!-- ' . $nom_image . ' -->' . "\n";
                }
            } elseif ($hauteur_image != '' && $largeur_image != '') {
                //pas de vignette, mais faut il redimensionner l'image?
                
                $adr_newsize = $baseUrl .'cache/image_' . $destimg;

                return '<img src="' . $adr_newsize . '" class="img-responsive ' . $class
                    . '" alt="' . $destimg . '"' 
                    . 'onerror="this.src=\''.$baseUrl . BAZ_CHEMIN_UPLOAD . $nom_image.'\'"'
                    . ' />' . "\n";
            } else {
                //on affiche l'image originale sinon
                return '<img src="'. $url_base . BAZ_CHEMIN_UPLOAD . $nom_image . '" class="img-responsive ' . $class
                    . '" alt="' . $destimg . '"' . ' />' . "\n";
            }
        }
    }

}
