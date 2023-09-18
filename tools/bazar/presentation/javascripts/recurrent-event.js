let rootsElements = ['.selector_is_recurrent'];
let isVueJS3 = (typeof Vue.createApp == "function");

const maxForNbMax = 50

let appParams = {
    components: {},
    data() {
        return {
            datePickerForLimitInternal:null,
            days:[],
            isRecurrent: false,
            months:[],
            nbmax:maxForNbMax,
            nth:'',
            recurrenceBaseId: '',
            repetition: '',
            step:1,
            whenInMonth:''
        }
    },
    computed:{
        datePickerForLimit(){
            let datePicker = this.datePickerForLimitInternal
            if (datePicker === null){
                let parentOfDatePicker = null
                let nextS = this.element
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
        },
        toggleMonth(key){
            this.months = [key]
        }
    },
    mounted(){
        const data = JSON.parse(this.element?.dataset?.data)
        const limitdate =  data?.limitdate ?? ''
        if (limitdate && 'value' in this.datePickerForLimit){
            this.datePickerForLimit.value = limitdate
        }
        if (data?.isRecurrent === '1'){
            this.isRecurrent = true
        } else {
            this.recurrenceBaseId = (
                typeof data === 'string'
                && data.match(/^\{"recurrentParentId":"([^"]+)"}$/)
            )
            ? data.replace(/^\{"recurrentParentId":"([^"]+)"}$/,'$1')
            : ''
        }
        this.repetition =  data?.repetition ?? ''
        this.step =  data?.step ?? 1
        this.whenInMonth =  data?.whenInMonth ?? ''
        this.month =  data?.month ?? ''
        this.nth =  data?.nth ?? ''
        const nbmax =  Number(data?.nbmax ?? maxForNbMax)
        this.nbmax = (nbmax && nbmax > 0 && nbmax <= maxForNbMax) ? nbmax : maxForNbMax
        this.days = Object.entries(data?.days ?? {})
            .filter(([,val])=>val === '1')
            .map(([idx,])=>idx)
        this.months = Object.entries(data?.months ?? {})
            .filter(([,val])=>val === '1')
            .map(([idx,])=>idx)
    },
    watch: {
        isRecurrent(isRecurrent){
            if (this.datePickerForLimit?.parentNode?.style){
                if (isRecurrent){
                    this.datePickerForLimit.removeAttribute('disabled')
                    this.datePickerForLimit.parentNode.removeAttribute('style')
                } else {
                    this.datePickerForLimit.parentNode.setAttribute('style','display:none !important;')
                    this.datePickerForLimit.setAttribute('disabled','disabled')
                }
            }
        },
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