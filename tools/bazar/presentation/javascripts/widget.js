// on recupere les donnees passées par les attributs data et les donnees facettes
var widgetdata = $('#widgetapp').data();
var datas = {};
$.extend( datas, widgetdata );
//$.extend( datas, facetteval );
$.extend( datas, facettetext );

var widgetapp = new Vue({
  el: '#widgetapp',
  data: datas,
  computed: {
    newiframeurl: function() {
      var facettelabel = [];
      this.checkedfacette.forEach(function(element, index) {
        facettelabel[index]= facettetext.facettetext[element];
      });
      return this.iframeurl +
        '&template=' + this.templatemodel +
        '&width=' + this.widthmodel +
        '&height=' + this.heightmodel +
        '&lat=' + this.latmodel +
        '&lon=' + this.lonmodel +
        '&markersize=' + this.markersizemodel +
        '&provider=' + this.providermodel +
        '&zoom=' + this.zoommodel +
        '&groups=' + this.checkedfacette.join(',') +
        '&titles=' + facettelabel.join(',')
    },
    wikiquery: function() {
      var facettelabel = [];
      this.checkedfacette.forEach(function(element, index) {
        facettelabel[index]= facettetext.facettetext[element];
      });
      return '{{bazarliste' +
        ' id="' + this.formid + '"' +
        ' template="' + this.templatemodel + '"' +
        ' width="' + this.widthmodel + '"' +
        ' height="' + this.heightmodel + '"' +
        ' lat="' + this.latmodel + '"' +
        ' lon="' + this.lonmodel + '"' +
        ' markersize="' + this.markersizemodel + '"' +
        ' provider="' + this.providermodel + '"' +
        ' zoom="' + this.zoommodel + '"' +
        ' groups="' + this.checkedfacette.join(',') + '"' +
        ' titles="' + facettelabel.join(',') + '"' +
        '}}'
    }
  },
  methods: {
    hideTooltip: function(){
      var iterator = Object.keys(this.show_tooltip);
      for(i = 0; i < iterator.length; ++i){
          this.show_tooltip[iterator[i]] = false;
      }
    },
    showTooltip: function(id){
      this.show_tooltip[id] = true;
    }
  }
})
