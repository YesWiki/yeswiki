(function( $ ){

  $.fn.pad = function( options ) {
    var settings = {
      'host'		 : 'http://pad.site-coop.net',
      'baseUrl'		 : '/p/',
      'showControls'     : true,
      'showChat'	 : false,
      'showLineNumbers'  : true,
      'userName'	 : 'unnamed',
      'useMonospaceFont' : false,
      'noColors'   : 'false'
    };

    // This writes a new frame if required
    if ( !options.getContents )
    {
      if ( options ) 
      { 
        $.extend( settings, options );
      }
      var epframe = this.attr('id');
      var iFrameLink = '<iframe id="epframe'+epframe+'" src="'+settings.host+settings.baseUrl+settings.padId+'?showControls='+settings.showControls+'&showChat='+settings.showChat+'&showLineNumbers='+settings.showLineNumbers+'&useMonospaceFont='+settings.useMonospaceFont+'&userName=' + settings.userName + '&noColors=' + settings.noColors + '"></iframe>';
      // console.log(iFrameLink);
      this.html(iFrameLink);
    }

    // This reads the etherpad contents if required
    if ( options.getContents )
    {
      // Specify the target Div
      var targetDiv = options.getContents;

      // Get the frame properties and provide us with an export path
      var frameID = this.attr('id');
      var epframe = "epframe"+frameID;
      var frameUrl = document.getElementById(epframe).src;
      if (frameUrl.indexOf("?")>-1){
        frameUrl = frameUrl.substr(0,frameUrl.indexOf("?"));
      }
      var contentsUrl = frameUrl + "/export/html";

      // perform an ajax call on contentsUrl and write it to the parent
      $.get(contentsUrl, function(data) {
      $('#'+targetDiv).html(data);
      });
    }


  };

        var mypad=($('#wikipad').attr('title')); // Nom du pad recupere depuis  le titre
        var myname=($('#wikipad').attr('class')); // Nom du pad recupere depuis  le titre
     $('#wikipad').pad({'padId':mypad,'showChat':'true','userName':myname })
     $('#epframewikipad').width(900);
     $('#epframewikipad').height(2000);
       


})( jQuery );
