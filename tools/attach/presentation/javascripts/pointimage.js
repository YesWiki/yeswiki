$(document).ready(function () {
	var labelcancel = $(".modal-pointimage .btn-close").text();
	var labeladdpoint = $(".modal-pointimage .modal-title").text();
	var $pointimagecontainers = $(".pointimage-container");
	var $popovers = $(".img-marker");

	$pointimagecontainers.each(function( index ) {
		if ($(this).data('readonly') === false) {
			$(this).find('.pointimage-image').append('<a class="btn btn-xs btn-primary btn-add-point" href="#"><i class="fa fa-plus icon-plus"></i> '+labeladdpoint+'</a><a class="pull-right btn btn-xs btn-default btn-edit-points" href="'+$(this).data('pagetag')+'/edit"><i class="fa fa-pencil-alt icon-edit"></i></a>');
		}
	});

	$popovers.popover({trigger: 'focus', html:'true', placement:'top', delay: { show: 0, hide: 0 }});
	$popovers.on( "click", function() { return false; });

	$popovers.on('shown.bs.popover', function () {
		var $popup = $(this);
		// the following part is to activate <a> tags because the off focus event deletes the popup before the mouseup
		// and a link is activated on a mouseup event (if it is already existing)
		var $links = $popup.next('.popover').find('.popover-content a');
		$links.each(function(){
			$(this).off("mousedown"); // remove previous onmousedown events ;
			$(this).on("mousedown", function(event){ 
				var target = $(this).attr('target');
				if ((!target || target.length ==0 ) && !$(this).hasClass('modalbox') && !$(this).hasClass('new-window')){
					window.location = $(this).attr('href');
				} else if (target == 'blank' || target == '_blank' || $(this).hasClass('modalbox') || $(this).hasClass('new-window')) {
					// modal is opened in new tab because openModal is difficult to reach in this context
					window.open($(this).attr('href'));
				} else {
					// console.log(event.target);
				}
			});
		});
	//     $popup.next('.popover').find('.popover-title').prepend('<button type="button" class="btn-close-popover pull-right close">&times;</button>'); 
	//     $popup.next('.popover').find('.btn-close-popover').click(function (e) {
	//         $popup.popover('hide');
	//     });
	});
	$pointimagecontainers.on( "click", ".btn-close-popover",  function() {
		$(this).parents('.popover').prev('.img-marker').popover('hide');
		return false;
	});

	$pointimagecontainers.on( "click", ".btn-add-point",  function() {
		$(this).removeClass('btn-add-point').removeClass('btn-primary').addClass('btn-cancel').addClass('btn-danger').text(labelcancel);
		$(this).parents('.pointimage-container').find("img").css('cursor', 'crosshair');
		return false;
	})
	.on( "click", ".btn-cancel", function() {
		$(this).removeClass('btn-cancel').removeClass('btn-danger').addClass('btn-add-point').addClass('btn-primary').html('<i class="fa fa-plus icon-plus"></i> '+labeladdpoint+'</a>');
		$(this).parents('.pointimage-container').find("img").css('cursor', 'default');
		return false;
	})
	.on( "click", function(e) {
		if ($(this).find("img").css('cursor') === 'crosshair') {
			var $this=$(this);
			$this.find("img").css('cursor', 'default');
			var Offset = $this.offset();
			var relX = Math.round(e.pageX - Offset.left);
			var relY = Math.round(e.pageY - Offset.top);
			var data = $this.data();
			var $formpointimage = $('.form-pointimage');
			$.each(data.markerscolor, function(index,value) {
				$formpointimage.find('.markers-choice').append('<label class="radio-inline"><input type="radio" name="color" value="'+value+'"><a class="img-marker" href="#" style="display:inline-block;position:relative;background:'+value+';width:'+data.markersize+'px;height:'+data.markersize+'px;"></a> '+data.markerslabel[index]+'&nbsp;</label>');
			});
			$formpointimage.find('input[type=radio][name=color]:first').attr('checked',true);
			$formpointimage.append('<input type="hidden" name="image_x" value="'+relX+'" /><input type="hidden" name="image_y" value="'+relY+'" /><input type="hidden" name="pagetag" value="'+data.pagetag+'" />');
			$this.find('.btn-cancel').removeClass('btn-cancel').removeClass('btn-danger').addClass('btn-add-point').addClass('btn-primary').html('<i class="fa fa-plus icon-plus"></i> '+labeladdpoint+'</a>');
			$this.css('cursor', 'default');
			$('.modal-pointimage').modal('show');
			return false;
		}
	});
	$('.modal-pointimage').on('hide.bs.modal', function (e) {
		$(this).find('.markers-choice').empty();
		$('.form-pointimage')[0].reset();
	});
});
