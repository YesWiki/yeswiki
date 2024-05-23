import SpinnerLoader from './SpinnerLoader.js'
import DynTable from './DynTable.js'
import TemplateRenderer from './TemplateRenderer.js'
import Waiter from '../Waiter.js'

const componentName = 'BazarTable'
const isVueJS3 = (typeof Vue.createApp == 'function')

const componentParams = {
  props: ['currentusername', 'params', 'entries', 'ready', 'root', 'isadmin'],
  components: { SpinnerLoader, DynTable },
  data() {
    return {
      columns: [],
      dynamicTableSearch: '',
      extraOptions: {},
      fastSearch: false,
      fields: {},
      forms: {},
      rows: {},
      uuid: null
    }
  },
  computed: {
    sumtranslate() {
      return TemplateRenderer.render('BazarTable', this, 'sumtranslate')
    }
  },
  methods: {
    addRows(columns, entries, currentusername, isadmin) {
      const entriesToAdd = entries.filter((entry) => entry?.id_fiche && !(entry.id_fiche in this.rows))
      entriesToAdd.forEach((entry) => {
        const formattedData = {}
        columns.forEach((col) => {
          if (!(typeof col.data === 'string')) {
            formattedData[col.data] = ''
          } else if (col.data === '==canDelete==') {
            formattedData[col.data] = !this.$root.isExternalUrl(entry)
                            && 'owner' in entry
                            && (isadmin || (currentusername.length > 0 && entry.owner == currentusername))
          } else if (['==adminsbuttons=='].includes(col.data)) {
            formattedData[col.data] = ''
          } else if ('firstlevel' in col && typeof col.firstlevel === 'string' && col.firstlevel.length > 0) {
            formattedData[col.data] = (
              col.firstlevel in entry
                            && (typeof entry[col.firstlevel] === 'object')
                            && entry[col.firstlevel] !== null
                            && col.data in entry[col.firstlevel]
            ) ? entry[col.firstlevel][col.data] : ''
          } else if ('checkboxfield' in col && typeof col.checkboxfield === 'string' && col.checkboxfield.length > 0) {
            formattedData[col.data] = (
              col.checkboxfield in entry
                            && 'checkboxkey' in col
                            && typeof entry[col.checkboxfield] === 'string'
                            && entry[col.checkboxfield].split(',').includes(col.checkboxkey)
            ) ? 'X' : ''
          } else if ('displayValOptions' in col) {
            formattedData[col.data] = (col.data in entry && typeof entry[col.data] === 'string') ? {
              display: entry[col.data].split(',').map((v) => (v in col.displayValOptions ? col.displayValOptions[v] : v)).join(',\n'),
              export: entry[col.data].split(',').map((v) => (v in col.displayValOptions ? `"${col.displayValOptions[v]}"` : v)).join(','),
              raw: entry[col.data].split(',').map((v) => ({ key: v, title: v in col.displayValOptions ? col.displayValOptions[v] : v }))
            } : ''
            if (formattedData[col.data] !== '' && 'externalBaseUrl' in col) {
              formattedData[col.data].externalBaseUrl = col.externalBaseUrl
              formattedData[col.data].export = entry[col.data].split(',').map((v) => col.externalBaseUrl + v).join(',')
            }
          } else {
            formattedData[col.data] = (col.data in entry && typeof entry[col.data] === 'string') ? entry[col.data] : ''
            if (formattedData[col.data] !== '' && 'externalBaseUrl' in col) {
              formattedData[col.data] = {
                display: formattedData[col.data],
                export: col.externalBaseUrl + formattedData[col.data],
                externalBaseUrl: col.externalBaseUrl
              }
            }
          }
        });
        ['id_fiche', 'color', 'icon', 'url'].forEach((key) => {
          if (!(key in formattedData)) {
            formattedData[key] = entry[key] || ''
          }
        })
        this.$set(this.rows, entry.id_fiche, formattedData)
      })
    },
    arraysEqual(a, b) {
      if (a === b) return true
      if (a == null || b == null || !Array.isArray(a) || !Array.isArray(b)) return false
      if (a.length !== b.length) return false

      a.sort()
      b.sort()
      return a.every((val, idx) => a[idx] !== b[idx])
    },
    deleteAllSelected(event) {
      const uuid = this.getUuid()
      multiDeleteService.updateNbSelected(`MultiDeleteModal${uuid}`)
      // if something to do before showing modal
    },
    getAdminsButtons(entryId, entryTitle, entryUrl, candelete) {
      const isExternal = this.$root.isExternalUrl({ id_fiche: entryId, url: entryUrl })
      return TemplateRenderer.render(
        'BazarTable',
        this,
        'adminsbuttons',
        {
          entryId: 'entryId',
          entryTitle: 'entryTitle',
          entryUrl: 'entryUrl',
          isExternal,
          candelete: [true, 'true'].includes(candelete)
        },
        [
          [/entryId/g, entryId],
          [/entryTitle/g, entryTitle],
          [/entryUrl/g, entryUrl]
        ]
      )
    },
    async getColumns() {
      if (this.columns.length == 0) {
        const fields = await Waiter.waitFor('fields', this)
        const params = await Waiter.waitFor('params', this)
        let columnfieldsids = this.sanitizedParam(params, this.isAdmin, 'columnfieldsids')
        const defaultcolumnwidth = this.sanitizedParam(params, this.isAdmin, 'defaultcolumnwidth')
        if (columnfieldsids.every((id) => id.length == 0)) {
          // backup
          columnfieldsids = ['bf_titre']
        }
        const data = { columns: [] }
        const width = defaultcolumnwidth.length > 0 ? { width: defaultcolumnwidth } : {}
        if (this.sanitizedParam(params, this.isAdmin, 'displayadmincol')) {
          const uuid = this.getUuid()
          data.columns.push({
            ...{
              data: '==canDelete==',
              class: 'not-export-this-col',
              orderable: false,
              render: (data, type, row) => (type === 'display' ? this.getDeleteChekbox(uuid, row.id_fiche, !data) : ''),
              title: this.getDeleteChekboxAll(uuid, 'top'),
              footer: this.getDeleteChekboxAll(uuid, 'bottom')
            },
            ...width
          })
          data.columns.push({
            ...{
              data: '==adminsbuttons==',
              orderable: false,
              class: 'horizontal-admins-btn not-export-this-col',
              render: (data, type, row) => (type === 'display' ? this.getAdminsButtons(row.id_fiche, row.bf_titre || '', row.url || '', row['==canDelete==']) : ''),
              title: '',
              footer: ''
            },
            ...width
          })
        }
        const options = {
          checkboxfieldsincolumns: this.sanitizedParam(params, this.isAdmin, 'checkboxfieldsincolumns'),
          columnswidth: this.sanitizedParam(params, this.isAdmin, 'columnswidth'),
          defaultcolumnwidth,
          sumfieldsids: this.sanitizedParam(params, this.isAdmin, 'sumfieldsids'),
          visible: true,
          printable: true,
          addLink: false,
          columntitles: this.sanitizedParam(params, this.isAdmin, 'columntitles'),
          displayimagesasthumbnails: this.sanitizedParam(params, this.isAdmin, 'displayimagesasthumbnails'),
          displayvaluesinsteadofkeys: this.sanitizedParam(params, this.isAdmin, 'displayvaluesinsteadofkeys'),
          baseIdx: data.columns.length
        }
        let fieldsToRegister = ['date_creation_fiche', 'date_maj_fiche', 'owner', 'id_typeannonce', 'url']
        columnfieldsids.forEach((id, idx) => {
          if (id.length > 0 && id in fields) {
            this.registerField(data, {
              ...options,
              ...{
                field: fields[id],
                visible: true,
                printable: true,
                addLink: (idx === 0 && !columnfieldsids.includes('bf_titre')) || id === 'bf_titre'
              }
            })
          } else if (fieldsToRegister.includes(id)) {
            fieldsToRegister = fieldsToRegister.filter((e) => e != id)
            this.registerSpecialFields([id], false, params, data, options)
          }
        })
        if (await this.sanitizedParamAsync('exportallcolumns')) {
          Object.keys(fields).forEach((id) => {
            // append fields not displayed
            if (!columnfieldsids.includes(id)) {
              this.registerField(data, {
                ...options,
                ...{
                  field: fields[id],
                  visible: false,
                  printable: false,
                  addLink: false
                }
              })
            }
          })
        }

        this.registerSpecialFields(fieldsToRegister, true, params, data, options)
        const champField = await this.sanitizedParamAsync('champ')
        const ordreField = await this.sanitizedParamAsync('ordre')
        const order = (ordreField === 'desc') ? 'desc' : 'asc'
        let firstColumnOrderable = 0
        for (let index = 0; index < data.columns.length; index++) {
          if ('orderable' in data.columns[index] && !data.columns[index].orderable) {
            firstColumnOrderable += 1
          } else {
            break
          }
        }
        let columnToOrder = firstColumnOrderable
        if (typeof champField === 'string' && champField.length > 0) {
          for (let index = 0; index < data.columns.length; index++) {
            if ((!('orderable' in data.columns[index]) || data.columns[index].orderable) && data.columns[index].data == champField) {
              columnToOrder = index
            }
          }
        }
        this.extraOptions = { order: [[columnToOrder, order]] }
        this.columns = data.columns
      }
      return this.columns
    },
    getDeleteChekbox(targetId, itemId, disabled = false) {
      return TemplateRenderer.render(
        'BazarTable',
        this,
        'deletecheckbox',
        { targetId: 'targetId', itemId: 'itemId', disabled },
        [
          [/targetId/g, targetId],
          [/itemId/g, itemId]
        ]
      )
    },
    getDeleteChekboxAll(targetId, selectAllType) {
      return TemplateRenderer.render(
        'BazarTable',
        this,
        'deletecheckboxall',
        {},
        [
          [/targetId/g, targetId],
          [/selectAllType/g, selectAllType]
        ]
      )
    },
    async getJson(url) {
      return await fetch(url)
        .then((response) => {
          if (response.ok) {
            return response.json()
          }
          throw new Error(`reponse was not ok when getting "${url}"`)
        })
    },
    getUuid() {
      if (this.uuid === null) {
        this.uuid = `${Date.now()}-${Math.round(Math.random() * 10000)}`
      }
      return this.uuid
    },
    manageError(error) {
      if (wiki.isDebugEnabled) {
        console.error(error)
      }
      return null
    },
    registerField(data, {
      field,
      checkboxfieldsincolumns = false,
      sumfieldsids = [],
      visible = true,
      printable = true,
      addLink = false,
      columnswidth = {},
      defaultcolumnwidth = '',
      columntitles = {},
      baseIdx = 0,
      displayimagesasthumbnails = false,
      displayvaluesinsteadofkeys = false
    }) {
      if (typeof field.propertyname === 'string' && field.propertyname.length > 0) {
        const className = (printable ? '' : 'not-printable') + (sumfieldsids.includes(field.propertyname) ? ' sum-activated' : '')
        const width = field.propertyname in columnswidth ? { width: columnswidth[field.propertyname] } : (defaultcolumnwidth.length > 0 ? { width: defaultcolumnwidth } : {})
        const titleIdx = data.columns.length - baseIdx
        if (typeof field.type === 'string' && field.type === 'map') {
          data.columns.push({
            ...{
              class: className,
              data: field.latitudeField,
              title: columntitles[field.latitudeField] || columntitles[titleIdx] || TemplateRenderer.getTemplateFromSlot('BazarTable', this, 'latitudetext'),
              firstlevel: field.propertyname,
              render: this.renderCell({ addLink, idx: titleIdx }),
              footer: '',
              visible
            },
            ...width
          })
          data.columns.push({
            ...{
              class: className,
              data: field.longitudeField,
              title: columntitles[field.longitudeField] || columntitles[titleIdx + 1] || TemplateRenderer.getTemplateFromSlot('BazarTable', this, 'longitudetext'),
              firstlevel: field.propertyname,
              render: this.renderCell({ addLink }),
              footer: '',
              visible
            },
            ...width
          })
        } else if (checkboxfieldsincolumns
                    && typeof field.type === 'string'
                    && ['checkboxfiche', 'checkbox'].includes(field.type)
                    && typeof field.options == 'object') {
          Object.keys(field.options).forEach((optionKey, idx) => {
            const name = `${field.propertyname}-${optionKey}`
            data.columns.push({
              ...{
                class: className,
                data: name,
                title: columntitles[name]
                                    || (
                                      field.propertyname in columntitles
                                        ? `${columntitles[field.propertyname]} - ${field.options[optionKey] || optionKey}`
                                        : undefined
                                    )
                                    || columntitles[titleIdx + idx]
                                    || `${field.label || field.propertyname} - ${field.options[optionKey] || optionKey}`,
                checkboxfield: field.propertyname,
                render: this.renderCell({ addLink, idx: titleIdx + idx }),
                checkboxkey: optionKey,
                footer: '',
                visible
              },
              ...width
            })
          })
        } else {
          const fieldtype = ['link', 'email', 'checkboxfiche', 'listefiche', 'radiofiche'].includes(field.type) ? field.type : ((field.type === 'image' && displayimagesasthumbnails) ? 'image' : '')
          const fieldName = (fieldtype === 'image' && displayimagesasthumbnails) ? field.propertyname : ''
          const displayValOptions = (displayvaluesinsteadofkeys && 'options' in field && typeof field.options === 'object')
            ? { displayValOptions: field.options }
            : {}
          if ('linkedObjectName' in field && field.linkedObjectName.match(/^https?:\/\//)) {
            displayValOptions.externalBaseUrl = field.linkedObjectName.match(/^(https?:\/\/.*)api\/(forms|entries).*$/)
              ? field.linkedObjectName.replace(/^(https?:\/\/.*)api\/(forms|entries).*$/, '$1')
              : ''
          }
          data.columns.push({
            ...{
              class: className,
              data: field.propertyname,
              title: columntitles[field.propertyname] || columntitles[titleIdx] || field.label || field.propertyname,
              render: this.renderCell({ fieldtype, addLink, idx: titleIdx, fieldName }),
              footer: '',
              visible
            },
            ...width,
            ...displayValOptions
          })
        }
      }
    },
    registerSpecialFields(fieldsToRegister, test, params, data, options) {
      if (Array.isArray(fieldsToRegister) && fieldsToRegister.length > 0) {
        const parameters = {
          date_creation_fiche: {
            paramName: 'displaycreationdate',
            slotName: 'creationdatetranslate'
          },
          date_maj_fiche: {
            paramName: 'displaylastchangedate',
            slotName: 'modifiydatetranslate'
          },
          owner: {
            paramName: 'displayowner',
            slotName: 'ownertranslate'
          },
          id_typeannonce: {
            paramName: '',
            slotName: 'formidtranslate'
          },
          url: {
            paramName: '',
            slotName: 'urltranslate'
          }
        }
        fieldsToRegister.forEach((propertyName) => {
          if (propertyName in parameters) {
            const canPushColumn = (parameters[propertyName].slotName.length > 0)
              ? (
                test
                  ? (
                    parameters[propertyName].paramName.length > 0
                                    && this.sanitizedParam(params, this.isadmin, parameters[propertyName].paramName)
                  )
                  : true
              ) : false
            if (canPushColumn) {
              const internalOptions = {
                data: propertyName,
                title: options.columntitles[propertyName] || TemplateRenderer.getTemplateFromSlot('BazarTable', this, parameters[propertyName].slotName),
                footer: ''
              }
              if (propertyName === 'url') {
                internalOptions.render = this.renderCell({ addLink: true })
              }
              data.columns.push(internalOptions)
            }
          }
        })
      }
    },
    removeRows(newIds) {
      const entryIdsToRemove = Object.keys(this.rows).filter((id) => !newIds.includes(id))
      entryIdsToRemove.forEach((id) => {
        if (id in this.rows) {
          this.$delete(this.rows, id)
        }
      })
    },
    renderCell({ fieldtype = '', fieldName = '', addLink = false, idx = -1 }) {
      return (data, type, row) => {
        if (type === 'sort' || type === 'filter') {
          return (typeof data === 'object' && 'export' in data) ? data.export : data
        }
        const formattedData = (typeof data === 'object' && data !== null && 'display' in data) ? data.display : (data === null ? '' : String(data))
        let anchorData = 'anchorData'
        let anchorImageSpecificPart = ''
        let anchorImageOther = ''
        let anchorImageExt = ''
        let anchorOtherEntryId = ''
        if (fieldtype === 'image') {
          if (formattedData.length > 0) {
            let regExp = new RegExp(`^(${row.id_fiche}_${fieldName}_)(.*)_(\\d{14})_(\\d{14})\\.([^.]+)$`)
            if (regExp.test(formattedData)) {
              let anchorImageDate1 = ''
              let anchorImageDate2 = '';
              [,, anchorImageSpecificPart, anchorImageDate1, anchorImageDate2, anchorImageExt] = formattedData.match(regExp)
              anchorImageOther = `${anchorImageDate1}_${anchorImageDate2}`
              anchorData = 'entryIdAnchor_fieldNameAnchor_anchorImageSpecificPart_anchorImageOther.anchorImageExt'
            } else {
              regExp = new RegExp(`^(${row.id_fiche}_${fieldName}_)(.*)\\.([^.]+)$`)
              if (regExp.test(formattedData)) {
                [,, anchorImageSpecificPart, anchorImageExt] = formattedData.match(regExp)
                anchorData = 'entryIdAnchor_fieldNameAnchor_anchorImageSpecificPart.anchorImageExt'
              } else {
                // maybe from other entry
                regExp = new RegExp(`^([A-Za-z0-9-_]+)(_${fieldName}_)(.*)_(\\d{14})_(\\d{14})\\.([^.]+)$`)
                if (regExp.test(formattedData)) {
                  let anchorImageDate1 = ''
                  let anchorImageDate2 = '';
                  [, anchorOtherEntryId,, anchorImageSpecificPart, anchorImageDate1, anchorImageDate2, anchorImageExt] = formattedData.match(regExp)
                  anchorImageOther = `${anchorImageDate1}_${anchorImageDate2}`
                  anchorData = 'anchorOtherEntryId_fieldNameAnchor_anchorImageSpecificPart_anchorImageOther.anchorImageExt'
                } else {
                  // last possible format
                  regExp = new RegExp('^(.*)\\.([^.]+)$')
                  if (regExp.test(formattedData)) {
                    [, anchorImageSpecificPart, anchorImageExt] = formattedData.match(regExp)
                    anchorData = 'anchorImageSpecificPart.anchorImageExt'
                  } else {
                    anchorImageSpecificPart = formattedData
                    anchorData = 'anchorImageSpecificPart'
                  }
                }
              }
            }
          } else {
            anchorData = ''
          }
        } else if (typeof fieldtype === 'string' && ['listefiche', 'radiofiche', 'checkboxfiche'].includes(fieldtype) && formattedData.length > 0) {
          const intFieldType = (typeof data === 'object' && data !== null && 'externalBaseUrl' in data)
            ? (
              data.externalBaseUrl.length > 0
                ? 'urlnewwindow'
                : ''
            ) : 'urlmodal'
          const formattedArray = (typeof data === 'object' && data !== null && 'raw' in data)
            ? data.raw
            : formattedData.split(',').map((key) => ({ key, title: key }))
          return formattedArray.map(({ key, title }) => this.renderCell({ fieldtype: intFieldType, fieldName, idx })({ display: title }, type, {
            ...row,
            ...{
              id_fiche: key,
              url: (intFieldType === 'urlmodal')
                ? wiki.url(`${key}/iframe`)
                : (
                  intFieldType === 'urlnewwindow'
                    ? data.externalBaseUrl + key
                    : ''
                )
            }
          }).trim()).join(',\n')
        }
        return TemplateRenderer.render(
          'BazarTable',
          this,
          'rendercell',
          {
            anchorData,
            fieldtype,
            addLink,
            entryId: 'entryIdAnchor',
            fieldName,
            url: 'anchorUrl',
            color: (idx === 0 && row?.color && row.color.length > 0) ? 'lightslategray' : '',
            icon: (idx === 0 && row?.icon && row.icon.length > 0) ? 'iconAnchor' : ''
          },
          [
            [/anchorData/g, formattedData.replace(/\n/g, '<br/>')],
            [/entryIdAnchor/g, row?.id_fiche],
            [/anchorUrl/g, row?.url],
            [/lightslategray/g, row?.color],
            [/iconAnchor/g, row?.icon],
            [/fieldNameAnchor/g, fieldName],
            [/anchorImageSpecificPart/g, anchorImageSpecificPart],
            [/anchorImageOther/g, anchorImageOther],
            [/anchorImageExt/g, anchorImageExt],
            [/anchorOtherEntryId/g, anchorOtherEntryId]
          ]
        )
      }
    },
    async sanitizedParamAsync(name) {
      return this.sanitizedParam(await Waiter.waitFor('params', this), this.isadmin, name)
    },
    sanitizedParam(params, isAdmin, name) {
      switch (name) {
        case 'displayadmincol':
        case 'displaycreationdate':
        case 'displaylastchangedate':
        case 'displayowner':
          const paramValue = (
            name in params
                            && typeof params[name] === 'string'
                            && ['yes', 'onlyadmins'].includes(params[name]))
            ? params[name]
            : false
          switch (paramValue) {
            case 'onlyadmins':
              return [1, true, '1', 'true'].includes(isAdmin)
            case 'yes':
              return true
            case false:
            default:
              return false
          }
        case 'checkboxfieldsincolumns':
          // default true
          return name in params ? !([false, 0, '0', 'false'].includes(params[name])) : true
        case 'displayvaluesinsteadofkeys':
        case 'exportallcolumns':
        case 'displayimagesasthumbnails':
          // default false
          return name in params ? [true, 1, '1', 'true'].includes(params[name]) : false

        case 'columnfieldsids':
        case 'sumfieldsids':
          return (name in params && typeof params[name] === 'string')
            ? params[name].split(',').map((v) => v.trim())
            : []
        case 'columntitles':
          const columntitlesastab = (name in params && typeof params[name] === 'string')
            ? params[name].split(',').map((v) => v.trim())
            : []
          const columntitles = {}
          columntitlesastab.forEach((val, idx) => {
            const match = val.match(/^([A-Za-z0-9\-_]+)=(.+$)/)
            if (match) {
              const [, key, title] = match
              columntitles[key] = title
            } else {
              columntitles[idx] = val
            }
          })
          return columntitles
        case 'sumfieldsids':
          return (name in params && typeof params[name] === 'string')
            ? params[name].split(',').map((v) => v.trim())
            : []
        case 'columnswidth':
          const columnswidth = {}
          if (
            name in params
                        && typeof params[name] === 'string'
          ) {
            params[name].split(',').forEach((extract) => {
              const [name, value] = extract.split('=', 2)
              if (name && value && name.length > 0 && value.length > 0) {
                columnswidth[name] = value
              }
            })
          }
          return columnswidth
        case 'defaultcolumnwidth':
          return name in params ? String(params[name]) : ''
        default:
          return params[name] || null
      }
    },
    startDelete(event) {
      if (!multiDeleteService.isRunning) {
        multiDeleteService.isRunning = true
        const elem = event.target
        if (elem) {
          $(elem).attr('disabled', 'disabled')
          multiDeleteService.deleteItems(elem)
        }
      }
    },
    async updateEntries(newEntries, newIds) {
      const columns = await this.getColumns()
      const { currentusername } = this
      this.removeRows(newIds)
      this.addRows(columns, newEntries, currentusername, this.isadmin)
    },
    updateFieldsFromRoot() {
      this.fields = this.$root.formFields
      if (Object.keys(this.fields).length > 0) {
        Waiter.resolve('fields')
      }
    }
  },
  mounted() {
    $(isVueJS3 ? this.$el.parentNode : this.$el).on('dblclick', (e) => false)
    this.updateFieldsFromRoot()
    window.urlImageResizedOnError = this.$root.urlImageResizedOnError
    this.$root.$watch('isLoading', (isLoading) => {
      if (!isLoading) {
        this.fastSearch = false
      }
    })
    this.$root.$watch('search', (newSearch) => {
      this.fastSearch = true
      this.dynamicTableSearch = newSearch
    })
    this.$root.$watch('searchedEntries', () => {
      this.dynamicTableSearch = ''
    })
  },
  watch: {
    entries(newVal, oldVal) {
      this.updateFieldsFromRoot() // because updated in same time than entries (but not reactive)
      const sanitizedNewVal = newVal.filter((e) => (typeof e === 'object' && e !== null && 'id_fiche' in e))
      const newIds = sanitizedNewVal.map((e) => e.id_fiche)
      const oldIds = oldVal.map((e) => e.id_fiche || '').filter((e) => (typeof e === 'string' && e.length > 0))
      if (!this.arraysEqual(newIds, oldIds)) {
        this.updateEntries(sanitizedNewVal, newIds).catch(this.manageError)
      }
    },
    params() {
      Waiter.resolve('params')
    },
    ready() {
      this.sanitizedParamAsync('displayadmincol').then((displayadmincol) => {
        if (displayadmincol) {
          $(this.$refs.buttondeleteall).find(`#MultiDeleteModal${this.getUuid()}`).first().each(function() {
            $(this).on('shown.bs.modal', function() {
              multiDeleteService.initProgressBar($(this))
              $(this).find('.modal-body .multi-delete-results').html('')
              $(this).find('button.start-btn-delete-all').removeAttr('disabled')
            })
            $(this).on('hidden.bs.modal', function() {
              multiDeleteService.modalClosing($(this))
            })
          })
        }
      }).catch(this.manageError)
    }
  },
  template: `
    <div>
        <slot name="header" v-bind="{BazarTable:this}"/>
        <dyn-table 
                :columns="columns" 
                :rows="rows" 
                :uuid="getUuid()" 
                :externalSearch="dynamicTableSearch"
                :extraOptions="extraOptions">
            <template #dom>&lt;'row'&lt;'col-sm-12'tr>>&lt;'row'&lt;'col-sm-6'i>&lt;'col-sm-6'&lt;'pull-right'B>>></template>
            <template #sumtranslate>{{ sumtranslate }}</template>
        </dyn-table>
        <div ref="buttondeleteall">
            <slot v-if="ready && sanitizedParam(params,isadmin,'displayadmincol')" name="deleteallselectedbutton" v-bind="{uuid:getUuid(),BazarTable:this}"/>
        </div>
        <slot name="spinnerloader" v-bind="{BazarTable:this}"/>
        <slot name="footer" v-bind="{BazarTable:this}"/>
    </div>
  `
}

if (isVueJS3) {
  if (window.hasOwnProperty('bazarVueApp')) { // bazarVueApp must be defined into bazar-list-dynamic
    if (!bazarVueApp.config.globalProperties.hasOwnProperty('wiki')) {
      bazarVueApp.config.globalProperties.wiki = wiki
    }
    if (!bazarVueApp.config.globalProperties.hasOwnProperty('_t')) {
      bazarVueApp.config.globalProperties._t = _t
    }
    window.bazarVueApp.component(componentName, componentParams)
  }
} else {
  if (!Vue.prototype.hasOwnProperty('wiki')) {
    Vue.prototype.wiki = wiki
  }
  if (!Vue.prototype.hasOwnProperty('_t')) {
    Vue.prototype._t = _t
  }
  Vue.component(componentName, componentParams)
}
