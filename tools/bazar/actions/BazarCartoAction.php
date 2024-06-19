<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Core\YesWikiAction;

class BazarCartoAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        // PROVIDERS
        $provider = $_GET['provider'] ?? $arg['provider'] ?? $this->params->get('baz_provider');
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
        $markerSize = $_GET['markersize'] ?? $arg['markersize'] ?? null;
        $smallMarker = $_GET['smallmarker'] ?? $arg['smallmarker'] ?? $markerSize === 'small' ? '1' : $this->params->get('baz_small_marker');

        // backward compatibility for custom map.tpl.html
        // TO remove this part when dynamic is robust AND user of custom templates are really aware of this
        $dynamic = $this->formatBoolean($arg, false, 'dynamic');
        $navigation = (!$dynamic) ?
            ($_GET['navigation'] ?? $arg['navigation'] ?? $this->params->get('baz_show_nav')) :
            $this->formatBoolean($arg['navigation'] ?? $this->params->get('baz_show_nav'), true);
        $zoom_molette = (!$dynamic) ?
            ($arg['zoommolette'] ?? $this->params->get('baz_wheel_zoom')) :
            $this->formatBoolean(($arg['zoommolette'] ?? $this->params->get('baz_wheel_zoom')), false);
        $fullscreen = (!$dynamic) ?
            ($arg['fullscreen'] ?? 'true') :
            $this->formatBoolean($arg, true, 'fullscreen');
        $template = (!$dynamic) ?
            ($arg['template'] ?? 'map.tpl.html') :
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
        $query = $this->getService(EntryController::class)->formatQuery($arg, $_GET);
        if ($template != 'map-and-table' ||
            (
                !empty($arg['tablewith']) &&
                $arg['tablewith'] === 'only-geolocation'
            )
        ) {
            if (!isset($query['bf_latitude!'])) {
                $query['bf_latitude!'] = '';
            }
            if (!isset($query['bf_longitude!'])) {
                $query['bf_longitude!'] = '';
            }
        }

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
             * layers="BD Carthage|Tiles|//a.tile.openstreetmap.fr/route500hydro/{z}/{x}/{y}.png,CUCS 2014|GeoJson|wakka.php?wiki=geojsonCUCS2014/raw"
             * layers="BD Carthage|Tiles|//a.tile.openstreetmap.fr/route500hydro/{z}/{x}/{y}.png,CUCS 2014|GeoJson|color:'red';opacity:0.3|wakka.php?wiki=geojsonCUCS2014/raw"
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
            'width' => $_GET['width'] ?? $arg['width'] ?? $this->params->get('baz_map_width'),
            // Hauteur de la carte à l'écran en pixels ou pourcentage
            'height' => $_GET['height'] ?? $arg['height'] ?? $this->params->get('baz_map_height'),
            // Latitude point central en degres WGS84 (exemple : 46.22763)
            'latitude' => $_GET['lat'] ?? $arg['lat'] ?? $this->params->get('baz_map_center_lat'),
            // Longitude point central en degres WGS84 (exemple : 3.42313)
            'longitude' => $_GET['lon'] ?? $arg['lon'] ?? $this->params->get('baz_map_center_lon'),
            // Niveau de zoom : de 1 (plus eloigne) a 15 (plus proche)
            'zoom' => $_GET['zoom'] ?? $arg['zoom'] ?? $this->params->get('baz_map_zoom'),
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
            //template - default value map
            'template' => $template,

            'entrydisplay' => $arg['entrydisplay'] ?? 'sidebar',
            'pagination' => -1, // disable pagination
            'query' => $query,
        ];
    }

    public function run()
    {
        return $this->callAction('bazarliste', $this->arguments);
    }
}
