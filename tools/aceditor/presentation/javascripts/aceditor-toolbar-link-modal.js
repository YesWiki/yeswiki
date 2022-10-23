export function openAceditorToolbarLinkModal($button, aceditor) {
  /* get pageList */
  if (!pagelist){
    var pagelist = [];
    $.ajax({
      url: wiki.url("?root/json", {demand: "pages"}), // keep ? because standart http rewrite waits for CamelCase and 'root' is not
      async:true,
      type: 'GET',
      cache: true,
      success: function(result){
        pagelist = [];
        for (var key in result) {
          let pageTag = result[key].tag;
          if (pageTag){
            pagelist.push(pageTag);
          }
        }
        // remove previous typeahead and refresh source
        $('#wikiurl-page-list-input').typeahead('destroy');
        $('#wikiurl-page-list-input').typeahead({ source: pagelist, items: 5});
      },
    });
  }
  /* create modal */
  $("body").append(
    `
  <div class="modal fade" id="YesWikiLinkModal">
  <div class="modal-dialog">
  <div class="modal-content">
  <div class="modal-header">
  <button type="button" class="close" data-dismiss="modal">&times;</button>
  <h3>${$button.attr("title")}</h3>
  </div>
  <div class="modal-body">
  <form id="form-link">
  <div class="control-group form-group internal-link">
  <label class="control-label">${wiki.lang['ACEDITOR_LINK_PAGE_NAME']}</label>
  <div class="controls">
  <input id="wikiurl-page-list-input" class="form-control" type="text" autocomplete="off" value=""
      name="wikiurl" data-provide="typeahead" data-items="5" data-source='${JSON.stringify(pagelist)}'>
  <span class="link-error help-block text-danger hidden">${wiki.lang['ACEDITOR_LINK_ERROR']}</span>
  <span class="help-block">${wiki.lang['ACEDITOR_LINK_HINT_NEW_PAGE_NAME']}</span>
  </div>
  </div>
  <div class="control-group form-group">
  <label class="control-label">${wiki.lang['ACEDITOR_LINK_TEXT']}</label>
  <div class="controls">
  <input class="form-control" type="text" name="text-url" value="${aceditor.getSelectedText()}">
  </div>
  </div>
  <select class="form-control link-options" style="margin: 1.5rem 0 2rem 0;">
  <option value="link">${wiki.lang['ACEDITOR_LINK_OPEN_IN_CURRENT_TAB']}</option>
  <option value="button" selected>${wiki.lang['ACEDITOR_LINK_OPEN_IN_CURRENT_TAB_ACTION_SYNTAX']}</option>
  <option value="ext">${wiki.lang['ACEDITOR_LINK_OPEN_IN_NEW_TAB']}</option>
  <option value="modal">${wiki.lang['ACEDITOR_LINK_OPEN_IN_MODAL']}</option>
  </select>
  </form>
  </div>
  <div class="modal-footer">
  <a href="#" class="btn btn-default" data-dismiss="modal">${wiki.lang['ACEDITOR_LINK_CANCEL']}</a>
  <a href="#" class="btn btn-primary btn-insert" data-dismiss="modal">${wiki.lang['ACEDITOR_LINK_INSERT']}</a>
  </div>
  </div>
  </div>
  </div>`
  );

  var $linkmodal = $("#YesWikiLinkModal");

  $(".btn-insert").on("click", (e) => {
    var $inputUrl = $linkmodal.find('[name="wikiurl"]')
    var wikiurl = $inputUrl.val() || ""

    // Replace spaces by -
    wikiurl = wikiurl.replace(/\s+/g, '-')
    $inputUrl.val(wikiurl)

    // Validate page name or url
    var isUrl = /^https?:\/\//.test(wikiurl)
    // We do not allow "." on purpose, even if it's part of WN_PAGE_TAG regular expression
    // because we want inputs like "yeswiki.net" to be interpreted as URL and not page names
    var haveSpecialChars = /[{}|\.\\"'<>~:/?#[\]@!$&()*+,;=%]/.test(wikiurl)
    var validWikiUrl = wikiurl && (isUrl || !haveSpecialChars)
    if (!validWikiUrl) {
      e.stopImmediatePropagation()
      $linkmodal.find('.link-error').removeClass('hidden')
      return
    }

    // Create wiki code
    var text = $linkmodal.find('[name="text-url"]').val() || wikiurl
    let linkOption = $linkmodal.find('.link-options').val()
    if (linkOption == "link") {
      var replacement = '[[' + wikiurl + ' '+text+']]';
    } else {
      const klass = ({ ext: "new-window", modal: "modalbox" })[linkOption]
      const params = klass ? `class="${klass}" ` : ""
      var replacement = `{{button link="${wikiurl}" text="${text}" ${params}nobtn="1"}}`
    }
    aceditor.session.replace(aceditor.getSelectionRange(),replacement)
  });

  $linkmodal
    .modal({ show: true, keyboard: false })
    .on("hidden hidden.bs.modal", function() {
      $linkmodal.remove()
    });
}