const rootsElementsRaw = document.getElementsByClassName('selector_is_recurrent')
let rootsElements = []
for (let index = 0; index < rootsElementsRaw.length; index++) {
    rootsElements.push(rootsElementsRaw.item(index).parentNode)
}
let isVueJS3 = (typeof Vue.createApp == "function");

const maxForNbMax = 300

let appParams = {
    components: {},
    data() {
        return {
            datePickerForLimitInternal:null,
            days:['mon'],
            month:'',
            nbmax:maxForNbMax,
            nth:'',
            recurrenceBaseId: '',
            repetitionInternal: '',
            showRange: false,
            stepInternal:2,
            whenInMonth:''
        }
    },
    computed:{
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
        repetition(){
            switch (this.repetitionInternal) {
                case 'd':
                case 'xd':
                    return 'd'
                case 'w':
                case '2w':
                case 'xw':
                    return 'w'
                case 'm':
                case '2m':
                case '3m':
                case 'xm':
                    return 'm'
                case 'y':
                case 'xy':
                    return 'y'
                default:
                    return ''
            }
        },
        step(){
            switch (this.repetitionInternal) {
                case 'd':
                case 'w':
                case 'm':
                case 'y':
                    return 1
                case '2w':
                case '2m':
                    return 2
                case '3m':
                    return 3
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
        toggleDay(key){
            if (this.repetition === 'w'){
                if (this.days.includes(key)){
                    this.days = this.days.filter((elem)=>elem != key)
                } else {
                    this.days.push(key)
                }
            } else {
                this.days = [key]
            }
        }
    },
    mounted(){
        const data = JSON.parse(this.dataset)
        const limitdate =  data?.limitdate ?? ''
        if (limitdate && 'value' in this.datePickerForLimit){
            this.datePickerForLimit.value = limitdate
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
            (data?.isRecurrent === '1' && ['d','y'].includes(repetition))
            ? (
                Number(step) === 1
                ? repetition
                : `x${repetition}`
            )
            : (
                ['w','m'].includes(repetition)
                ? (
                    Number(step) === 1
                    ? repetition
                    : ( Number(step) === 2
                        ? `2${repetition}`
                        : ((repetition === 'm' && Number(step) === 3)
                            ? '3m'
                            : `x${repetition}`
                        )
                    )
                )
                : ''
            )
        this.whenInMonth =  data?.whenInMonth ?? ''
        this.month =  data?.month ?? ''
        this.nth =  data?.nth ?? ''
        const nbmax =  Number(data?.nbmax ?? maxForNbMax)
        this.nbmax = (nbmax && nbmax > 0 && nbmax <= maxForNbMax) ? nbmax : maxForNbMax
        this.days = Array.isArray(data?.days)
            ? data.days
            : ['mon']
        this.month = data?.month ?? ''
    },
    watch: {
        repetition(repetition){
            if (repetition !== 'w' && this.days?.length > 1){
                this.days = [this.days[0]]
            }
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