ywInitEach('body', (body) => {
  body.addEventListener('click', (e) => {
    const submitBtn = e.target.closest('.mail-submit')
    if (!submitBtn) return

    const form = submitBtn.closest('.ajax-mail-form')
    if (!form) return

    e.preventDefault()
    e.stopPropagation()

    form.querySelectorAll('.help-block').forEach((el) => el.remove())

    let atLeastOneFieldNotValid = false
    let atLeastOneMailFieldNotValid = false
    let firstErrorGroup = null

    form
      .querySelectorAll('input[required], textarea[required]')
      .forEach((field) => {
        const group = field.closest('.yw-form-group')
        if (field.value.length === 0 || field.value === '0') {
          atLeastOneFieldNotValid = true
          if (group) {
            group.classList.add('has-error')
            if (!firstErrorGroup) firstErrorGroup = group
            const helpBlock = document.createElement('span')
            helpBlock.className = 'help-block'
            helpBlock.textContent = _t('CONTACT_REQUIRED_FIELD')
            group.appendChild(helpBlock)
          }
        } else if (group) {
          group.classList.remove('has-error')
        }
      })

    form.querySelectorAll('input[type=email]').forEach((field) => {
      const reg = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/
      const address = field.value
      const group = field.closest('.yw-form-group')
      const isEmptyOptional =
        address === '' && field.getAttribute('required') !== 'required'
      if (reg.test(address) === false && !isEmptyOptional) {
        atLeastOneMailFieldNotValid = true
        if (group) {
          group.classList.add('has-error')
          if (!firstErrorGroup) firstErrorGroup = group
          const helpBlock = document.createElement('span')
          helpBlock.className = 'help-block'
          helpBlock.textContent = _t('CONTACT_EMAIL_NOT_VALID')
          group.appendChild(helpBlock)
        }
      } else if (group) {
        group.classList.remove('has-error')
      }
    })

    if (atLeastOneFieldNotValid || atLeastOneMailFieldNotValid) {
      if (firstErrorGroup) {
        const top =
          firstErrorGroup.getBoundingClientRect().top + window.scrollY - 80
        window.scrollTo({ top, behavior: 'smooth' })
      }
      return
    }

    const formData = new FormData(form)
    formData.set('pageTag', form.dataset.pageTag || wiki.pageTag)
    if (form.dataset.field) {
      formData.set('field', form.dataset.field)
    }

    fetch(wiki.url('api/contact/mail'), { method: 'POST', body: formData })
      .then((response) => response.json())
      .then((result) => {
        form.querySelectorAll('.yw-alert').forEach((el) => el.remove())
        const alert = document.createElement('div')
        alert.className = `yw-alert yw-alert--${result.type}`
        alert.innerHTML = result.message
        form.prepend(alert)

        if (result.type === 'success') {
          form.reset()
        }
      })
  })
})
