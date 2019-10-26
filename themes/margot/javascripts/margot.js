$(document).ready(function() {
  // replace full calendar icons
  $('.fc-prev-button').html('<span class="fa fa-chevron-left"></span>')
                      .prependTo('.fc-toolbar.fc-header-toolbar').removeClass('btn btn-default')
  $('.fc-next-button').html('<span class="fa fa-chevron-right"></span>')
                      .appendTo('.fc-toolbar.fc-header-toolbar').removeClass('btn btn-default')

  $('.form-control').each(function() {
    var parent = $(this).closest('.form-group');
    parent.addClass($(this).prop("tagName").toLowerCase());
    parent.addClass($(this).attr("type"));
    if ($(this).hasClass('wiki-textarea')) {
      parent.addClass('wiki-textarea');
      parent.find('.control-label').prependTo(parent.find('.aceditor-toolbar'));
    }
  })
  $('.controls .radio, .controls .checkbox').each(function() {
    var parent = $(this).closest('.form-group');
    parent.addClass('form-control wrapper');
  })

  $('form button[type=submit]').removeClass('btn-success').addClass('btn-primary')
  $('form button[type=submit] ~ .btn-danger').removeClass('btn-danger').addClass('btn-default')

  $('.tooltip_aide').each(function() {
    var tooltip = $(this).data('original-title');
    var newImage = $("<span class='form-help fa fa-question-circle' title='" + tooltip +"'></span>")
    $(this).parent().append(newImage)
    // newImage.tooltip();
    $(this).remove();
  })

  $('.BAZ_menu .nav-pills').removeClass('nav-pills').addClass('nav-tabs');
})