<?php
/*

Copyright (c) 2016, Florian Schmitt <mrflos@gmail.com>
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}
if (isset($_GET['id'])) {
    echo $this->Header();
    echo '<h1>Partager les résultats par widget HTML (code embed)</h1>'."\n";
    $params = getAllParameters($this);

    // chaine de recherche
    $q = '';
    if (isset($_GET['q']) and !empty($_GET['q'])) {
        $q = $_GET['q'];
    }

    // tableau des fiches correspondantes aux critères
    if (is_array($params['idtypeannonce'])) {
        $results = array();
        foreach ($params['idtypeannonce'] as $formid) {
            $results = array_merge(
                $results,
                baz_requete_recherche_fiches($params['query'], 'alphabetique', $formid, '', 1, '', '', true, $q)
            );
        }
    } else {
        $results = baz_requete_recherche_fiches($params['query'], 'alphabetique', $params['idtypeannonce'], '', 1, '', '', true, $q);
    }
    $params['groups'][0] = 'all';
    $results = searchResultstoArray($results, $params);
    $tabfacette = scanAllFacettable($results, $params, '', true);
  var_dump($tabfacette);
    $urlparams = 'id='.$_GET['id']
      .(isset($_GET['query']) ? '&query='.$_GET['query'] : '')
      .(!empty($q) ? '&q='.$q : '')
      .'&width='.$params['width']
      .'&height='.$params['height'];
?>
<div id="widgetapp">
  <div class="row"> <!-- start of grid -->
    <div class="col-md-3"> <!-- start of col -->
      <div class="panel panel-default">
        <div class="panel-heading">Type de visualisation</div>
        <div class="panel-body">
          <select v-model="templateModel" class="form-control">
            <option value="liste_liens.tpl.html">Liste de lien simple</option>
            <option value="liste_accordeon.tpl.html">Liste accordéons</option>
            <option value="carto.tpl.html">Carte avec fiches résumées</option>
            <option value="map.tpl.html">Carte avec fiches entières</option>
          </select>
        </div>
      </div>
      <template v-if="templateModel === 'map.tpl.html' || templateModel === 'carto.tpl.html'">
        <div class="panel panel-default">
          <div class="panel-heading">Options pour la carte</div>
          <div class="panel-body">
            <div class="form-group">
              <label>Fond de carte</label>
              <select v-model="providerModel" class="form-control">
                <option value="OpenStreetMap.Mapnik">OpenStreetMap.Mapnik</option>
                <option value="OpenStreetMap.BlackAndWhite">OpenStreetMap.BlackAndWhite</option>
                <option value="OpenStreetMap.DE">OpenStreetMap.DE</option>
                <option value="OpenStreetMap.France">OpenStreetMap.France</option>
                <option value="OpenStreetMap.HOT">OpenStreetMap.HOT</option>
                <option value="OpenTopoMap">OpenTopoMap</option>
                <option value="Thunderforest.OpenCycleMap">Thunderforest.OpenCycleMap</option>
                <option value="Thunderforest.Transport">Thunderforest.Transport</option>
                <option value="Thunderforest.TransportDark">Thunderforest.TransportDark</option>
                <option value="Thunderforest.SpinalMap">Thunderforest.SpinalMap</option>
                <option value="Thunderforest.Landscape">Thunderforest.Landscape</option>
                <option value="Thunderforest.Outdoors">Thunderforest.Outdoors</option>
                <option value="Thunderforest.Pioneer">Thunderforest.Pioneer</option>
                <option value="OpenMapSurfer.Roads">OpenMapSurfer.Roads</option>
                <option value="OpenMapSurfer.Grayscale">OpenMapSurfer.Grayscale</option>
                <option value="Hydda.Full">Hydda.Full</option>
                <option value="Hydda.Base">Hydda.Base</option>
                <option value="MapBox">MapBox</option>
                <option value="Stamen.Toner">Stamen.Toner</option>
                <option value="Stamen.TonerBackground">Stamen.TonerBackground</option>
                <option value="Stamen.TonerLite">Stamen.TonerLite</option>
                <option value="Stamen.Watercolor">Stamen.Watercolor</option>
                <option value="Stamen.Terrain">Stamen.Terrain</option>
                <option value="Stamen.TerrainBackground">Stamen.TerrainBackground</option>
                <option value="Stamen.TopOSMRelief">Stamen.TopOSMRelief</option>
                <option value="Esri.WorldStreetMap">Esri.WorldStreetMap</option>
                <option value="Esri.DeLorme">Esri.DeLorme</option>
                <option value="Esri.WorldTopoMap">Esri.WorldTopoMap</option>
                <option value="Esri.WorldImagery">Esri.WorldImagery</option>
                <option value="Esri.WorldTerrain">Esri.WorldTerrain</option>
                <option value="Esri.WorldShadedRelief">Esri.WorldShadedRelief</option>
                <option value="Esri.WorldPhysical">Esri.WorldPhysical</option>
                <option value="Esri.OceanBasemap">Esri.OceanBasemap</option>
                <option value="Esri.NatGeoWorldMap">Esri.NatGeoWorldMap</option>
                <option value="Esri.WorldGrayCanvas">Esri.WorldGrayCanvas</option>
                <option value="HERE.normalDay">HERE.normalDay</option>
                <option value="HERE.basicMap">HERE.basicMap</option>
                <option value="HERE.hybridDay">HERE.hybridDay</option>
                <option value="FreeMapSK">FreeMapSK</option>
                <option value="MtbMap">MtbMap</option>
                <option value="CartoDB.Positron">CartoDB.Positron</option>
                <option value="CartoDB.PositronNoLabels">CartoDB.PositronNoLabels</option>
                <option value="CartoDB.PositronOnlyLabels">CartoDB.PositronOnlyLabels</option>
                <option value="CartoDB.DarkMatter">CartoDB.DarkMatter</option>
                <option value="CartoDB.DarkMatterNoLabels">CartoDB.DarkMatterNoLabels</option>
                <option value="CartoDB.DarkMatterOnlyLabels">CartoDB.DarkMatterOnlyLabels</option>
                <option value="HikeBike.HikeBike">HikeBike.HikeBike</option>
                <option value="HikeBike.HillShading">HikeBike.HillShading</option>
                <option value="BasemapAT.basemap">BasemapAT.basemap</option>
                <option value="BasemapAT.grau">BasemapAT.grau</option>
                <option value="BasemapAT.overlay">BasemapAT.overlay</option>
                <option value="BasemapAT.highdpi">BasemapAT.highdpi</option>
                <option value="BasemapAT.orthofoto">BasemapAT.orthofoto</option>
                <option value="NASAGIBS.ModisTerraTrueColorCR">NASAGIBS.ModisTerraTrueColorCR</option>
                <option value="NASAGIBS.ModisTerraBands367CR">NASAGIBS.ModisTerraBands367CR</option>
                <option value="NASAGIBS.ViirsEarthAtNight2012">NASAGIBS.ViirsEarthAtNight2012</option>
              </select>
            </div>
            <div class="form-group">
              <label>Taille des marqueurs</label>
              <select v-model="markersizeModel" class="form-control">
                <option value="small">Petite</option>
                <option value="big">Grande</option>
              </select>
            </div>
          </div>
        </div>
      </template>
      <template v-if="templateModel === 'map.tpl.html' || templateModel === 'carto.tpl.html'">
        <div class="panel panel-default">
          <div class="panel-heading">Marqueurs</div>
          <div class="panel-body">
            <div class="form-group">
              <label>Champ associé</label>
              <select v-model="markerfieldModel" class="form-control">
                <option value="<?php echo _t('BAZ_CHOISIR'); ?>"><?php echo _t('BAZ_CHOISIR'); ?></option>
                    <?php foreach ($tabfacette as $key => $value) : ?>
                    <option value="<?php echo $key; ?>">
                            <?php echo $key; ?>
                    </option>
                    <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </template>
      <template v-if="templateModel !== 'map.tpl.html' && templateModel !== 'carto.tpl.html'">
        <div class="panel panel-default">
          <div class="panel-heading">Couleurs</div>
          <div class="panel-body">
            <div class="form-group">
              <label>Champ associé</label>
              <select v-model="colorfieldModel" class="form-control">
                <option value="<?php echo _t('BAZ_CHOISIR'); ?>"><?php echo _t('BAZ_CHOISIR'); ?></option>
                  <?php foreach ($tabfacette as $key => $value) : ?>
                    <option value="<?php echo $key; ?>">
                          <?php echo $key; ?>
                    </option>
                  <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </template>
      <div class="panel panel-default">
        <div class="panel-heading">Icones</div>
        <div class="panel-body">
          <div class="form-group">
            <label>Champ associé</label>
            <select v-model="iconfieldModel" class="form-control">
              <option value="<?php echo _t('BAZ_CHOISIR'); ?>"><?php echo _t('BAZ_CHOISIR'); ?></option>
              <?php foreach ($tabfacette as $key => $value) : ?>
                <option value="<?php echo $key; ?>">
                      <?php echo $key; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="panel panel-default">
        <div class="panel-heading">Ajouter des facettes</div>
        <div class="panel-body" style="height:300px;overflow-y:auto;">
          <ul class="list-group">
           <!-- v-sortable="{group: 'facettes'}" -->
            <?php foreach ($tabfacette as $key => $value) : ?>
            <li class="list-group-item" data-id="<?php echo $key; ?>">
              <div class="checkbox">
                <label>
                  <input type="checkbox" value="<?php echo $key; ?>" v-model="checkedFacette">
                    <?php echo $value['source']; ?>
                    <!-- <i class="handle pull-right glyphicon glyphicon-resize-vertical"></i> -->
                </label>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
          <pre>{{ checkedFacette }}</pre>
        </div>
      </div>
    </div> <!-- end of col -->
    <div class="col-md-9"> <!-- start of col -->
      <div class="panel panel-default">
        <div class="panel-heading">Prévisualisation</div>
        <div class="panel-body">
          <iframe class="iframe-preview" style="border:1px dotted #ccc;" width="<?php echo $params['width']; ?>" height="<?php echo $params['height']; ?>" frameborder="0" v-bind:src="iframeUrl + '&template=' + templateModel+ '&provider=' + providerModel + '&groups=' + checkedFacette.join(',')"></iframe>

          <strong>Code embed a copier coller dans votre site</strong>
          <pre><code>&lt;iframe width="<?php echo $params['width']; ?>" height="<?php echo $params['height']; ?>" frameborder="0" allowfullscreen="true" src="<?php echo $this->href('iframe', '', $urlparams); ?>&template={{ templateModel }}&provider={{ providerModel }}&groups={{ checkedFacette.join(',') }}"&gt;&lt;/iframe&gt;</code></pre>

          <strong>Code action wiki a copier coller dans une page de ce site</strong>
          <pre><code>&#123;\&#123;bazarliste id="<?php echo $_GET['id']; ?>" width="<?php echo $params['width']; ?>" height="<?php echo $params['height']; ?>" template="{{ templateModel }}" provider="{{ providerModel }}" groups="{{ checkedFacette.join(',') }}"&#125;\&#125;</code></pre>
        </div>
      </div>
    </div> <!-- end of col -->
  </div> <!-- end of row -->
</div> <!-- /#widgetapp -->
<?php
    $this->addJavascriptFile('tools/bazar/libs/vendor/vue.min.js');
    //$this->addJavascriptFile('tools/bazar/libs/vendor/Sortable.min.js');
    //$this->addJavascriptFile('tools/bazar/libs/vendor/vue-sortable.js');
    $this->addJavascript('
    var widgetapp = new Vue({
      el: \'#widgetapp\',
      data: {
        templateModel: \''.$params['template'].'\',
        providerModel: \''.$params['provider'].'\',
        iframeUrl: \''.$this->href('iframe', '', $urlparams, false).'\',
        checkedFacette: [],
        markerfieldModel: "'._t('BAZ_CHOISIR').'",
        colorfieldModel: "'._t('BAZ_CHOISIR').'",
        iconfieldModel: "'._t('BAZ_CHOISIR').'",
        markersizeModel: "big",
      },
      methods: {
        addTodo: function () {
        },
        removeTodo: function (index) {
        }

      }
    })
    // Vue.filter(\'implode\', function (value) {
    //   return value.join(\',\');
    // })
');
    echo $this->Footer();
    exit;
}
