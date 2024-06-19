// Jquery needed
// tools/tags/libs/vendor/bootstrap-tagsinput.min.js needed
$(document).ready(() => {
  function BazarTagsInputService() {
    this.init = function() {
      if (typeof bazarlistTagsInputsData === 'undefined' || bazarlistTagsInputsData.length < 1) return null
      const propertiesNames = Object.keys(bazarlistTagsInputsData)
      propertiesNames.forEach((propertyName) => {
        const { existingTags } = bazarlistTagsInputsData[propertyName]
        const existingTagsArray = Object.values(existingTags)
        const limit = bazarlistTagsInputsData[propertyName].limit ?? 0
        const { selectedOptions } = bazarlistTagsInputsData[propertyName]

        const anchor = $(`#formulaire .yeswiki-input-entries${propertyName}`)
        if (anchor.length == 0) {
          console.log(`#formulaire .yeswiki-input-entries${propertyName} NOT FOUND in bazar-tagsinput.js !`)
        } else {
          let options = {
            itemValue: 'id',
            itemText: 'title',
            typeahead: {
              afterSelect(val) { anchor.tagsinput('input').val('') },
              source: existingTagsArray,
              autoSelect: false
            },
            freeInput: false,
            confirmKeys: [13, 186, 188]
          }
          if (limit === 1) {
            options = {
              ...options,
              ...{ maxTags: 1 }
            }
          }
          anchor.tagsinput(options)
          selectedOptions.forEach((selectedOption) => {
            anchor.tagsinput('add', existingTags[selectedOption])
          })
        }
      })
    }
  }

  const BazarTagsInputRefresh = {
    bazarTagsInputService: {},
    bazarlistTagsInputsData: {},
    getFormId() {
      const input = $('input[name=id_typeannonce]').first()
      return input ? input.val() : null
    },
    refresh(element) {
      const parent = this
      const propertyName = $(element).data('property-name')
      const formId = this.getFormId()
      const concernedInput = $(`input[name=${propertyName}]`)
      if (propertyName && formId && concernedInput) {
        $.get(
          wiki.url(`api/forms/${formId}`),
          (data) => {
            if (data.prepared) {
              const fields = (typeof data.prepared == 'object')
                ? Object.values(data.prepared)
                : data.prepared
              fields.forEach((field) => {
                if (field.propertyname && field.propertyname == propertyName) {
                  const { options } = field
                  if (options) {
                    // get current
                    const currentOption = concernedInput.tagsinput('items')
                    // reset tagsinput
                    concernedInput.tagsinput('destroy')
                    const existingTags = new Object()
                    for (const key in options) {
                      existingTags[key] = {
                        id: key,
                        title: options[key]
                      }
                    }
                    const previousbazarlistTagsInputsData = {}
                    for (const key in parent.bazarlistTagsInputsData) {
                      previousbazarlistTagsInputsData[key] = parent.bazarlistTagsInputsData[key]
                      delete parent.bazarlistTagsInputsData[key]
                    }
                    parent.bazarlistTagsInputsData[propertyName] = {
                      existingTags,
                      limit: 1,
                      selectedOptions: currentOption[0] ? (currentOption[0].id ? [currentOption[0].id] : []) : []
                    }
                    // add tagsinput
                    parent.bazarTagsInputService.init()
                    // reset bazarlistTagsInputsData
                    for (const key in previousbazarlistTagsInputsData) {
                      parent.bazarlistTagsInputsData[key] = previousbazarlistTagsInputsData[key]
                    }
                  }
                }
              })
            }
          }
        )
      }
    },
    init(bazarTagsInputService, bazarlistTagsInputsData) {
      const parent = this
      this.bazarTagsInputService = bazarTagsInputService
      this.bazarlistTagsInputsData = bazarlistTagsInputsData
      $('.tagsinput-refresh').each(function() {
        $(this).click(function() {
          parent.refresh(this)
        })
      })
    }
  }

  const bazarTagsInputService = new BazarTagsInputService()
  bazarTagsInputService.init()

  BazarTagsInputRefresh.init(bazarTagsInputService, bazarlistTagsInputsData ?? {})
})
