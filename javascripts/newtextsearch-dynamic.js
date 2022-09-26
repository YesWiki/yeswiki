import SpinnerLoader from '../tools/bazar/presentation/javascripts/components/SpinnerLoader.js'

let rootsElements = ['div.search-results'];
let isVueJS3 = (typeof Vue.createApp == "function");

let appParams = {
    components: { SpinnerLoader},
    data: function() {
        return {
            doMContentLoaded: false,
            updating: true,
            searchText: "",
            results: {},
            args: {
                separator: "",
                viewtype: "modal",
                displaytext: ""
            },
            textInput: null,
            titles: {},
            doNotShowMoreFor: [],
            ready: false,
        };
    },
    methods: {
        updateSearchText: function () {
            this.searchText = $(this.textInput).val();
        },
        filterResultsAccordingType: function(results, type){
            return Object.keys(results).filter((key) => this.isResultOfType(results[key],type)) ;
        },
        isResultOfType: function(result, type){
            return (result.type == 'entry' && result.form == type)
                || (type.slice(0,4) == 'tag:' && result.tags.includes(type.slice(4)))
                || result.type == type;
        },
        updateUrl: function(searchText){
            let url = window.location.toString();
            let rewriteMode = (
                wiki &&
                typeof wiki.baseUrl == "string" &&
                !wiki.baseUrl.includes("?")
                );
            let newUrl = url;
            if (url.includes("&phrase=")){
                let urlSplitted = url.split("&phrase=");
                let textRaw = urlSplitted[1];
                let textRawSplitted = textRaw.split("&");
                let oldText = textRawSplitted[0];
                newUrl = url.replace(`&phrase=${oldText}`,`&phrase=${encodeURIComponent(searchText)}`);
            } else if (rewriteMode && url.includes("?phrase=")) {
                let urlSplitted = url.split("?phrase=");
                let textRaw = urlSplitted[1];
                let textRawSplitted = textRaw.split("&");
                let oldText = textRawSplitted[0];
                newUrl = url.replace(`?phrase=${oldText}`,`?phrase=${encodeURIComponent(searchText)}`);
            } else {
                newUrl = url.includes(rewriteMode ? '?' : '&') 
                    ? `${url}&phrase=${encodeURIComponent(searchText)}` 
                    : (
                        rewriteMode
                        ? `${url}?phrase=${encodeURIComponent(searchText)}`
                        : `${url}&phrase=${encodeURIComponent(searchText)}`
                    );
            }
            history.pushState({ filter: true }, null, newUrl);
        },
        showSeeMoreButton: function(results, type){
            let app = this;
            if (type == ''){
                return (Object.keys(results).length > 0 && Object.keys(results).length % app.args.limit == 0);
            } else if (app.doNotShowMoreFor.includes(type)) {
                return false;
            } else {
                return (app.filterResultsAccordingType(results, type).length % app.args.limit == 0);
            }
        },
        moreResults: function(event) {
            let app = this;
            event.preventDefault();
            let button = event.target;
            let type = button.dataset.type;
            let currentResults = (type == '')
                ? Object.values(app.results)
                : app.filterResultsAccordingType(app.results,type).map((key)=>{return app.results[key];})
            let previousTags = currentResults.map((page)=>page.tag).join(',');
            app.searchTextFromApi({excludes:previousTags,categories:type});
        },
        searchTextFromApi: function(extraParams = {}){
            let app = this;
            app.updating = true;
            let params = {};
            if (app.args.displaytext){
                params.displaytext = true;
            }
            if (app.args.limit > 0){
                params.limit = app.args.limit;
            }
            if (app.args.template == "newtextsearch-by-category.twig"){
                params.limitByCat = true;
            }
            if (app.args.hasOwnProperty('displayorder') && app.args.displayorder.length > 0){
                params.categories = Array.isArray(app.args.displayorder) ? app.args.displayorder.join(',') : app.args.displayorder;
            }
            if (app.args.hasOwnProperty('onlytags') && app.args.onlytags.length > 0){
                params.onlytags = app.args.onlytags.join(',');
            }
            for (const key in extraParams) {
                if (key.length > 0){
                    params[key] = extraParams[key];
                }
            }
            $.ajax({
                method: "GET",
                url: wiki.url(`api/search/${app.searchText}`),
                data: params,
                success: function(data){
                    // append data in results (which has to be cleaned before)
                    let resultsAsArray = Object.values(app.results);
                    let dataAsArray =
                        (Array.isArray(data))
                        ? data
                        : ((typeof data == "object")
                            ? Object.values(data)
                            : []
                        );
                    if (extraParams.hasOwnProperty('categories') &&
                        !extraParams.categories.includes(',') &&
                        dataAsArray.length == 0 &&
                        !app.doNotShowMoreFor.includes(extraParams.categories)
                        ){
                        app.doNotShowMoreFor.push(extraParams.categories);
                    }
                    dataAsArray.forEach((value)=>{
                        resultsAsArray.push(value);
                    });
                    let results = {};
                    resultsAsArray.forEach((value,index)=>{
                        let idx = (value.hasOwnProperty('tag') && value.tag !='') ? value.tag : index; 
                        results[idx] = value;
                    });
                    app.results = results;
                },
                error: function(xhr,status,error){
                    // do nothing
                },
                complete: function(){
                    app.updating = false;
                    app.ready = true;
                }
            });
        }
    },
    watch: {
        searchText: function (newValue,oldValue){
            let app = this;
            app.updateUrl(newValue);
            if (newValue != oldValue){
                if (newValue.length == 0){
                    this.results = {};
                    this.updating = false;
                } else {
                    // reset results
                    app.results = {};
                    app.searchTextFromApi();
                }
            } else {
                this.updating = false;
            }
        }
    },
    mounted(){
        $(isVueJS3 ? this.$el.parentNode : this.$el).on('dblclick',function(e) {
          return false;
        });
        document.addEventListener('DOMContentLoaded', () => {
            this.doMContentLoaded = true;
        });
        this.args = $(isVueJS3 ? this.$el.parentNode : this.$el).data("args");
        this.titles = $(isVueJS3 ? this.$el.parentNode : this.$el).data("titles");
        if (!this.args.hasOwnProperty('viewtype')){
            this.args.viewtype = "modal";
        }
        if (!this.args.hasOwnProperty('separator')){
            this.args.separator = "";
        }
        if (this.args.separator.length > 0) {
            this.args.displaytext = false;
        }
        if (!this.args.hasOwnProperty('displaytext') || !(this.args.displaytext !== true || this.args.displaytext !== false)){
            this.args.displaytext = !this.args.hasOwnProperty('template') || this.args.template == "newtextsearch.twig";
        }
        let forms = $('.search-form');
        if (forms != undefined && forms.length > 0){
            let form = $(forms).first();
            let textInput = $(form).find('input[type=text]:first');
            if (textInput != undefined && textInput.length > 0){
                this.textInput = textInput;
            }
        }
        if (this.textInput && this.textInput != undefined) {
            this.searchText = this.textInput.val();
            $(this.textInput).on('change',()=>this.updateSearchText());
            $(this.textInput).parent().find('input[type=submit]').on('click',(event)=>{
                event.preventDefault();
                this.updateSearchText();
            });
        } else {
            this.searchText = $(isVueJS3 ? this.$el.parentNode : this.$el).data("initialsearchtext");
        }
        if (this.searchText.length == 0){
            this.updating = false;
        }
    }
};

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