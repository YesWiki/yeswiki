const ConditionsChecking = {
    conditionsCache: [],
    fieldNamesCache: {},
    triggersCache: {},
    boolList: ['false','true'],
    operationsList: ['!(','not(','not (','(',')'],
    operationsListIncludInSpaceParenthesis: ['and','or'],
    conditionsList: ['==','!=',' in','|length ==','|length !=','|length <','|length <=','|length >=','|length >',' is empty',' is not empty'],
    pregQuote: function (input){
        return (input + '').replace(new RegExp(
            '[.\\[\\]\\^(){}!=\\\\+*?$<>|:]','g'
        ),'\\$&');
    },
    updateOperationData: function (data, rest, condition, element){
        let newIndex = (rest == undefined ) ? -1 : condition.length - rest[0].length;
        if (Object.keys(data).length == 0 || data.indexOf == undefined){
            data.indexOf = -1;
        }
        if ( newIndex > -1 && (newIndex < data.indexOf || data.indexOf < 0)){
            data.indexOf = newIndex;
            data.element = element;
            data.fullRest = rest[1];
        }
    },
    updateObject: function (data, condition, names){
        let result = {};
        if (data.indexOf < 0){
            result[names.current] = condition;
            result[names.rest] = "";
            result[names.element] = "";
        } else {
            result[names.current] = condition.substr(0,data.indexOf).trim();
            result[names.element] = data.element;
            result[names.rest] = condition.substr(data.indexOf+data.fullRest.length).trim();
        }
        return result;
    },
    getFirstOperation: function (parsingObject){
        let condition = parsingObject.restOfCondition.trim();
        let data = {};
        for (let index = 0; index < this.operationsList.length; index++) {
            let element = this.operationsList[index];
            let rest = condition.match(new RegExp(`(${this.pregQuote(element)})(.*)$`,'i'));
            this.updateOperationData(data,rest,condition,element);
        }
        for (let index = 0; index < this.operationsListIncludInSpaceParenthesis.length; index++) {
            let element = this.operationsListIncludInSpaceParenthesis[index];
            let rest = condition.match(new RegExp(`((?<= |\\)|^)${element}(?= |\\)))(.*)$`,'i'));
            this.updateOperationData(data,rest,condition,element);
        }
        return this.updateObject(data, condition, {current:"currentCondition", rest:"restOfCondition",element:"operation"});
    },
    addCondition: function (condition){
        let conditionLocal = condition.trim();
        let data = {
        };
        for (let index = 0; index < this.conditionsList.length; index++) {
            let element = this.conditionsList[index];
            let rest = conditionLocal.match(new RegExp("("+this.pregQuote(element)+")(.*)$",'i'));
            this.updateOperationData(data,rest,conditionLocal,element);
        }
        for (let index = 0; index < this.boolList.length; index++) {
            let element = this.boolList[index];
            let rest = conditionLocal.match(new RegExp("("+this.pregQuote(element)+")(.*)$",'i'));
            this.updateOperationData(data,rest,conditionLocal,element);
        }
        return this.updateObject(data, conditionLocal, {current:"leftPart", rest:"rightPart",element:"typeOfCondition"});
    },
    getCheckboxValues: function (field){
        let result = [];
        $(field).find("input[type=checkbox]").each(function(){
            if ($(this).prop('checked') == true){
                let name = $(this).attr('name');
                let value = name.match(/\[[A-Za-z0-9_-]+\]/)[0];
                value = value.substr(1,value.length-2);
                result.push(value);
            }
        });
        return result;
    },
    getCheckboxTagValues: function (field){
        let result = [];
        let value = $(field).val();
        if (value.trim() != ""){
            result = value.split(",");
        }
        return result;
    },
    getRadioValues: function (inputs){
        let result = [];
        $(inputs).each(function(){
            if ($(this).prop('checked') == true){
                result.push($(this).attr('value'));
            }
        });
        return result;
    },
    getSelectValues: function (field){
        let result = [];
        let value = $(field).val();
        if (value.trim() !=""){
            result.push(value.trim());
        }
        return result;
    },
    getFieldNameValues: function(fieldName){
        if (typeof this.fieldNamesCache[fieldName] === "undefined"){
            return [];
        }
        let fieldData = this.fieldNamesCache[fieldName];
        switch (fieldData.type) {
            case "checkbox":
                return this.getCheckboxValues(fieldData.node);
            case "checkboxtag":
                return this.getCheckboxTagValues(fieldData.node);
            case "radio":
                return this.getRadioValues(fieldData.node);
            case "select":
                return this.getSelectValues(fieldData.node);
            default:
                break;
        }
        return [];
    },
    extractValues: function(values){
        if (values.trim()==""){
            return [];
        }
        let tempValues = values.trim();
        if (tempValues.substr(0,1) == '[' && tempValues.substr(-1) == ']'){
            tempValues = tempValues.substr(1,tempValues.length - 2);
        }
        return tempValues.split(",");
    },
    commonForOperations: function(fieldName, values, extract){
        let fieldValues = this.getFieldNameValues(fieldName);
        let extractedValues = this.extractValues(values);
        for (let index = 0; index < extractedValues.length; index++) {
            if (extract.uniqueValues.indexOf(extractedValues[index].trim()) == -1){
                extract.uniqueValues.push(extractedValues[index].trim());
            }
        }
        for (let index = 0; index < fieldValues.length; index++) {
            if (extract.uniqueFieldValues.indexOf(fieldValues[index].trim()) == -1){
                extract.uniqueFieldValues.push(fieldValues[index].trim());
            }
        }
    },
    isLength: function (fieldName, values, operation){
        if (isNaN(values)){
            return false;
        }
        let fieldValues = this.getFieldNameValues(fieldName);
        let uniqueFieldValues = [];
        for (let index = 0; index < fieldValues.length; index++) {
            if (uniqueFieldValues.indexOf(fieldValues[index].trim()) == -1){
                uniqueFieldValues.push(fieldValues[index].trim());
            }
        }
        let length = fieldValues.length;
        let number = Number(values);
        return eval(length+" "+operation+" "+number);
    },
    isEqual: function (fieldName, values){
        let extract = {
            uniqueValues: [],
            uniqueFieldValues: []
        };
        this.commonForOperations(fieldName, values, extract);
        if (extract.uniqueValues.length != extract.uniqueFieldValues.length){
            return false;
        }
        let result = true;
        for (let index = 0; index < extract.uniqueFieldValues.length; index++) {
            if (extract.uniqueValues.indexOf(extract.uniqueFieldValues[index]) == -1){
                result = false
            }
        }
        return result;
    },
    isUnEqual: function (fieldName, values){
        let extract = {
            uniqueValues: [],
            uniqueFieldValues: []
        };
        this.commonForOperations(fieldName, values, extract);
        if (extract.uniqueValues.length != extract.uniqueFieldValues.length){
            return true;
        }
        let result = false;
        for (let index = 0; index < extract.uniqueFieldValues.length; index++) {
            if (extract.uniqueValues.indexOf(extract.uniqueFieldValues[index]) == -1){
                result = true;
            }
        }
        return result;
    },
    isIn: function (fieldName, values){
        let extract = {
            uniqueValues: [],
            uniqueFieldValues: []
        };
        this.commonForOperations(fieldName, values, extract);
        if (extract.uniqueFieldValues.length == 0){
            return false;
        }
        let result = false;
        for (let index = 0; index < extract.uniqueFieldValues.length; index++) {
            if (extract.uniqueValues.indexOf(extract.uniqueFieldValues[index]) > -1){
                result = true;
            }
        }
        return result;
    },
    isEmpty: function (fieldName){
        return this.isEqual(fieldName,"");
    },
    isNotEmpty: function (fieldName){
        return this.isUnEqual(fieldName,"");
    },
    renderCondition: function(structuredCondition){
        if (typeof structuredCondition.leftPart !== "undefined" &&
            typeof structuredCondition.rightPart !== "undefined" &&
            typeof structuredCondition.typeOfCondition !== "undefined"){
            return this.renderConditionSecured(
                structuredCondition.leftPart.trim(),
                structuredCondition.typeOfCondition.trim(),
                structuredCondition.rightPart.trim()
            );
        } else {
            console.log(structuredCondition)
        }
        return "";
    },
    renderConditionSecured: function(fieldName, condition, values){
        switch (condition) {
            case "==":
                return ` this.isEqual("${fieldName}","${values}")`;
            case "!=":
                return ` this.isUnEqual("${fieldName}","${values}")`;
            case 'in':
                return ` this.isIn("${fieldName}","${values}")`;
            case 'is empty':
                return ` this.isEmpty("${fieldName}")`;
            case 'is not empty':
                return ` this.isNotEmpty("${fieldName}")`;
            case '|length ==':
            case '|length !=':
            case '|length <':
            case '|length <=':
            case '|length >':
            case '|length >=':
                return ` this.isLength("${fieldName}","${values}","${condition.substr("|length ".length)}")`;
            case 'false':
                return ' false ';
            case 'true':
                return ' true ';
            case '':
                return '';
            default:
                break;
        }
        return " false ";
    },
    renderBadFormatingError: function (structuredCondition,conditionData){
        if (typeof structuredCondition.leftPart !== "undefined" && structuredCondition.leftPart.length != 0) {
            console.warn(`Left part ('${structuredCondition.leftPart}') should be empty before '${structuredCondition.operation}' in '${conditionData.condition}'`);
            return true;
        }
        return false;
    },
    emptyCheckbox: function (element){
        $(element).find("input[type=checkbox]").each(function(){
            $(this).prop("checked",false);
            $(this).trigger("change");
        });
    },
    setDefaultCheckbox: function (element){
        $(element).find("input[type=checkbox]:not([data-default])").each(function(){
            $(this).prop("checked",false);
            $(this).trigger("change");
        });
        $(element).find("input[type=checkbox][data-default]").each(function(){
            let defaultVal = $(this).data('default');
            if (defaultVal == "checked"){
                $(this).prop("checked",true);
                $(this).trigger("change");
            }
        });
    },
    emptySelect: function (element){
        $(element).find("select").each(function(){
            $(this).val('');
            $(this).trigger("change");
        });
    },
    setDefaultSelect: function (element){
        $(element).find("select[data-default]").each(function(){
            let defaultVal = $(this).data('default');
            let val = $(this).val();
            if (defaultVal != undefined){
                $(this).val(defaultVal);
                $(this).trigger("change");
            }
        });
    },
    emptyTextarea: function (element){
        $(element).find("textarea").each(function(){
            $(this).val('');
            $(this).trigger("change");
        });
    },
    emptyRadio: function (element){
        // warning it unselect the radio button but this will not erase previous saved value
        // it is needed to have a new value to erase it
        $(element).find("input[type=radio]").each(function(){
            $(this).prop("checked",false);
            $(this).trigger("change");
        });
    },
    setDefaultRadio: function (element){
        $(element).find("input[type=radio]:not([data-default])").each(function(){
            $(this).prop("checked",false);
            $(this).trigger("change");
        });
        $(element).find("input[type=radio][data-default]").each(function(){
            let defaultVal = $(this).data('default');
            if (defaultVal == "checked"){
                $(this).prop("checked",true);
                $(this).trigger("change");
            }
        });
    },
    emptyTextarea: function (element){
        $(element).find("textarea").each(function(){
            $(this).val('');
            $(this).trigger("change");
        });
    },
    emptyGeocode: function (element){
        $(element).find("div[class*=\"geocode-input\"] input[type=hidden]").each(function(){
            $(this).val('');
            $(this).trigger("change");
        });
    },
    emptyImage: function (element){
        $(element).find("div[class*=\"bazar-entry-edit-image\"]").each(function(){
            // currently not activated because ImageField is not safe
            // TODO activate and TEST (prefer usage of ajax)
            // $(this).find('output').html("");
            // $(this).find('input[id^=data-][type=hidden]').val("");
            // $(this).find('input[id^=filename-][type=hidden]').val("");
            // $(this).find('input[id^=oldimage-][type=hidden]').val("");
            // $(this).find('input[type=file]').val(" "); // works only if after this.emptyOthersInputs
        });
    },
    hasTagsInput: function (element){
        let result = false;
        Object.values($(element)[0]).forEach(param => {
            if (Object.keys(param).includes('tagsinput')) {
                result = true;
            }
        });
        return result;
    },
    emptyByTags: function (element) {
        $(element).find("input.yeswiki-input-entries").each(function(){
            if (ConditionsChecking.hasTagsInput(this)){
                $(this).tagsinput('removeAll');
            }
        });
    },
    setDefaultByTags: function (element){
        $(element).find("input.yeswiki-input-entries").each(function(){
            let propertyName = $(this).prop('name');
            let val = $(this).val();
            if (val.length == 0 && propertyName.length > 0 &&
                typeof bazarlistTagsInputsData !== "undefined" && 
                bazarlistTagsInputsData[propertyName] != undefined &&
                ConditionsChecking.hasTagsInput(this)
                ){
                let selectedOptions = bazarlistTagsInputsData[propertyName].selectedOptions || [];
                let existingTags = bazarlistTagsInputsData[propertyName].existingTags || [];
                $(this).tagsinput('removeAll');
                selectedOptions.forEach(tag => {
                    if (existingTags[tag] != undefined){
                        $(this).tagsinput('add',existingTags[tag]);
                    }
                });
            }
        });
    },
    emptyOthersInputs: function (element){
        $(element).find("input:not([type=checkbox]):not([type=radio]):not([type=hidden]):not(.yeswiki-input-entries)").each(function(){
            $(this).val('');
            $(this).trigger("change");
        });
    },
    emptyChildren: function (element){
        this.emptyCheckbox(element);
        this.emptySelect(element);
        this.emptyTextarea(element);
        this.emptyRadio(element);
        this.emptyGeocode(element);
        this.emptyByTags(element);
        this.emptyOthersInputs(element);
        // this.emptyImage(element);
        // do not work for FileField also
    },
    setDefaultChildren: function (element){
        this.setDefaultSelect(element);
        this.setDefaultRadio(element);
        this.setDefaultCheckbox(element);
        this.setDefaultByTags(element);
    },
    resolveCondition: function (id, cleanSubelements = true, elemsToClean = {}){
        if (typeof this.conditionsCache[id] !== "undefined"){
            let conditionData = this.conditionsCache[id];
            let stack = [];
            for (let key in conditionData.structuredConditions) {
                stack.push(conditionData.structuredConditions[key]);
            }
            let stringToEval = "";
            let errorFound = false;
            while (stack.length > 0 && !errorFound) {
                let structuredCondition = stack[0];
                stack.splice(0,1);
                switch (structuredCondition.operation) {
                    case "(":
                    case "!(":
                        if (this.renderBadFormatingError(structuredCondition,conditionData)) {
                            errorFound = true;
                        } else {
                            stringToEval = stringToEval+structuredCondition.operation
                        }
                        break;
                    case "not(":
                    case "not (":
                        if (this.renderBadFormatingError(structuredCondition,conditionData)) {
                            errorFound = true;
                        } else {
                            stringToEval = stringToEval+"!("
                        }
                        break;
                    case ")":
                        stringToEval = stringToEval+this.renderCondition(structuredCondition)+structuredCondition.operation
                        break;
                    case "and":
                        stringToEval = stringToEval+this.renderCondition(structuredCondition)+"&&";
                        break;
                    case "or":
                        stringToEval = stringToEval+this.renderCondition(structuredCondition)+"||";
                        break;
                    default:
                        if (stack.length > 0){
                            errorFound = true;
                            console.warn(`Unknown operation '${structuredCondition.operation}' in '${conditionData.condition}'`);
                        }
                        stringToEval = stringToEval+this.renderCondition(structuredCondition);
                        break;
                }
            }
            let display = false;
            try {
                display = errorFound ? false : eval(stringToEval);
            } catch (error) {
                console.warn(error);
                display = false;
            }
            // for debug console.log(stringToEval+" => "+display)
            // extract no clean param
            let clean = $(conditionData.node).data('noclean') != true;
            if (display) {
                let previousStateVisible = ($(conditionData.node).filter(':visible').length > 0);
                $(conditionData.node).show();
                window.dispatchEvent(new Event('resize')); // needed to refresh map for geolocalization
                if (clean && !previousStateVisible) {
                    if (cleanSubelements){
                        this.setDefaultChildren(conditionData.node);
                    } else {
                        elemsToClean[id] = false;
                    }
                }
            } else {
                $(conditionData.node).hide();
                if (clean) {
                    if (cleanSubelements){
                        this.emptyChildren(conditionData.node);
                    } else {
                        elemsToClean[id] = true;
                    }
                }
            }
        }
    },
    resolveTrigger: function (inputId){
        if (typeof this.triggersCache[inputId] !== "undefined"){
            let fieldsNames = this.triggersCache[inputId];
            let conditionsIds = [];
            for (let index = 0; index < fieldsNames.length; index++) {
                let fieldName = fieldsNames[index];
                if (typeof this.fieldNamesCache[fieldName] !== "undefined"){
                    let fieldData = this.fieldNamesCache[fieldName];
                    for (let indexCondition = 0; indexCondition < fieldData.conditionIds.length; indexCondition++) {
                        let id = fieldData.conditionIds[indexCondition];
                        if (conditionsIds.indexOf(id) < 0){
                            conditionsIds.push(id);
                        }
                    }
                }
            }
            for (let index = 0; index < conditionsIds.length; index++) {
                let id = conditionsIds[index];
                this.resolveCondition(id);
            }
        }
    },
    registerTrigger: function (input, fieldName){
        let inputId = $(input).attr('id');
        if (typeof this.triggersCache[inputId] === "undefined"){
            this.triggersCache[inputId] = [fieldName];
            $(input).on('change',function(){
                ConditionsChecking.resolveTrigger(inputId);
            });
        } else if (this.triggersCache[inputId].indexOf(fieldName) < 0) {
            this.triggersCache[inputId].push(fieldName);
        }
    },
    findCheckbox: function(fieldName,result){
        if (result.type != ""){
            return result;
        }
        let node = $(`div[class*="group-checkbox-"][class*="${fieldName}"]`).filter(
            function(index) {
                let classes = $(this).attr('class').split(" ");
                return classes.filter(function(className){
                    return className.slice(-fieldName.length) == fieldName;
                }).length > 0;
            }
        );
        if (node.length > 0){
            let inputs = $(node).find('input[type=checkbox]');
            if (inputs.length > 0 ){
                result.type = "checkbox";
                result.node = node;
                // register triggers
                $(inputs).each(function(){
                    ConditionsChecking.registerTrigger(this,fieldName);
                });
            }
        }
        return result;
    },
    findCheckboxTag: function(fieldName,result){
        if (result.type != ""){
            return result;
        }
        let node = $(`input[class$=${fieldName}].yeswiki-input-entries`);
        if (node.length > 0){
            result.type = "checkboxtag";
            result.node = node;
            // register triggers
            ConditionsChecking.registerTrigger(node,fieldName)
        }
        return result;
    },
    findList: function(fieldName,result){
        if (result.type != ""){
            return result;
        }
        let node = $(`select[name$=${fieldName}]`);
        if (node.length > 0){
            result.type = "select";
            result.node = node;
            // register triggers
            ConditionsChecking.registerTrigger(node,fieldName);
        }
        return result;
    },
    findRadio: function(fieldName,result){
        if (result.type != ""){
            return result;
        }
        let inputs = $(`input[name$=${fieldName}][type=radio]`);
        if (inputs.length > 0){
            result.type = "radio";
            result.node = inputs;
            // register triggers
            $(inputs).each(function(){
                ConditionsChecking.registerTrigger(this,fieldName);
            });
        }
        return result;
    },
    extractFieldNode: function(fieldName){
        let result = {
            type:"",
            node:{},
            conditionIds: []
        };
        result = this.findCheckbox(fieldName, result);
        result = this.findCheckboxTag(fieldName, result);
        result = this.findRadio(fieldName, result);
        result = this.findList(fieldName, result);

        return result;
    },
    registerFieldName: function(fieldName, id){
        if (typeof this.fieldNamesCache[fieldName] === "undefined"){
            this.fieldNamesCache[fieldName] = this.extractFieldNode(fieldName);
            this.fieldNamesCache[fieldName].conditionIds.push(id);
        } else if(this.fieldNamesCache[fieldName].conditionIds.indexOf(id) < 0) {
            this.fieldNamesCache[fieldName].conditionIds.push(id);
        }
    },
    parseCondition: function (element) {
        let condition = $(element).data("conditionschecking");
        // index = internal id
        let id = this.conditionsCache.length;
        // save cache
        this.conditionsCache.push({
            condition:condition,
            node:element,
            structuredConditions: {}
        });

        let parsingObject = {
            restOfCondition: condition,
            currentCondition: "",
            operation: ""
        }
        while (parsingObject.restOfCondition.length > 0) {
            parsingObject = this.getFirstOperation(parsingObject);
            // check condition
            let indexForStructuredCondition = Object.keys(this.conditionsCache[id].structuredConditions).length;
            // save in cache
            this.conditionsCache[id].structuredConditions[indexForStructuredCondition] = {
                operation:parsingObject.operation
            };
            let structuredCondition = this.conditionsCache[id].structuredConditions[indexForStructuredCondition];
            if (parsingObject.currentCondition.length > 0){
                structuredCondition = this.addCondition(
                    parsingObject.currentCondition);
            } else {
                structuredCondition.leftPart = "";
                structuredCondition.rightPart = "";
                structuredCondition.typeOfCondition = "";
            }
            // activate trigger
            if (typeof structuredCondition.leftPart !== "undefined" && structuredCondition.leftPart.length > 0){
                let fieldName = structuredCondition.leftPart.trim();
                this.registerFieldName(fieldName,id);
            }
            for (const key in structuredCondition) {
                this.conditionsCache[id].structuredConditions[indexForStructuredCondition][key] = structuredCondition[key];
            }
        }
    },
    init: function() {
        let conditionschecking = this;
        $("div[data-conditionschecking]").each(function (){
            let element = $(this);
            conditionschecking.parseCondition(element);
        });
        // init conditions
        let elemsToClean = {};
        for (let index = 0; index < this.conditionsCache.length; index++) {
            this.resolveCondition(index,false,elemsToClean);
        }
        for (const id in elemsToClean) {
            if (elemsToClean[id]) {
                let conditionData = this.conditionsCache[id];
                this.emptyChildren(conditionData.node);
            }
        }
    }
}

ConditionsChecking.init();