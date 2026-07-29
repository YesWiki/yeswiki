// javascripts/pointimage.js -- vanilla-JS replacement for the jQuery+Bootstrap
// {{pointimage}} widget (ticket 17, relocated from
// tools/attach/presentation/javascripts/pointimage.js). Modal *closing* (dismiss
// button, backdrop click, Escape) is handled generically by yw-core.js's
// `.yw-modal`/data-yw-dismiss="modal" convention; this file only opens the modal
// (triggered by clicking a spot on the image, not a static button) and implements
// the marker popovers, for which no vanilla equivalent existed yet.
(function() {
  const modal = document.querySelector('.modal-pointimage')
  const labelCancel = modal ? modal.querySelector('.btn-close').textContent.trim() : ''
  const labelAddPoint = modal ? modal.querySelector('.yw-modal__title').textContent.trim() : ''
  const popovers = new WeakMap()

  function escHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
  }

  function closeAllPopovers(except) {
    document.querySelectorAll('.yw-popover--open').forEach((popover) => {
      if (popover !== except) popover.classList.remove('yw-popover--open')
    })
  }

  document.querySelectorAll('.pointimage-container').forEach((container) => {
    if (container.getAttribute('data-readonly') === 'false') {
      const image = container.querySelector('.pointimage-image')
      if (image) {
        const editUrl = `${escHtml(container.getAttribute('data-pagetag') || '')}/edit`
        const addLabel = escHtml(labelAddPoint)
        image.insertAdjacentHTML('beforeend', `
          <a class="yw-btn yw-btn--sm yw-btn--primary btn-add-point" href="#">
            <svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#plus"/></svg> ${addLabel}
          </a>
          <a class="yw-btn yw-btn--sm yw-btn--default yw-pull-right btn-edit-points" href="${editUrl}">
            <svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#pencil"/></svg>
          </a>
        `)
      }
    }
  })

  // Popovers on marker links: build the bubble lazily on first focus/hover.
  document.querySelectorAll('.img-marker[data-yw-popover-title]').forEach((marker) => {
    marker.addEventListener('click', (e) => e.preventDefault())

    function openPopover() {
      let popover = popovers.get(marker)
      if (!popover) {
        popover = document.createElement('div')
        popover.className = 'yw-popover yw-popover--pointimage'
        const title = marker.getAttribute('data-yw-popover-title') || ''
        const content = marker.getAttribute('data-yw-popover-content') || ''
        popover.innerHTML = `
          <button type="button" class="yw-close btn-close-popover">&times;</button>
          <div class="yw-popover__title">${title}</div>
          <div class="yw-popover__content">${content}</div>
        `
        marker.insertAdjacentElement('afterend', popover)
        popovers.set(marker, popover)
        popover.querySelector('.btn-close-popover').addEventListener('click', () => {
          popover.classList.remove('yw-popover--open')
          marker.blur()
        })
      }
      closeAllPopovers(popover)
      popover.classList.add('yw-popover--open')
    }

    function closePopoverUnlessFocused() {
      const popover = popovers.get(marker)
      if (popover && document.activeElement !== marker) {
        popover.classList.remove('yw-popover--open')
      }
    }

    marker.addEventListener('focus', openPopover)
    marker.addEventListener('mouseenter', openPopover)
    marker.addEventListener('mouseleave', closePopoverUnlessFocused)
    marker.addEventListener('blur', () => {
      // Delay so a mousedown on a link inside the popover still registers
      // before the bubble disappears.
      setTimeout(closePopoverUnlessFocused, 150)
    })
  })

  // Add-marker workflow
  document.querySelectorAll('.pointimage-container').forEach((container) => {
    const image = container.querySelector('.pointimage-image')

    container.addEventListener('click', (e) => {
      const addBtn = e.target.closest('.btn-add-point')
      if (addBtn) {
        e.preventDefault()
        addBtn.classList.remove('btn-add-point', 'yw-btn--primary')
        addBtn.classList.add('btn-cancel', 'yw-btn--danger')
        addBtn.textContent = labelCancel
        if (image) image.style.cursor = 'crosshair'
        return
      }

      const cancelBtn = e.target.closest('.btn-cancel')
      if (cancelBtn) {
        e.preventDefault()
        cancelBtn.classList.remove('btn-cancel', 'yw-btn--danger')
        cancelBtn.classList.add('btn-add-point', 'yw-btn--primary')
        cancelBtn.innerHTML = `<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#plus"/></svg> ${escHtml(labelAddPoint)}`
        if (image) image.style.cursor = 'default'
        return
      }

      if (image && image.style.cursor === 'crosshair' && !e.target.closest('a')) {
        const rect = container.getBoundingClientRect()
        const relX = Math.round(e.pageX - (rect.left + window.scrollX))
        const relY = Math.round(e.pageY - (rect.top + window.scrollY))
        image.style.cursor = 'default'

        const colors = JSON.parse(container.getAttribute('data-markerscolor') || '["green"]')
        const labels = JSON.parse(container.getAttribute('data-markerslabel') || '[]')
        const markerSize = container.getAttribute('data-markersize') || '10'
        const pagetag = container.getAttribute('data-pagetag') || ''

        const form = document.querySelector('.form-pointimage')
        if (form) {
          const choices = form.querySelector('.markers-choice')
          if (choices) {
            choices.innerHTML = ''
            colors.forEach((value, index) => {
              const checked = index === 0 ? ' checked' : ''
              const label = escHtml(labels[index] || '')
              const safeValue = escHtml(value)
              const safeSize = escHtml(markerSize)
              const swatchStyle = `display:inline-block;position:relative;background:${safeValue};`
                + `width:${safeSize}px;height:${safeSize}px;`
              choices.insertAdjacentHTML('beforeend', `
                <label class="yw-radio-inline">
                  <input type="radio" name="color" value="${safeValue}"${checked}>
                  <span class="img-marker" style="${swatchStyle}"></span> ${label}&nbsp;
                </label>
              `)
            })
          }
          const hiddenFieldsSelector = 'input[name="image_x"], input[name="image_y"], '
            + 'input[name="pagetag"]'
          form.querySelectorAll(hiddenFieldsSelector).forEach((el) => el.remove())
          form.insertAdjacentHTML('beforeend', `
            <input type="hidden" name="image_x" value="${relX}" />
            <input type="hidden" name="image_y" value="${relY}" />
            <input type="hidden" name="pagetag" value="${escHtml(pagetag)}" />
          `)
        }

        const btnAddPoint = container.querySelector('.btn-cancel')
        if (btnAddPoint) {
          btnAddPoint.classList.remove('btn-cancel', 'yw-btn--danger')
          btnAddPoint.classList.add('btn-add-point', 'yw-btn--primary')
          btnAddPoint.innerHTML = `<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#plus"/></svg> ${escHtml(labelAddPoint)}`
        }

        if (modal) modal.classList.add('yw-modal--open')
      }
    })
  })

  // Reset the marker-choice form + colors whenever the modal closes (mirrors the
  // old 'hide.bs.modal' handler); yw-core.js owns the actual close logic.
  if (modal) {
    new MutationObserver(() => {
      if (!modal.classList.contains('yw-modal--open')) {
        const choices = modal.querySelector('.markers-choice')
        if (choices) choices.innerHTML = ''
        const form = modal.querySelector('.form-pointimage')
        if (form) form.reset()
      }
    }).observe(modal, { attributes: true, attributeFilter: ['class'] })
  }
}())
