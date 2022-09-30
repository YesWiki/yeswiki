export default function(aceeditor) {
  // ---- KEY BINDING --------
  let isCtrl = false
  let isAlt = false
  let isShift = false

  aceeditor.keyup((e) => {
    if (e.ctrlKey || e.metaKey) {
      isCtrl = false
    }
    if (e.altKey) {
      isAlt = false
    }
    if (e.shiftKey) {
      isShift = false
    }
  })

  aceeditor.keydown((e) => {
    const keyCode = e.which
    if (e.ctrlKey || e.metaKey) {
      isCtrl = true
    } else {
      isCtrl = false
    }
    if (e.altKey) {
      isAlt = true
    } else {
      isAlt = false
    }
    if (e.shiftKey) {
      isShift = true
    } else {
      isShift = false
    }
    let currentACE = $('pre.ace_editor.ace_focus')
    if (currentACE.length == 0) {
      currentACE = $('pre.ace_editor').first()
    }
    const currentToolbar = $(currentACE).closest('.ace-editor-container').siblings('.aceditor-toolbar').first()
    if (isCtrl === true && isAlt === false) {
      // title 1
      if (keyCode == 49 && isShift === true) {
        $(currentToolbar).find('.aceditor-btn-title1').mousedown(); e.preventDefault()
      }
      // title 2
      else if (keyCode == 50 && isShift === true) {
        $(currentToolbar).find('.aceditor-btn-title2').mousedown(); e.preventDefault()
      }
      // title 3
      else if (keyCode == 51 && isShift === true) {
        $(currentToolbar).find('.aceditor-btn-title3').mousedown(); e.preventDefault()
      }
      // title 4
      else if (keyCode == 52 && isShift === true) {
        $(currentToolbar).find('.aceditor-btn-title4').mousedown(); e.preventDefault()
      }
      // title 5
      else if (keyCode == 53 && isShift === true) {
        $(currentToolbar).find('.aceditor-btn-title5').mousedown(); e.preventDefault()
      }
      // bold
      else if (keyCode == 66 && isShift === false) {
        $(currentToolbar).find('.aceditor-btn-bold').mousedown(); e.preventDefault()
      }
      // italic
      else if (keyCode == 73 && isShift === false) {
        $(currentToolbar).find('.aceditor-btn-italic').mousedown(); e.preventDefault()
      }
      // underline
      else if (keyCode == 85 && isShift === false) {
        $(currentToolbar).find('.aceditor-btn-underline').mousedown(); e.preventDefault()
      }
      // strike
      else if (keyCode == 89) {
        $(currentToolbar).find('.aceditor-btn-strike').mousedown(); e.preventDefault()
      }
      // save page
      else if (keyCode == 83 && isShift === false) {
        $(currentToolbar).find('.aceditor-btn-save').click(); e.preventDefault()
      }
      // comment key ':'
      else if (keyCode == 58 && isShift === false) {
        $(currentToolbar).find('.aceditor-btn-comment').mousedown(); e.preventDefault()
      }
    } else if (isCtrl === false && isAlt === true) {
      if (keyCode == 85 && isShift === false) {
        $(currentToolbar).find('.aceditor-btn-underline').mousedown(); e.preventDefault()
      }
    }
  })
}
