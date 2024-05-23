// function to render help for tabs and tabchange
export default {
  cache: {},
  holders: {},
  ids: {},
  formFields: {},
  getFormField(fieldId) {
    if (!this.formFields.hasOwnProperty(fieldId)) {
      const formField = $(`.field-${fieldId}`).closest('li.form-field')
      const newFormField = {}
      newFormField[fieldId] = (formField.length == 0) ? false : formField
      this.formFields = { ...this.formFields, ...newFormField }
    }
    return this.formFields[fieldId]
  },
  getFormGroup(field, formGroupName) {
    const holder = this.getHolder(field)
    if (holder) {
      const formGroup = holder.find(`.${formGroupName}-wrap`)
      if (typeof formGroup !== undefined && formGroup.length > 0) {
        return formGroup
      }
    }
    return null
  },
  getHolder(field) {
    const fieldId = field.id
    if (!this.holders.hasOwnProperty(fieldId)) {
      const formField = this.getFormField(fieldId)
      const newHolder = {}
      const newId = {}
      let id = false
      if (formField) {
        id = $(formField).attr('id')
        const anchor = $(`#${id}-holder`)
        if (typeof anchor === 'undefined'
              || anchor.length == 0) {
          newHolder[fieldId] = false
          newId[fieldId] = false
        } else {
          newHolder[fieldId] = anchor.first()
          newId[fieldId] = id
        }
      } else {
        newHolder[fieldId] = false
        newId[fieldId] = false
      }
      this.holders = { ...this.holders, ...newHolder }
      this.ids = { ...this.ids, ...newId }
    }
    return this.holders[fieldId]
  },
  getId(field) {
    const fieldId = field.id
    if (!this.ids.hasOwnProperty(fieldId)) {
      this.getHolder(field)
    }
    return this.ids[fieldId]
  },
  initializeField(field) {
    if (!field.hasClass('initialized')
      || field.data('savedId') != field.prop('id')) {
      field.addClass('initialized')
      field.data('savedId', field.prop('id'))
      field.find('.initialized').each(function() {
        $(this).removeClass('initialized')
      })
    }
  },
  prependHint(field, message) {
    const holder = this.getHolder(field)
    if (holder) {
      if (!holder.hasClass('hint-already-defined')) {
        const formElements = holder.find('.form-elements').first()
        const helpMsg = $('<div/>')
          .addClass('custom-hint')
          .append(message)
        formElements.prepend(helpMsg)
        holder.addClass('hint-already-defined')
      }
    }
  },
  prependHTMLBeforeGroup(field, formGroupName, html) {
    const formGroup = this.getFormGroup(field, formGroupName)
    if (formGroup !== null) {
      if (!formGroup.hasClass('prepended-html-already-defined')) {
        formGroup.before(html)
        formGroup.addClass('prepended-html-already-defined')
      }
    }
  },
  defineLabelHintForGroup(field, formGroupName, message) {
    const formGroup = this.getFormGroup(field, formGroupName)
    if (formGroup !== null) {
      const label = formGroup.find('label').first()
      if (!label.hasClass('label-hint-already-defined')) {
        label.append(' ')
        label.append($('<i/>')
          .addClass('fa fa-question-circle')
          .attr('title', message)
          .tooltip())
        label.addClass('label-hint-already-defined')
      }
    }
  }
}
