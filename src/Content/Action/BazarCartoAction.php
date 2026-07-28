<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Search\Service\SearchManager;

class BazarCartoAction extends YesWikiAction implements RegisteredAction
{
    /** `{{bazarcarto}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'bazarcarto';
    }

    public function formatArguments($arg)
    {
        // PROVIDERS
        $get = $this->getRequest()->query;
        $provider = $get->get('provider') ?? $arg['provider'] ?? $this->params->get('baz_provider');
        $providerId = $arg['providerid'] ?? null;
        $providerPass = $arg['providerpass'] ?? null;
        if (!empty($providerId) && !empty($providerPass)) {
            if ($provider === 'MapBox') {
                $providerCredentials = ', {id: \'' . $providerId . '\', accessToken: \'' . $providerPass . '\'}';
            } else {
                $providerCredentials = ', {app_id: \'' . $providerId . '\',app_code: \'' . $providerPass . '\'}';
            }
        } else {
            $providerCredentials = '';
        }

        // MARKERS
        $markerSize = $get->get('markersize') ?? $arg['markersize'] ?? null;
        $smallMarker = $get->get('smallmarker') ?? $arg['smallmarker'] ?? $markerSize === 'small' ? '1' : $this->params->get('baz_small_marker');

        // backward compatibility for custom static map templates
        // TO remove this part when dynamic is robust AND user of custom templates are really aware of this
        $dynamic = $this->formatBoolean($arg, false, 'dynamic');
        $navigation = (!$dynamic) ?
            ($get->get('navigation') ?? $arg['navigation'] ?? $this->params->get('baz_show_nav')) :
            $this->formatBoolean($arg['navigation'] ?? $this->params->get('baz_show_nav'), true);
        $zoom_molette = (!$dynamic) ?
            ($arg['zoommolette'] ?? $this->params->get('baz_wheel_zoom')) :
            $this->formatBoolean($arg['zoommolette'] ?? $this->params->get('baz_wheel_zoom'), false);
        $fullscreen = (!$dynamic) ?
            ($arg['fullscreen'] ?? 'true') :
            $this->formatBoolean($arg, true, 'fullscreen');
        $template = (!$dynamic) ?
            ($arg['template'] ?? 'map.twig') :
            ($arg['template'] ?? 'map');
        if (strpos($template, 'gogomap') !== false) {
            $template = 'gogocarto';
        }
        $spider = (!$dynamic) ?
            ($arg['spider'] ?? 'false') :
            $this->formatBoolean($arg, false, 'spider');
        $cluster = (!$dynamic) ?
            ($arg['cluster'] ?? 'false') :
            $this->formatBoolean($arg, false, 'cluster');

        // Filters entries via query to remove whose withou bf_latitude nor bf_longitude

        $vSearchManager = $this->getService(SearchManager::class);

        $query = $vSearchManager->aggregateQueries($arg, $get->all());

        return [
            /*
             * Le fond de carte utilisé pour la carte
             * cf. https://github.com/leaflet-extras/leaflet-providers
             */
            'provider' => $provider,
            'providerid' => $providerId,
            'providerpass' => $providerPass,
            'provider_credentials' => $providerCredentials,
            /*
             * Une liste de fonds de carte.
             * Exemple: provider="OpenStreetMap.France" providers="OpenStreetMap.Mapnik,OpenStreetMap.France"
             * TODO: ajouter gestion "providers_credentials"
             */
            'providers' => $this->formatArray($arg['providers'] ?? []),
            /*
             * Une liste de layers (couches).
             * Exemple avec 1 layer tiles, 1 layer geojson:
             * layers="BD Carthage|Tiles|//a.tile.openstreetmap.fr/route500hydro/{z}/{x}/{y}.png,CUCS 2014|GeoJson|?wiki=geojsonCUCS2014/raw"
             * layers="BD Carthage|Tiles|//a.tile.openstreetmap.fr/route500hydro/{z}/{x}/{y}.png,CUCS 2014|GeoJson|color:'red';opacity:0.3|?wiki=geojsonCUCS2014/raw"
             *
             * format pour chaque layer : NOM|TYPE|URL ou NOM|TYPE|OPTIONS|URL
             * - OPTIONS: facultatif ex: "color:red; opacity:0.3"
             * nota bene: le séparateur d'options est le ';' et pas la ',' qui est déjà utilisée pour séparer les LAYERS.
             * - TYPE: Tiles ou GeoJson
             * - URL: Attention au Blocage d'une requête multi-origines (Cross-Origin Request).
             *  Le plus simple est de recopier les data GeoJson dans une page du Wiki puis de l'appeler avec le handler "/raw".
             * TODO: ajouter gestion "layers_credentials"
             */
            'layers' => $this->formatArray($arg['layers'] ?? []),
            // Mettre des puces petites ? non par defaut
            'markersize' => $markerSize,
            'smallmarker' => $smallMarker === '1' ? '' : ' xl',
            'iconSize' => $smallMarker === '1' ? '[15, 20]' : '[35, 46]',
            'iconAnchor' => $smallMarker === '1' ? '[8, 19]' : '[18, 45]',
            'popupAnchor' => $smallMarker === '1' ? '[0, -19]' : '[0, -45]',
            // Largeur de la carte à l'écran en pixels ou pourcentage
            'width' => $get->get('width') ?? $arg['width'] ?? $this->params->get('baz_map_width'),
            // Hauteur de la carte à l'écran en pixels ou pourcentage
            'height' => $get->get('height') ?? $arg['height'] ?? $this->params->get('baz_map_height'),
            // Latitude point central en degres WGS84 (exemple : 46.22763)
            'latitude' => $get->get('lat') ?? $arg['lat'] ?? $this->params->get('baz_map_center_lat'),
            // Longitude point central en degres WGS84 (exemple : 3.42313)
            'longitude' => $get->get('lon') ?? $arg['lon'] ?? $this->params->get('baz_map_center_lon'),
            // Niveau de zoom : de 1 (plus eloigne) a 15 (plus proche)
            'zoom' => $get->get('zoom') ?? $arg['zoom'] ?? $this->params->get('baz_map_zoom'),
            // Affiche outil de navigation
            'navigation' => $navigation,
            // Zoom sur molette
            'zoom_molette' => $zoom_molette,
            // Affichage en eclate des points superposes : true or false
            'spider' => $spider,
            // Affichage en cluster : true or false
            'cluster' => $cluster,
            // Ajout bouton plein écran (https://github.com/brunob/leaflet.fullscreen)
            'fullscreen' => $fullscreen,
            // Fournit une configuration JSON via un URL
            'jsonconfurl' => $arg['jsonconfurl'] ?? null,
            // template - default value map
            'template' => $template,

            'entrydisplay' => $arg['entrydisplay'] ?? 'sidebar',
            'pagination' => -1, // disable pagination
            'query' => $query,
            'geolocationfield' => $get->get('geolocationfield') ?? $arg['geolocationfield'] ?? 'bf_geolocation',
        ];
    }

    public function run()
    {
        return $this->callAction('bazarliste', $this->arguments);
    }
}
