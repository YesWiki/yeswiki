// replace full calendar icons
$(document).ready(function() {
  $('.fc-prev-button').html('<span class="fa fa-chevron-left"></span>')
                      .prependTo('.fc-toolbar.fc-header-toolbar').removeClass('btn btn-default')
  $('.fc-next-button').html('<span class="fa fa-chevron-right"></span>')
                      .appendTo('.fc-toolbar.fc-header-toolbar').removeClass('btn btn-default')
})