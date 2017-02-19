// on recupere les donnees passées par les attributs data et les donnees facettes
var widgetdata = $('#widgetapp').data();
var datas = {};
$.extend( datas, widgetdata );
$.extend( datas, facetteval );

var widgetapp = new Vue({
  el: '#widgetapp',
  data: datas,
  computed: {
    newiframeurl: function() {
      return this.iframeurl +
        '&template=' + this.templatemodel +
        '&width=' + this.widthmodel +
        '&height=' + this.heightmodel +
        '&lat=' + this.latmodel +
        '&lon=' + this.lonmodel +
        '&markersize=' + this.markersizemodel +
        '&provider=' + this.providermodel +
        '&zoom=' + this.zoommodel +
        '&groups=' + this.checkedfacette.join(',')
    },
    wikiquery: function() {
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
        '}}'
    }
  },
  methods: {
    hideTooltip: function(id){
      // When a model is changed, the view will be automatically updated.
      this.show_tooltip[id] = false;
    },
    toggleTooltip: function(id){
      this.show_tooltip[id] = !this.show_tooltip[id];
    }
  }
})
