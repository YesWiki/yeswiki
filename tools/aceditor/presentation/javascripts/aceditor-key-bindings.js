export default function($aceContainer, $toolbar) {
  $aceContainer.keydown((e) => {
    const keyCode = e.which
    const isCtrl = e.ctrlKey || e.metaKey
    const isAlt = e.altKey
    const isShift = e.shiftKey

    if (isCtrl === true && isAlt === false) {
      // title 1
      if (keyCode == 49 && isShift === true) {
        $toolbar.find('.aceditor-btn-title1').trigger('click'); e.preventDefault()
      }
      // title 2
      else if (keyCode == 50 && isShift === true) {
        $toolbar.find('.aceditor-btn-title2').trigger('click'); e.preventDefault()
      }
      // title 3
      else if (keyCode == 51 && isShift === true) {
        $toolbar.find('.aceditor-btn-title3').trigger('click'); e.preventDefault()
      }
      // title 4
      else if (keyCode == 52 && isShift === true) {
        $toolbar.find('.aceditor-btn-title4').trigger('click'); e.preventDefault()
      }
      // title 5
      else if (keyCode == 53 && isShift === true) {
        $toolbar.find('.aceditor-btn-title5').trigger('click'); e.preventDefault()
      }
      // bold
      else if (keyCode == 66 && isShift === false) {
        $toolbar.find('.aceditor-btn-bold').trigger('click'); e.preventDefault()
      }
      // italic
      else if (keyCode == 73 && isShift === false) {
        $toolbar.find('.aceditor-btn-italic').trigger('click'); e.preventDefault()
      }
      // underline
      else if (keyCode == 85 && isShift === false) {
        $toolbar.find('.aceditor-btn-underline').trigger('click'); e.preventDefault()
      }
      // strike
      else if (keyCode == 89) {
        $toolbar.find('.aceditor-btn-strike').trigger('click'); e.preventDefault()
      }
      // save page
      else if (keyCode == 83 && isShift === false) {
        $toolbar.find('.aceditor-btn-save').trigger('click'); e.preventDefault()
      }
      // comment key ':'
      else if (keyCode == 58 && isShift === false) {
        $toolbar.find('.aceditor-btn-comment').trigger('click'); e.preventDefault()
      }
    } else if (isCtrl === false && isAlt === true) {
      if (keyCode == 85 && isShift === false) {
        $toolbar.find('.aceditor-btn-underline').trigger('click'); e.preventDefault()
      }
    }
  })
}
