import SpinnerLoader from './SpinnerLoader.js'

let componentName = 'BazarTable';
let isVueJS3 = (typeof Vue.createApp == "function");

let componentParams = {
    props: ['currentusername','params','entries','ready','root','isadmin'],
    components: {SpinnerLoader},
    data: function() {
        return {
            cacheResolveReject: {},
            columns: [],
            dataTable: null,
            displayedEntries: {},
            fields: {},
            forms: {},
            isReady:{
                fields: false,
                params: false
            },
            templatesForRendering: {},
            uuid: null
        };
    },
    methods:{
        addRows(dataTable,columns,entries,currentusername,isadmin){
            const entriesToAdd = entries.filter((entry)=>typeof entry === 'object' && 'id_fiche' in entry && !(entry.id_fiche in this.displayedEntries))
            let formattedDataList = []
            entriesToAdd.forEach((entry)=>{
                this.displayedEntries[entry.id_fiche] = entry
                let formattedData = {}
                columns.forEach((col)=>{
                    if (!(typeof col.data === 'string')){
                        formattedData[col.data] = ''
                    } else if (col.data === '==canDelete=='){
                        formattedData[col.data] = !this.$root.isExternalUrl(entry) && 
                            'owner' in entry &&
                            (isadmin || entry.owner == currentusername)
                    } else if (['==adminsbuttons=='].includes(col.data)) {
                        formattedData[col.data] = ''
                    } else if ('firstlevel' in col && typeof col.firstlevel === 'string' && col.firstlevel.length > 0){
                        formattedData[col.data] = (
                            col.firstlevel in entry && 
                            (typeof entry[col.firstlevel] === 'object') &&
                            entry[col.firstlevel] !== null && 
                            col.data in entry[col.firstlevel]
                        ) ? entry[col.firstlevel][col.data] : ''
                    } else if ('checkboxfield' in col && typeof col.checkboxfield === 'string' && col.checkboxfield.length > 0){
                        formattedData[col.data] = (
                            col.checkboxfield in entry &&
                            'checkboxkey' in col &&
                            typeof entry[col.checkboxfield] === 'string'
                            && entry[col.checkboxfield].split(',').includes(col.checkboxkey)
                        ) ? 'X' : ''
                    } else if ('displayValOptions' in col){
                        formattedData[col.data] = (col.data in entry && typeof entry[col.data] === 'string') ? {
                            display:entry[col.data].split(',').map((v)=>v in col.displayValOptions ? col.displayValOptions[v] : v).join(",\n"),
                            export: entry[col.data].split(',').map((v)=>v in col.displayValOptions ? `"${col.displayValOptions[v]}"` : v).join(',')
                        } : ''
                    } else {
                        formattedData[col.data] = (col.data in entry && typeof entry[col.data] === 'string' ) ? entry[col.data] : ''
                    }
                });
                ['id_fiche','color','icon','url'].forEach((key)=>{
                    if (!(key in formattedData)){
                        formattedData[key] = entry[key] || ''
                    }
                })
                formattedDataList.push(formattedData)
            })
            dataTable.rows.add(formattedDataList)
        },
        arraysEqual(a, b) {
            if (a === b) return true
            if (a == null || b == null || !Array.isArray(a) || !Array.isArray(b)) return false
            if (a.length !== b.length) return false
    
            a.sort()
            b.sort()
            return a.every((val,idx)=>a[idx] !== b[idx])
        },
        deleteAllSelected(event){
            const uuid = this.getUuid()
            multiDeleteService.updateNbSelected(`MultiDeleteModal${uuid}`)
            const entriesIdsToRefreshDeleteToken = []
            $(`#${uuid}`).find('tr > td:first-child input.selectline[type=checkbox]:visible:checked').each(function (){
                const csrfToken = $(this).data('csrftoken')
                const itemId = $(this).data('itemid')
                if (typeof itemId === 'string' && itemId.length > 0 && (typeof csrfToken !== 'string' || csrfToken === 'to-be-defined')){
                    entriesIdsToRefreshDeleteToken.push({elem:$(this),itemId})
                }
            })
            if (entriesIdsToRefreshDeleteToken.length > 0){
                this.getCsrfDeleteTokens(entriesIdsToRefreshDeleteToken.map((e)=>e.itemId))
                    .then((tokens)=>{
                        entriesIdsToRefreshDeleteToken.forEach(({elem,itemId})=>{
                            $(elem).data('csrftoken',tokens[itemId] || 'error')
                        })
                    })
                    .catch(this.manageError)
            }
            // if something to do before showing modal (like get csrf token ?)
        },
        getAdminsButtons(entryId,entryTitle,entryUrl,candelete){
            const isExternal =this.$root.isExternalUrl({id_fiche:entryId,url:entryUrl})
            return this.getTemplateFromSlot(
                'adminsbuttons',
                {
                    entryId:'entryId',
                    entryTitle:'entryTitle',
                    entryUrl:'entryUrl',
                    isExternal,
                    candelete: [true,'true'].includes(candelete)
                }
            ).replace(/entryId/g,entryId)
            .replace(/entryTitle/g,entryTitle)
            .replace(/entryUrl/g,entryUrl)
        },
        async getColumns(){
            if (this.columns.length == 0){
                const fields = await this.waitFor('fields')
                const params = await this.waitFor('params');
                let columnfieldsids = this.sanitizedParam(params,this.isAdmin,'columnfieldsids')
                let defaultcolumnwidth = this.sanitizedParam(params,this.isAdmin,'defaultcolumnwidth')
                if (columnfieldsids.every((id)=>id.length ==0)){
                    // backup
                    columnfieldsids = ['bf_titre']
                }
                const data = {columns:[]}
                const width = defaultcolumnwidth.length > 0 ? {width:defaultcolumnwidth}: {}
                if (this.sanitizedParam(params,this.isAdmin,'displayadmincol')){
                    const uuid = this.getUuid()
                    data.columns.push({
                        ...{
                            data: '==canDelete==',
                            class: 'not-export-this-col',
                            orderable: false,
                            render: (data,type,row)=>{
                                return type === 'display' ? this.getDeleteChekbox(uuid,row.id_fiche,!data) : ''
                            },
                            title: this.getDeleteChekboxAll(uuid,'top'),
                            footer: this.getDeleteChekboxAll(uuid,'bottom')
                        },
                        ...width
                    })
                    data.columns.push({
                        ...{
                            data: '==adminsbuttons==',
                            orderable: false,
                            class: 'horizontal-admins-btn not-export-this-col',
                            render: (data,type,row)=>{
                                return type === 'display' ? this.getAdminsButtons(row.id_fiche,row.bf_titre || '',row.url || '',row['==canDelete==']) : ''
                            },
                            title: '',
                            footer: ''
                        },
                        ...width
                    })
                }
                const options={
                    checkboxfieldsincolumns: this.sanitizedParam(params,this.isAdmin,'checkboxfieldsincolumns'),
                    columnswidth: this.sanitizedParam(params,this.isAdmin,'columnswidth'),
                    defaultcolumnwidth,
                    sumfieldsids: this.sanitizedParam(params,this.isAdmin,'sumfieldsids'),
                    visible:true,
                    printable:true,
                    addLink:false,
                    columntitles:this.sanitizedParam(params,this.isAdmin,'columntitles'),
                    displayimagesasthumbnails:this.sanitizedParam(params,this.isAdmin,'displayimagesasthumbnails'),
                    displayvaluesinsteadofkeys:this.sanitizedParam(params,this.isAdmin,'displayvaluesinsteadofkeys'),
                    baseIdx: data.columns.length
                }
                let fieldsToRegister = ['date_creation_fiche','date_maj_fiche','owner','id_typeannonce']
                columnfieldsids.forEach((id,idx)=>{
                    if (id.length >0 && id in fields){
                        this.registerField(data,{
                            ...options,
                            ...{
                                field:fields[id],
                                visible:true,
                                printable:true,
                                addLink:idx === 0 && !('bf_titre' in columnfieldsids)
                            }
                        })
                    } else if (fieldsToRegister.includes(id)) {
                        fieldsToRegister = fieldsToRegister.filter((e)=>e!=id)
                        this.registerSpecialFields([id],false,params,data)
                    }
                })
                if (await this.sanitizedParamAsync('exportallcolumns')){
                    Object.keys(fields).forEach((id)=>{
                        // append fields not displayed
                        if (!columnfieldsids.includes(id)){
                            this.registerField(data,{
                                ...options,
                                ...{
                                    field:fields[id],
                                    visible:false,
                                    printable:false,
                                    addLink:false
                                }
                            })
                        }
                    })
                }

                this.registerSpecialFields(fieldsToRegister,true,params,data)

                this.columns = data.columns
            }
            return this.columns
        },
        async getCsrfDeleteToken(entryId){
            return await this.getJson(wiki.url(`?api/pages/${entryId}/delete/getToken`))
            .then((json)=>('token' in json && typeof json.token === 'string') ? json.token : 'error')
        },
        async getCsrfDeleteTokens(entriesIds){
            return await this.getJson(wiki.url(`?api/pages/example/delete/getTokens`,{pages:entriesIds.join(',')}))
            .then((json)=>('tokens' in json && typeof json.tokens === 'object') ? json.tokens : entriesIds.reduce((o, key) => ({ ...o, [key]: 'error'}), {}))
        },
        getDatatableOptions(){
            const buttons = []
            DATATABLE_OPTIONS.buttons.forEach((option) => {
              buttons.push({
                ...option,
                ...{ footer: true },
                ...{
                  exportOptions: (
                    option.extend != 'print'
                      ? {
                        orthogonal: 'sort', // use sort data for export
                        columns(idx, data, node) {
                          return !$(node).hasClass('not-export-this-col')
                        }
                      }
                      : {
                        columns(idx, data, node) {
                          const isVisible = $(node).data('visible')
                          return !$(node).hasClass('not-export-this-col') && (
                            isVisible == undefined || isVisible != false
                          ) && !$(node).hasClass('not-printable')
                        }
                      })
                }
              })
            })
            return {
                ...DATATABLE_OPTIONS,
                ...{
                  searching: false,
                  footerCallback: ()=>{
                    this.updateFooter()
                  },
                  buttons
                }
              }
        },
        async getDatatable(){
            if (this.dataTable === null){
                // create dataTable
                const columns = await this.getColumns()
                const sumfieldsids = await this.sanitizedParamAsync('sumfieldsids')
                const champField = await this.sanitizedParamAsync('champ')
                const ordreField = await this.sanitizedParamAsync('ordre')
                let order = (ordreField === 'desc') ? 'desc'  :'asc'
                let firstColumnOrderable = 0
                for (let index = 0; index < columns.length; index++) {
                    if ('orderable' in columns[index]  && !columns[index].orderable){
                        firstColumnOrderable += 1
                    } else {
                        break
                    }
                }
                let columnToOrder = firstColumnOrderable
                if (typeof champField === 'string' && champField.length > 0){
                    for (let index = 0; index < columns.length; index++) {
                        if ((!('orderable' in columns[index]) || columns[index].orderable) && columns[index].data == champField){
                            columnToOrder = index
                        }
                    }
                }
                this.dataTable = $(this.$refs.dataTable).DataTable({
                    ...this.getDatatableOptions(),
                    ...{
                        columns: columns,
                        "scrollX": true,
                        order: [[columnToOrder,order]]
                    }
                })
                $(this.dataTable.table().node()).prop('id',this.getUuid())
                if (sumfieldsids.length > 0 || this.sanitizedParamAsync('displayadmincol')){
                    this.initFooter(columns,sumfieldsids)
                }
            }
            return this.dataTable
        },
        getDeleteChekbox(targetId,itemId,disabled = false){
            return this.getTemplateFromSlot(
                'deletecheckbox',
                {targetId:'targetId',itemId:'itemId',disabled}
            ).replace(/targetId/g,targetId)
            .replace(/itemId/g,itemId)
        },
        getDeleteChekboxAll(targetId,selectAllType){
            return this.getTemplateFromSlot('deletecheckboxall',{})
                .replace(/targetId/g,targetId)
                .replace(/selectAllType/g,selectAllType)
        },
        async getJson(url){
            return await fetch(url)
                .then((response)=>{
                    if (response.ok){
                        return response.json()
                    } else {
                        throw new Error(`reponse was not ok when getting "${url}"`)
                    }
                })
        },
        getTemplateFromSlot(name,params){
            const key = name+'-'+JSON.stringify(params)
            if (!(key in this.templatesForRendering)){
                if (name in this.$scopedSlots){
                    const slot = this.$scopedSlots[name]
                    const constructor = Vue.extend({
                        render: function(h){
                            return h('div',{},slot(params))
                        }
                    })
                    const instance = new constructor()
                    instance.$mount()
                    let outerHtml = '';
                    for (let index = 0; index < instance.$el.childNodes.length; index++) {
                        outerHtml += instance.$el.childNodes[index].outerHTML || instance.$el.childNodes[index].textContent
                    }
                    this.templatesForRendering[key] = outerHtml
                } else {
                    this.templatesForRendering[key] = ''
                }
            }
            return this.templatesForRendering[key]
        },
        getUuid(){
            if (this.uuid === null){
                this.uuid = crypto.randomUUID()
            }
            return this.uuid
        },
        initFooter(columns,sumfieldsids){
            const footerNode = this.dataTable.footer().to$()
            if (footerNode[0] !== null){
                const footer = $('<tr>')
                let displayTotal = sumfieldsids.length > 0
                columns.forEach((col)=>{
                    if ('footer' in col && col.footer.length > 0){
                        const element = $(col.footer)
                        const isTh = $(element).prop('tagName') === 'TH'
                        footer.append(isTh ? element : $('<th>').append(element))
                    } else if (displayTotal) {
                        displayTotal = false
                        footer.append($('<th>').text(this.getTemplateFromSlot('sumtranslate',{})))
                    } else {
                        footer.append($('<th>'))
                    }
                })
                footerNode.html(footer)
            }
        },
        manageError(error){
            if (wiki.isDebugEnabled){
                console.error(error)
            }
            return null
        },
        registerField(data,{
                field,
                checkboxfieldsincolumns=false,
                sumfieldsids=[],
                visible=true,
                printable=true,
                addLink=false,
                columnswidth={},
                defaultcolumnwidth='',
                columntitles={},
                baseIdx=0,
                displayimagesasthumbnails=false,
                displayvaluesinsteadofkeys=false
            }){
            if (typeof field.propertyname === 'string' && field.propertyname.length > 0){
                const className = (printable ? '' : 'not-printable')+(sumfieldsids.includes(field.propertyname) ? ' sum-activated': '')
                const width = field.propertyname in columnswidth ? {width:columnswidth[field.propertyname]} : (defaultcolumnwidth.length > 0 ? {width:defaultcolumnwidth}: {})
                const titleIdx = data.columns.length - baseIdx
                if (typeof field.type === 'string' && field.type === 'map'){
                    data.columns.push({
                        ...{
                            class: className,
                            data: field.latitudeField,
                            title: columntitles[field.latitudeField] || columntitles[titleIdx] || this.getTemplateFromSlot('latitudetext',{}),
                            firstlevel: field.propertyname,
                            render: this.renderCell({addLink,idx:titleIdx}),
                            footer: '',
                            visible
                        },
                        ...width
                    })
                    data.columns.push({
                        ...{
                            class: className,
                            data: field.longitudeField,
                            title: columntitles[field.longitudeField] || columntitles[titleIdx+1] || this.getTemplateFromSlot('longitudetext',{}),
                            firstlevel: field.propertyname,
                            render: this.renderCell({addLink}),
                            footer: '',
                            visible
                        },
                        ...width
                    })
                } else if (checkboxfieldsincolumns && 
                    typeof field.type === 'string' && 
                    ['checkboxfiche','checkbox'].includes(field.type) &&
                    typeof field.options == 'object') {
                    Object.keys(field.options).forEach((optionKey,idx)=>{
                        const name = `${field.propertyname}-${optionKey}`
                        data.columns.push({
                            ...{
                                class: className,
                                data: name,
                                title: columntitles[name] || 
                                    (
                                        field.propertyname in columntitles 
                                        ? `${columntitles[field.propertyname]} - ${field.options[optionKey] || optionKey}` 
                                        : undefined
                                    ) || 
                                    columntitles[titleIdx+idx] || 
                                    `${field.label || field.propertyname} - ${field.options[optionKey] || optionKey}`,
                                checkboxfield: field.propertyname,
                                render: this.renderCell({addLink,idx:titleIdx+idx}),
                                checkboxkey: optionKey,
                                footer: '',
                                visible
                            },
                            ...width
                        })
                    })
                } else {
                    const fieldtype = ['link','email'].includes(field.type) ? field.type: ((field.type === 'image' && displayimagesasthumbnails)?'image':'')
                    const fieldName = (fieldtype === 'image' && displayimagesasthumbnails) ? field.propertyname : ''
                    const displayValOptions = (displayvaluesinsteadofkeys && 'options' in field && typeof field.options === 'object')
                    ? {
                        displayValOptions:field.options
                    } 
                    : {}
                    data.columns.push({
                        ...{
                            class: className,
                            data: field.propertyname,
                            title: columntitles[field.propertyname] || columntitles[titleIdx] || field.label || field.propertyname,
                            render: this.renderCell({fieldtype,addLink,idx:titleIdx,fieldName}),
                            footer: '',
                            visible
                        },
                        ...width,
                        ...displayValOptions
                    })
                }
            }
        },
        registerSpecialFields(fieldsToRegister,test,params,data){
            if (Array.isArray(fieldsToRegister) && fieldsToRegister.length > 0){
                const parameters = {
                    'date_creation_fiche': {
                        paramName: 'displaycreationdate',
                        slotName: 'creationdatetranslate' 
                    },
                    'date_maj_fiche': {
                        paramName: 'displaylastchangedate',
                        slotName: 'modifiydatetranslate' 
                    },
                    'owner': {
                        paramName: 'displayowner',
                        slotName: 'ownertranslate' 
                    },
                    'id_typeannonce': {
                        paramName: '',
                        slotName: 'formidtranslate' 
                    },
                }
                fieldsToRegister.forEach((propertyName)=>{
                    if (propertyName in parameters){
                        const canPushColumn = 
                            (parameters[propertyName].slotName.length > 0)
                            ? (
                                test
                                ? (
                                    parameters[propertyName].paramName.length > 0 &&
                                    this.sanitizedParam(params,this.isadmin,parameters[propertyName].paramName)
                                )
                                : true
                            ) : false
                        if (canPushColumn){
                            data.columns.push({
                                data: propertyName,
                                title: this.getTemplateFromSlot(parameters[propertyName].slotName,{}),
                                footer: ''
                            })
                        }
                    }
                })
            }
        },
        removeRows(dataTable,newIds){
            let entryIdsToRemove = Object.keys(this.displayedEntries).filter((id)=>!newIds.includes(id))
            entryIdsToRemove.forEach((id)=>{
                if (id in this.displayedEntries){
                    delete this.displayedEntries[id]
                }
            })
            dataTable.rows((idx,data,node)=>{
                return !('id_fiche' in data) || entryIdsToRemove.includes(data.id_fiche)
            }).remove()
        },
        resolve(name){
            this.isReady[name] = true
            if (name in this.cacheResolveReject &&
                Array.isArray(this.cacheResolveReject[name])){
                const listOfResolveReject = this.cacheResolveReject[name]
                this.cacheResolveReject[name] = []
                listOfResolveReject.forEach(({resolve})=>resolve(name in this ? this[name] : null))
            }
        },
        renderCell({fieldtype='',fieldName='',addLink=false,idx=-1}){
            return (data,type,row)=>{
                if (type === 'sort' || type === 'filter'){
                    return (typeof data === 'object' && 'export' in data) ? data.export : data
                }
                const formattedData = (typeof data === 'object' && data !== null && 'display' in data) ? data.display : (data === null ? '' : String(data))
                let anchorData = 'anchorData'
                let anchorImageSpecificPart = ''
                let anchorImageOther = ''
                let anchorImageExt = ''
                let anchorOtherEntryId = ''
                if (fieldtype === 'image'){
                    if(formattedData.length > 0){
                        let regExp = new RegExp(`^(${row.id_fiche}_${fieldName}_)(.*)_(\\d{14})_(\\d{14})\\.([^.]+)$`)
                        if (regExp.test(formattedData)) {
                            let anchorImageDate1 = ''
                            let anchorImageDate2 = '';
                            [,,anchorImageSpecificPart,anchorImageDate1,anchorImageDate2,anchorImageExt] = formattedData.match(regExp)
                            anchorImageOther = `${anchorImageDate1}_${anchorImageDate2}`
                            anchorData = 'entryIdAnchor_fieldNameAnchor_anchorImageSpecificPart_anchorImageOther.anchorImageExt'
                        } else {
                            regExp = new RegExp(`^(${row.id_fiche}_${fieldName}_)(.*)\\.([^.]+)$`)
                            if (regExp.test(formattedData)) {
                                [,,anchorImageSpecificPart,anchorImageExt] = formattedData.match(regExp)
                                anchorData = 'entryIdAnchor_fieldNameAnchor_anchorImageSpecificPart.anchorImageExt'
                            } else {
                                // maybe from other entry
                                regExp = new RegExp(`^([A-Za-z0-9-_]+)(_${fieldName}_)(.*)_(\\d{14})_(\\d{14})\\.([^.]+)$`)
                                if (regExp.test(formattedData)) {
                                    let anchorImageDate1 = ''
                                    let anchorImageDate2 = '';
                                    [,anchorOtherEntryId,,anchorImageSpecificPart,anchorImageDate1,anchorImageDate2,anchorImageExt] = formattedData.match(regExp)
                                    anchorImageOther = `${anchorImageDate1}_${anchorImageDate2}`
                                    anchorData = 'anchorOtherEntryId_fieldNameAnchor_anchorImageSpecificPart_anchorImageOther.anchorImageExt'
                                } else {
                                    // last possible format
                                    regExp = new RegExp('^(.*)\\.([^.]+)$')
                                    if (regExp.test(formattedData)) {
                                        [,anchorImageSpecificPart,anchorImageExt] = formattedData.match(regExp)
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
                }
                const template = this.getTemplateFromSlot('rendercell',{
                    anchorData,
                    fieldtype,
                    addLink,
                    entryId:'entryIdAnchor',
                    fieldName, 
                    url:'anchorUrl',
                    color: (idx === 0 && row.color.length > 0) ? 'lightslategray' : '',
                    icon: (idx === 0 && row.icon.length > 0) ? 'iconAnchor' : ''
                })
                return template.replace(/anchorData/g,formattedData.replace(/\n/g,'<br/>'))
                  .replace(/entryIdAnchor/g,row.id_fiche)
                  .replace(/anchorUrl/g,row.url)
                  .replace(/lightslategray/g,row.color)
                  .replace(/iconAnchor/g,row.icon)
                  .replace(/fieldNameAnchor/g,fieldName)
                  .replace(/anchorImageSpecificPart/g,anchorImageSpecificPart)
                  .replace(/anchorImageOther/g,anchorImageOther)
                  .replace(/anchorImageExt/g,anchorImageExt)
                  .replace(/anchorOtherEntryId/g,anchorOtherEntryId)
            }
        },
        async sanitizedParamAsync(name){
            return this.sanitizedParam(await this.waitFor('params'),this.isadmin,name)
        },
        sanitizedParam(params,isAdmin,name){
            switch (name) {
                case 'displayadmincol':
                case 'displaycreationdate':
                case 'displaylastchangedate':
                case 'displayowner':
                    const paramValue = (
                            name in params && 
                            typeof params[name] === 'string' && 
                            ['yes','onlyadmins'].includes(params[name]))
                        ? params[name]
                        : false
                    switch (paramValue) {
                        case 'onlyadmins':
                            return [1,true,'1','true'].includes(isAdmin)
                        case 'yes':
                            return true
                        case false:
                        default:
                            return false
                    }
                case 'checkboxfieldsincolumns':
                    // default true
                    return name in params ? !([false,0,'0','false'].includes(params[name])) : true
                case 'displayvaluesinsteadofkeys':
                case 'exportallcolumns':
                case 'displayimagesasthumbnails':
                    // default false
                    return name in params ? [true,1,'1','true'].includes(params[name]) : false
                    
                case 'columnfieldsids':
                case 'sumfieldsids':
                    return (name in params && typeof params[name] === 'string')
                        ? params[name].split(',').map((v)=>v.trim())
                        : []
                case 'columntitles':
                    const columntitlesastab = (name in params && typeof params[name] === 'string')
                        ? params[name].split(',').map((v)=>v.trim())
                        : []
                    const columntitles = {}
                    columntitlesastab.forEach((val,idx)=>{
                        const match = val.match(/^([A-Za-z0-9\-_]+)=(.+$)/)
                        if (match){
                            const [,key,title] = match
                            columntitles[key] = title
                        } else {
                            columntitles[idx] = val
                        }
                    })
                    return columntitles
                case 'sumfieldsids':
                    return (name in params && typeof params[name] === 'string')
                        ? params[name].split(',').map((v)=>v.trim())
                        : []
                case 'columnswidth':
                    const columnswidth = {}
                    if (
                        name in params && 
                        typeof params[name] === 'string'
                    ) {
                        params[name].split(',').forEach((extract)=>{
                            const [name,value] = extract.split('=',2)
                            if (name && value && name.length > 0 && value.length > 0){
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
        sanitizeValue(val) {
          let sanitizedValue = val
          if (Object.prototype.toString.call(val) === '[object Object]') {
            // because if orthogonal data is defined, value is an object
            sanitizedValue = val.display || ''
          }
          return (isNaN(sanitizedValue)) ? 1 : Number(sanitizedValue)
        },
        startDelete(event){
            if (!multiDeleteService.isRunning) {
                multiDeleteService.isRunning = true
                const elem = event.target
                if (elem) {
                    $(elem).attr('disabled', 'disabled')
                    multiDeleteService.deleteItems(elem)
                }
            }
        },
        async updateEntries(newEntries,newIds){
            const columns = await this.getColumns()
            const dataTable = await this.getDatatable()
            const currentusername = this.currentusername
            this.removeRows(dataTable,newIds)
            this.addRows(dataTable,columns,newEntries,currentusername,this.isadmin)
            this.dataTable.draw()
        },
        updateFieldsFromRoot(){
            this.fields = this.$root.formFields
            if (Object.keys(this.fields).length > 0){
                this.resolve('fields')
            }
        },
        updateFooter(){
            if (this.dataTable !== null){
                const activatedRows = []
                this.dataTable.rows({ search: 'applied' }).every(function() {
                  activatedRows.push(this.index())
                })
                this.dataTable.columns('.sum-activated').every((indexCol) => {
                    let col = this.dataTable.column(indexCol)
                    let sum = 0
                    activatedRows.forEach((indexRow) => {
                      const value = this.dataTable.row(indexRow).data()[col.dataSrc()]
                      sum += this.sanitizeValue(Number(value))
                    })
                    this.dataTable.footer().to$().find(`> tr > th:nth-child(${indexCol+1})`).html(sum)
                })
            }
        },
        async waitFor(name){
            if (this.isReady[name]){
                return this[name] || null
            }
            if (!(name in this.cacheResolveReject)){
                this.cacheResolveReject[name] = []
            }
            const promise = new Promise((resolve,reject)=>{
                this.cacheResolveReject[name].push({resolve,reject})
            })
            return await promise.then((...args)=>Promise.resolve(...args)) // force .then()
        }
    },
    mounted(){
        $(isVueJS3 ? this.$el.parentNode : this.$el).on('dblclick',function(e) {
          return false;
        });
        this.updateFieldsFromRoot()
        window.urlImageResizedOnError = this.$root.urlImageResizedOnError
    },
    watch: {
        entries(newVal, oldVal) {
            this.updateFieldsFromRoot() // because updated in same time than entries (but not reactive)
            const sanitizedNewVal = newVal.filter((e)=>(typeof e === 'object' && e !== null && 'id_fiche' in e))
            const newIds = sanitizedNewVal.map((e) => e.id_fiche)
            const oldIds = oldVal.map((e) => e.id_fiche || '').filter((e)=>(typeof e === 'string' && e.length > 0))
            if (!this.arraysEqual(newIds, oldIds)) {
                this.updateEntries(sanitizedNewVal,newIds).catch(this.manageError)
            }
        },
        params() {
            this.resolve('params')
        },
        ready(){
            this.sanitizedParamAsync('displayadmincol').then((displayadmincol)=>{
                if (displayadmincol){
                    $(this.$refs.buttondeleteall).find(`#MultiDeleteModal${this.getUuid()}`).first().each(function(){
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
        <slot name="header" v-bind="{displayedEntries,BazarTable:this}"/>
        <table ref="dataTable" class="table prevent-auto-init table-condensed display">
            <tfoot v-if="sanitizedParam(params,isadmin,'sumfieldsids').length > 0 || sanitizedParam(params,isadmin,'displayadmincol')">
                <tr></tr>
            </tfoot>
        </table>
        <div ref="buttondeleteall">
            <slot v-if="ready && sanitizedParam(params,isadmin,'displayadmincol')" name="deleteallselectedbutton" v-bind="{uuid:getUuid(),BazarTable:this}"/>
        </div>
        <slot name="spinnerloader" v-bind="{displayedEntries,BazarTable:this}"/>
        <slot name="footer" v-bind="{displayedEntries,BazarTable:this}"/>
    </div>
  `
};

if (isVueJS3){
    if (window.hasOwnProperty('bazarVueApp')){ // bazarVueApp must be defined into bazar-list-dynamic
        if (!bazarVueApp.config.globalProperties.hasOwnProperty('wiki')){
            bazarVueApp.config.globalProperties.wiki = wiki;
        }
        if (!bazarVueApp.config.globalProperties.hasOwnProperty('_t')){
            bazarVueApp.config.globalProperties._t = _t;
        }
        window.bazarVueApp.component(componentName,componentParams);
    }
} else {
    if (!Vue.prototype.hasOwnProperty('wiki')){
        Vue.prototype.wiki = wiki;
    }
    if (!Vue.prototype.hasOwnProperty('_t')){
        Vue.prototype._t = _t;
    }
    Vue.component(componentName,componentParams);
}