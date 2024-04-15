const rootsElementsRaw = document.getElementsByClassName('selector_is_recurrent')
let rootsElements = []
for (let index = 0; index < rootsElementsRaw.length; index++) {
    rootsElements.push(rootsElementsRaw.item(index).parentNode)
}
let isVueJS3 = (typeof Vue.createApp == "function");

const defaultNbMax = 300
const daysToCodeAssoc = {
    mon:1,
    tue:2,
    wed:3,
    thu:4,
    fri:5,
    sat:6,
    sun:7
}
const monthsToCodeAssoc = {
    jan: 0,
    feb: 1,
    mar: 2,
    apr: 3,
    may: 4,
    jun: 5,
    jul: 6,
    aug: 7,
    sep: 8,
    oct: 9,
    nov: 10,
    dec: 11
}
const wantedPositionList = {
    fisrtOfMonth:1,
    secondOfMonth:2,
    thirdOfMonth:3,
    forthOfMonth:4,
    lastOfMonth:99
}

let appParams = {
    components: {},
    data() {
        return {
            availableExcept: [],
            canCustomizeRepetition: false,
            datePickerForLimitInternal:null,
            days:['mon'],
            endDateLimitTime: -1,
            except:[],
            month:'',
            newExcept:'',
            nbmax:defaultNbMax,
            nth:'',
            recurrenceBaseId: '',
            repetitionInternal: '',
            showRange: false,
            startDateInputInternal : null,
            stepInternal:2,
            whenInMonth:''
        }
    },
    computed:{
        availableExceptFiltered(){
            return this.availableExcept.filter((elem)=>!this.except.includes(elem))
        },
        baseElement(){
            return this.element?.getElementsByClassName('selector_is_recurrent')?.[0]
        },
        dataset(){
            return this.baseElement?.dataset?.data
        },
        datePickerForLimit(){
            let datePicker = this.datePickerForLimitInternal
            if (datePicker === null){
                let parentOfDatePicker = null
                let nextS = this.baseElement
                do {
                    nextS = nextS.nextSibling
                    if (nextS?.classList?.contains('input-prepend')){
                        parentOfDatePicker = nextS
                    }
                } while (parentOfDatePicker === null && nextS !== null);
                datePicker = parentOfDatePicker?.querySelector('input[name="bf_date_fin_evenement_data[limitdate]"]')
                this.datePickerForLimitInternal = datePicker
            }
            return datePicker
        },
        element(){
            return isVueJS3 ? this.$el.parentNode : this.$el
        },
        isRecurrent(){
            return this.repetition !== ''
        },
        mainParentElement(){
            return this.baseElement?.parentNode?.parentNode
        },
        repetition(){
            switch (this.repetitionInternal) {
                case 'd':
                case 'xd':
                    return 'd'
                case 'w':
                case 'xw':
                    return 'w'
                case 'm':
                case 'xm':
                    return 'm'
                case 'y':
                case 'xy':
                    return 'y'
                default:
                    return ''
            }
        },
        startDateInput(){
            if (this.startDateInputInternal === null){
                this.startDateInputInternal = document.getElementById('bf_date_debut_evenement')
            }
            return this.startDateInputInternal
        },
        step(){
            switch (this.repetitionInternal) {
                case 'd':
                case 'w':
                case 'm':
                case 'y':
                    return 1
                case 'xd':
                case 'xw':
                case 'xm':
                case 'xy':
                default:
                    return this.stepInternal
            }
        }
    },
    methods:{
        calculateAvailableExcept(upToLimit = false){
            const currentStartDate = this.getCurrentStartDate()
            if (typeof currentStartDate !== 'string' || currentStartDate.length === 0){
                return []
            }
            let date = new Date(currentStartDate)
            if (date.toString() === 'Invalid Date'){
                return []
            }

            const except = []
            const nbStep = upToLimit ? 300 : this.nbmax
            for (let i = 0; i < nbStep; i++) {
                let nextStartDate = null
                switch (this.repetition) {
                    case 'd':
                        nextStartDate = new Date(date.getTime()+Number(this.step)*24*3600*1000)
                        break
                    case 'w':
                        const currentStartDayCode = date.getDay() || 7
                        const daysTocode = this.days.map((d)=>daysToCodeAssoc[d] ?? 1)
                        let nextWantedDay = null
                        const maxDaysToCode = daysTocode.reduce((acc,val)=>Math.max(acc,val),1)
                        if (!daysTocode.includes(currentStartDayCode) || currentStartDayCode === maxDaysToCode){
                            nextWantedDay = daysTocode.reduce((acc,val)=>Math.min(acc,val),7)
                            nextStartDate = new Date(date.getTime())
                            nextStartDate.setDate(nextStartDate.getDate()+nextWantedDay+7*(Number(this.step)-1)+7-currentStartDayCode)
                        } else {
                            nextWantedDay = daysTocode.filter((d)=>d > currentStartDayCode).reduce((acc,val)=>Math.min(acc,val),7)
                            nextStartDate = new Date(date.getTime())
                            nextStartDate.setDate(nextStartDate.getDate()+nextWantedDay-currentStartDayCode)
                        }
                        break
                    case 'm':
                        let {nextStartMonth,currentStartYear} = this.calculateNextMonth(date.getMonth(),date.getFullYear(),Number(this.step))
                        nextStartDate = this.findNextStartDate(date,currentStartYear,nextStartMonth,(m,y)=>{
                            return this.calculateNextMonth(m,y,Number(this.step))
                        })
                        break
                    case 'y':
                        nextStartDate = this.findNextStartDate(date,date.getFullYear()+Number(this.step),monthsToCodeAssoc?.[this.month] ?? 0,(m,y)=>{
                            return {
                                currentStartYear:y+Number(this.step),
                                nextStartMonth:m
                            }
                        })
                        break
                    default:
                        break
                }
                if (nextStartDate === null
                    || nextStartDate.toString() === 'Invalid Date'){
                    return except
                }
                date = nextStartDate
                if ( this.endDateLimitTime < 0
                     || nextStartDate.getTime() <= this.endDateLimitTime
                    ){
                    // work in UTC because ISO string is splitted
                    except.push((new Date(Date.UTC(
                        date.getFullYear(),
                        date.getMonth(),
                        date.getDate(),
                        date.getHours(),
                        date.getMinutes(),
                        date.getSeconds()
                    ))).toISOString().slice(0,10))
                }
            }
            return except
        },
        calculateNextMonth(nextStartMonth,currentStartYear,step)
        {
            const newMonth = nextStartMonth + step
            return newMonth > 11
                ? {
                    nextStartMonth:newMonth-12,
                    currentStartYear:currentStartYear + 1
                }
                : {
                    nextStartMonth:newMonth,
                    currentStartYear
                }
        },
        getNbDaysInMonth(year,month){
            let firstDayOfMonth = new Date(year,month)
            firstDayOfMonth.setDate(1)
            let lastDayOfMonth = new Date(year+(month === 12 ? 1 : 0),(month % 12)+1)
            lastDayOfMonth.setDate(0)
            return Math.round((lastDayOfMonth-firstDayOfMonth)/1000/3600/24)+1
        },
        findNextStartDate(date,startYear,startMonth,callback){
            if (this.whenInMonth === 'nthOfMonth'){
                let limit = 60;
                let currentStartYear = startYear
                let nextStartMonth = startMonth
                const nth = this.nth || 1
                while(limit > 0 && nth > this.getNbDaysInMonth(currentStartYear,nextStartMonth)){
                    const data = callback(nextStartMonth,currentStartYear)
                    nextStartMonth = data?.nextStartMonth ?? nextStartMonth
                    currentStartYear = data?.currentStartYear ?? currentStartYear
                    limit = limit -1
                }
                let newStartDate = new Date(date.getTime())
                newStartDate.setFullYear(currentStartYear,nextStartMonth,nth)
                return newStartDate
            } else {
                const wantedPosition = wantedPositionList?.[this.whenInMonth] ?? 1
                const nbDaysInMonth = this.getNbDaysInMonth(startYear,startMonth)
                const day = this.days.reduce((acc,d)=>Math.min(acc,daysToCodeAssoc?.[d] ?? 7),7)
                let counter = 0;
                let testedDate = new Date(date.getTime())
                testedDate.setFullYear(startYear,startMonth)
                let newStartDate = new Date(date.getTime())
                for (let curDay = 1; curDay <= nbDaysInMonth; curDay++) {
                    if (counter < wantedPosition){
                        testedDate.setDate(curDay)
                        if ((testedDate.getDay() || 7) === day){
                            counter = counter + 1;
                            newStartDate = new Date(testedDate.getTime())
                        }
                    }
                }
                return newStartDate
            }
        },
        getCurrentStartDate(){
            return this.startDateInput?.value ?? ''
        },
        getCurrentStartDay(){
            const dateStr = this.getCurrentStartDate()
            if (dateStr === ''){
                return ''
            }
            const date = new Date(dateStr)
            if (date.toString() === 'Invalid Date') {
                return ''
            }
            const day = date.getDay()
            switch (day) {
                case 0:
                    return 'sun'
                case 1:
                    return 'mon'
                case 2:
                    return 'tue'
                case 3:
                    return 'wed'
                case 4:
                    return 'thu'
                case 5:
                    return 'fri'
                case 6:
                    return 'sat'
                default:
                    return ''
            }
        },
        registerChangeOnStartDateInput(){
            this.startDateInput.addEventListener('blur',()=>{
                setTimeout(()=>{this.setCurrentDayIfWeek()},200)
                this.updateAvailableExceptUpdatingNbMax()
            })
        },
        setCurrentDayIfWeek(){
            if (this.repetitionInternal?.match(/w$/)){
                const day = this.getCurrentStartDay()
                if (day !== '' && !this.days.includes(day)){
                    if (day !== 'mon' && this.days.length === 1 && this.days.includes('mon')){
                        this.days = [day]
                    } else {
                        this.days.push(day)
                    }
                }
            }
        },
        toggleDay(key){
            if (this.repetition === 'w'){
                if (this.days.includes(key) && key !== this.getCurrentStartDay()){
                    this.days = this.days.filter((elem)=>elem != key)
                } else {
                    this.days.push(key)
                }
            } else {
                this.days = [key]
            }
        },
        updateAvailableExcept(){
            this.availableExcept = this.calculateAvailableExcept()
        },
        updateAvailableExceptUpdatingNbMax(){
            const newAvailableExcept = this.calculateAvailableExcept(true)
            if (this.endDateLimitTime > 0
                && this.nbmax < 300
                && newAvailableExcept.length > this.nbmax){
                this.nbmax = Math.min(300, newAvailableExcept.length)
            } else {
                this.updateAvailableExcept()
            }
        },
        updateEndDateLimitTime(newVal = null){
            const endDateLimit = (newVal === null)
                ? (
                    (this.datePickerForLimit.value.length > 0)
                    ? new Date(this.datePickerForLimit.value)
                    : null
                )
                : new Date(newVal)
            this.endDateLimitTime = (endDateLimit === null
                || endDateLimit.toString() === 'Invalid Date')
                ? -1
                : endDateLimit.getTime()
            this.updateAvailableExceptUpdatingNbMax()
        }
    },
    mounted(){
        const data = JSON.parse(this.dataset)
        const limitdate =  data?.limitdate ?? ''
        $(this.datePickerForLimit).on('changeDate',(event)=>{
            this.updateEndDateLimitTime(event.date)
        })
        if (limitdate && 'value' in this.datePickerForLimit){
            this.datePickerForLimit.value = limitdate
            this.updateEndDateLimitTime()
        } else {
            this.updateAvailableExcept()
        }
        if (data?.isRecurrent !== '1'){
            this.recurrenceBaseId = (
                typeof data === 'string'
                && data.match(/^\{"recurrentParentId":"([^"]+)"}$/)
            )
            ? data.replace(/^\{"recurrentParentId":"([^"]+)"}$/,'$1')
            : ''
        }
        const step = data?.step ?? 2
        this.stepInternal = step
        const repetition = data?.repetition ?? ''
        this.repetitionInternal = 
            (data?.isRecurrent === '1' && ['d','y','w','m'].includes(repetition))
            ? (
                Number(step) === 1
                ? repetition
                : `x${repetition}`
            )
            : ''
        if (repetition.length > 0 && Number(step) > 1){
            this.canCustomizeRepetition = true
        }
        this.whenInMonth =  data?.whenInMonth ?? ''
        this.month =  data?.month ?? ''
        this.nth =  data?.nth ?? ''
        const nbmax =  Number(data?.nbmax ?? defaultNbMax)
        this.nbmax = (nbmax && nbmax > 0) ? nbmax : defaultNbMax
        this.days = Array.isArray(data?.days)
            ? data.days
            : ['mon']
        this.month = data?.month ?? ''
        this.except = Array.isArray(data?.except) ? data?.except : []
        this.mainParentElement?.classList?.add('ready')
    },
    watch: {
        days(){
            this.updateAvailableExceptUpdatingNbMax()
        },
        month(){
            this.updateAvailableExceptUpdatingNbMax()
        },
        nbmax(){
            this.updateAvailableExceptUpdatingNbMax()
        },
        newExcept(newValue){
            if (newValue.length > 0){
                if (!this.except.includes(newValue)
                    && this.availableExceptFiltered.includes(newValue)){
                    this.except.push(newValue)
                }
                this.newExcept = ''
            }
        },
        repetition(repetition){
            if (repetition !== 'w' && this.days?.length > 1){
                this.days = [this.days[0]]
            }
            this.updateAvailableExceptUpdatingNbMax()
        },
        repetitionInternal(repetition, previousValue){
            if (repetition === 'activateCustom'){
                this.canCustomizeRepetition = true
                this.repetitionInternal = previousValue
            } else if (repetition === 'removeCustom'){
                this.canCustomizeRepetition = false
                this.repetitionInternal = previousValue.replace('x', '')
            }
            this.setCurrentDayIfWeek()
        },
        step(){
            this.updateAvailableExceptUpdatingNbMax()
        }
    }
}
if (isVueJS3){
    let app = Vue.createApp(appParams);
    app.config.globalProperties.wiki = wiki;
    app.config.globalProperties._t = _t;
    rootsElements.forEach(elem => {
        app.mount(elem);
    });
} else {
    Vue.prototype.wiki = wiki;
    Vue.prototype._t = _t;
    rootsElements.forEach(elem => {
        new Vue({
            ...{el:elem},
            ...appParams
        });
    });
}