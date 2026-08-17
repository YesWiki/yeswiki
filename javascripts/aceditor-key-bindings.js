export default function (aceContainer, toolbar) {
  aceContainer.addEventListener('keydown', (e) => {
    const keyCode = e.which
    const isCtrl = e.ctrlKey || e.metaKey
    const isAlt = e.altKey
    const isShift = e.shiftKey

    const click = (selector) => {
      const btn = toolbar.querySelector(selector)
      if (btn) btn.click()
    }

    if (isCtrl === true && isAlt === false) {
      if (keyCode === 49 && isShift === true) {
        click('.aceditor-btn-title1')
        e.preventDefault()
      } else if (keyCode === 50 && isShift === true) {
        click('.aceditor-btn-title2')
        e.preventDefault()
      } else if (keyCode === 51 && isShift === true) {
        click('.aceditor-btn-title3')
        e.preventDefault()
      } else if (keyCode === 52 && isShift === true) {
        click('.aceditor-btn-title4')
        e.preventDefault()
      } else if (keyCode === 53 && isShift === true) {
        click('.aceditor-btn-title5')
        e.preventDefault()
      } else if (keyCode === 66 && isShift === false) {
        click('.aceditor-btn-bold')
        e.preventDefault()
      } else if (keyCode === 73 && isShift === false) {
        click('.aceditor-btn-italic')
        e.preventDefault()
      } else if (keyCode === 85 && isShift === false) {
        click('.aceditor-btn-underline')
        e.preventDefault()
      } else if (keyCode === 89) {
        click('.aceditor-btn-strike')
        e.preventDefault()
      } else if (keyCode === 83 && isShift === false) {
        click('.aceditor-btn-save')
        e.preventDefault()
      } else if (keyCode === 58 && isShift === false) {
        click('.aceditor-btn-comment')
        e.preventDefault()
      }
    } else if (isCtrl === false && isAlt === true) {
      if (keyCode === 85 && isShift === false) {
        click('.aceditor-btn-underline')
        e.preventDefault()
      }
    }
  })
}
