function openNav() {
    // si c'est déja ouvert, on ferme
    if (document.getElementById("preset-sidenav").style.width == "250px") {
        closeNav()
    } else {
        document.getElementById("preset-sidenav").style.width = "250px";
        document.getElementById("yw-container").style.paddingRight = "250px";
        let previousAdtive = $('.css-preset.active');
        $('#preset-sidenav .colorpicker').each(function(){
        // define values from current set for color picker
        var value = document.documentElement.style.getPropertyValue('--'+$(this).attr('name'));
        if (value){  
            $(this).val(value);
            // trigger change event
            $(this).change();
        }
        });
        $('#preset-sidenav .fontpicker').each(function(){
        // define values from current set for color picker
        var value = document.documentElement.style.getPropertyValue('--'+$(this).attr('name'));
        if (value){  
            // extract name
            let values = value.split(',');
            value = values[0];
            value = value.replace(/'/g,'');
            $(this).val(value);
            // trigger change event
            $(this).change();
        }
        });
        $('#preset-sidenav .form-input[name=main-text-fontsize]').each(function(){
        // define values from current set for color picker
        var value = document.documentElement.style.getPropertyValue('--main-text-fontsize');
        
        if (value){  
            // extract name
            let values = value.split('px');
            value = values[0];
            $(this).val(value);
            // trigger change event
            $(this).change();
        }
        });
        $(previousAdtive).addClass('active');
    }
    return false;
}

function closeNav() {
    document.getElementById("preset-sidenav").style.width = "0";
    document.getElementById("yw-container").style.paddingRight = "0";
    return false;
}

document.addEventListener('DOMContentLoaded', function(){
    if (typeof $('.colorpicker').spectrum === "function") {
        $('.colorpicker').spectrum({
        showPalette:true,
        showAlpha: true,
        showInput: true,
        clickoutFiresChange: true,
        showInitial: true,
        chooseText: themeSelectorTranslation['TEMPLATE_APPLY'],
        cancelText: themeSelectorTranslation['TEMPLATE_CANCEL'],
        change: function(color) {
            document.documentElement.style.setProperty('--'+$(this).attr('name'), color.toRgbString());
            $('.css-preset').removeClass('active') ;
        },
        hide: function(color) {
            document.documentElement.style.setProperty('--'+$(this).attr('name'), color.toRgbString());
        },
        move: function(color) {
            document.documentElement.style.setProperty('--'+$(this).attr('name'), color.toRgbString());
        },
        palette: [
            [
            getComputedStyle(document.documentElement).getPropertyValue('--primary-color'),
            getComputedStyle(document.documentElement).getPropertyValue('--secondary-color-1'),
            getComputedStyle(document.documentElement).getPropertyValue('--secondary-color-2')
            ],
            [
            getComputedStyle(document.documentElement).getPropertyValue('--neutral-light-color'),
            getComputedStyle(document.documentElement).getPropertyValue('--neutral-soft-color'),
            getComputedStyle(document.documentElement).getPropertyValue('--neutral-color')
            ]
        ]
        })
    }
    if (typeof $('.fontpicker').fontselect === "function") {
        $('.fontpicker')
        .fontselect({
        placeholder: themeSelectorTranslation['TEMPLATE_CHOOSE_FONT'],
        placeholderSearch: themeSelectorTranslation['TEMPLATE_SEARCH_POINTS'],
        })
        .on('change', function() {
        // Replace + signs with spaces for css
        var font = this.value.replace(/\+/g, ' ');

        // Split font into family and weight
        font = font.split(':');

        var fontFamily = font[0];
        var fontWeight = font[1] || 400;

        document.documentElement.style.setProperty('--'+$(this).attr('name'), "'"+fontFamily+"'");
        $('.css-preset').removeClass('active') ;
        });
    }

    $('#preset-sidenav .range').on('change', function() {
        document.documentElement.style.setProperty('--'+$(this).attr('name'), ""+$(this).val()+"px");
        $('.css-preset').removeClass('active') ;
    });
}, false);


$('.css-preset').click(function() {
    closeNav();
    // get data
    var primaryColor = $(this).data('primary-color');
    var secondaryColor1 = $(this).data('secondary-color-1') ;
    var secondaryColor2 = $(this).data('secondary-color-2');
    var neutralColor = $(this).data('neutral-color');
    var neutralSoftColor = $(this).data('neutral-soft-color');
    var neutralLightColor = $(this).data('neutral-light-color');
    var mainTextFontsize = $(this).data('main-text-fontsize');
    var mainTextFontfamily = $(this).data('main-text-fontfamily');
    var mainTitleFontfamily = $(this).data('main-title-fontfamily');
    // check all data
    if (!primaryColor || !secondaryColor1 || !secondaryColor2 || !neutralColor
        || !neutralSoftColor || !neutralLightColor || !mainTextFontsize
        || !mainTextFontfamily || !mainTitleFontfamily) {
        // error
        let message = themeSelectorTranslation['TEMPLATE_PRESET_ERROR'];
        if (typeof toastMessage == 'function'){
            toastMessage(message,3000,'alert alert-warning');
        } else {
            alert(message);
        }
        return false;
    }
    // set values
    document.documentElement.style.setProperty('--primary-color', primaryColor);
    document.documentElement.style.setProperty('--secondary-color-1', secondaryColor1);
    document.documentElement.style.setProperty('--secondary-color-2', secondaryColor2);
    document.documentElement.style.setProperty('--neutral-color', neutralColor);
    document.documentElement.style.setProperty('--neutral-soft-color', neutralSoftColor);
    document.documentElement.style.setProperty('--neutral-light-color', neutralLightColor);
    document.documentElement.style.setProperty('--main-text-fontsize', mainTextFontsize);
    document.documentElement.style.setProperty('--main-text-fontfamily', mainTextFontfamily);
    document.documentElement.style.setProperty('--main-title-fontfamily', mainTitleFontfamily);
    // set filename
    var filename = $(this).data('key');
    filename = filename.replace('.css','');
    if (filename){
        $('#preset-sidenav input.form-input[name=filename]').each(function (){
            $(this).val(filename);
        });
    }
    // set class active or toggle it
    let isAlreadyActive = $(this).hasClass('active') ;
    $('.css-preset').removeClass('active') ;
    if (!isAlreadyActive){
        $(this).addClass('active') ;
    }
    return false;
});
function deleteCSSPreset(elem,text,url){
    event.preventDefault();
    var key = $(elem).data('key');
    var confirmResult = confirm(text);
    if (confirmResult) {
        $.ajax({
            url: url,
            success: function(data,textStatus,jqXHR){
                console.log(key+' deleted !');
                $(elem).parent().remove();
            },
            method: 'DELETE',
            cache: false,
            error: function(jqXHR,textStatus,errorThrown){
                let message = key+themeSelectorTranslation['TEMPLATE_FILE_NOT_DELETED'];
                console.log(message+' Message :'+jqXHR.responseText);
                if (typeof toastMessage == 'function'){
                    toastMessage(message,3000,'alert alert-warning');
                } else {
                    alert(message);
                }
            },
        });
    }
    // to prevent opening url
    return false;
}
function componentToHex(c) {
    let hex = parseInt(c).toString(16);
    return hex.length == 1 ? '0' + hex : hex;
}
function extractFromStringWithRGB(value){
    var res = value.match(/\s*rgb\(\s*([0-9]*)\s*,\s*([0-9]*)\s*,\s*([0-9]*)\s*\)/);
    if (res && res.length > 3){
        value = '#' + componentToHex(res[1]) + componentToHex(res[2]) + componentToHex(res[3]);
    }
    return value;
}
function getStyleValueEvenIfNotInitialized(prop){
    var value = document.documentElement.style.getPropertyValue(prop);
    if (!value){
        value = getComputedStyle(document.documentElement).getPropertyValue(prop);
    }
    return value ;
}
function saveCSSPreset(elem,url,rewriteMode){
    event.preventDefault();
    var fileName = $(elem).prev().find('input[name=filename]').val();
    fileName = fileName.replace('.css','');
    var fullFileName = fileName + '.css';
    url = url + fullFileName;
    // get values
    var primaryColor = extractFromStringWithRGB(getStyleValueEvenIfNotInitialized('--primary-color'));
    var secondaryColor1 = extractFromStringWithRGB(getStyleValueEvenIfNotInitialized('--secondary-color-1'));
    var secondaryColor2 = extractFromStringWithRGB(getStyleValueEvenIfNotInitialized('--secondary-color-2'));
    var neutralColor = extractFromStringWithRGB(getStyleValueEvenIfNotInitialized('--neutral-color'));
    var neutralSoftColor = extractFromStringWithRGB(getStyleValueEvenIfNotInitialized('--neutral-soft-color'));
    var neutralLightColor = extractFromStringWithRGB(getStyleValueEvenIfNotInitialized('--neutral-light-color'));
    var mainTextFontsize = getStyleValueEvenIfNotInitialized('--main-text-fontsize');
    var mainTextFontfamily = getStyleValueEvenIfNotInitialized('--main-text-fontfamily');
    var mainTitleFontfamily = getStyleValueEvenIfNotInitialized('--main-title-fontfamily');
    if (mainTextFontfamily.search(/^[A-Za-z0-9 ]*$/) != -1){
        mainTextFontfamily = '\''+mainTextFontfamily+'\', sans-serif';
    } else if (mainTextFontfamily.search(/^\'[A-Za-z0-9 ]*\'$/) != -1){
        mainTextFontfamily = mainTextFontfamily+', sans-serif';

    }
    if (mainTitleFontfamily.search(/^[A-Za-z0-9 ]*$/) != -1){
        mainTitleFontfamily = '\''+mainTitleFontfamily+'\', sans-serif';
    } else if (mainTitleFontfamily.search(/^\'[A-Za-z0-9 ]*\'$/) != -1){
        mainTitleFontfamily = mainTitleFontfamily+', sans-serif';

    }
    $.ajax({
        url: url,
        success: function(data,textStatus,jqXHR){
            console.log(fullFileName+' added !');
            var urlwindow = window.location.toString();
            let urlAux = urlwindow.split((rewriteMode ? "?" : "&")+"theme=");
            window.location =
                urlAux[0] +
                (rewriteMode ? "?" : "&")+"theme=" +
                $("#changetheme").val() +
                "&squelette=" +
                $("#changesquelette").val() +
                "&style=" +
                $("#changestyle").val()+
                "&preset=" +customCSSPresetsPrefix+
                fullFileName;
        },
        method: 'POST',
        data: {
            'primary-color':primaryColor,
            'secondary-color-1':secondaryColor1,
            'secondary-color-2':secondaryColor2,
            'neutral-color':neutralColor,
            'neutral-soft-color':neutralSoftColor,
            'neutral-light-color':neutralLightColor,
            'main-text-fontsize':mainTextFontsize,
            'main-text-fontfamily':mainTextFontfamily,
            'main-title-fontfamily':mainTitleFontfamily,
        },
        cache: false,
        error: function(jqXHR,textStatus,errorThrown){
            try {
                var data = JSON.parse(jqXHR.responseText);
                var dataMessage = data.message;
            } catch (error) {
                var data = null;  
                var dataMessage = JSON.stringify(jqXHR.responseText);
            }
            let message = fullFileName+themeSelectorTranslation['TEMPLATE_FILE_NOT_ADDED'];
            let duration = 3000;
            if (data && data.errorCode == 2){
                message = message+"\n"+themeSelectorTranslation['TEMPLATE_FILE_ALREADY_EXISTING'];
                duration = 6000;
            }
            console.log(message+". Message :"+dataMessage);
            if (typeof toastMessage == 'function'){
                toastMessage(message,duration,'alert alert-danger');
            } else {
                alert(message);
            }
        },
    });
}

function getActivePreset(){
    var presetKey = '';
    let selectedCssPresets = $('.css-preset.active');
    if (selectedCssPresets && selectedCssPresets.length > 0){
      let selectedCssPreset = $(selectedCssPresets).first();
      let key = $(selectedCssPreset).data('key');
      if (key) {
        if ($(selectedCssPreset).hasClass('custom')){
          presetKey = customCSSPresetsPrefix+key;
        } else {
          presetKey = key;
        }
      }
    }
    return presetKey;
}

function saveTheme(url){
    let theme = $("#changetheme").val();
    let squelette = $("#changesquelette").val();
    let style = $("#changestyle").val();
    let preset = getActivePreset();    
    let errorMessage = themeSelectorTranslation['TEMPLATE_THEME_NOT_SAVE'];
    if (theme && squelette && style){
        $("body").append('<form id="templateFormSubmit" method="post" action="'+url+'" enctype="multipart/form-data">'
            + '<input type="hidden" name="action" value="setTemplate"/>'
            + '<input type="hidden" name="wdtTheme" value="'+theme+'"/>'
            + '<input type="hidden" name="wdtSquelette" value="'+squelette+'"/>'
            + '<input type="hidden" name="wdtStyle" value="'+style+'"/>'
            + '<input type="hidden" name="preset" value="'+preset+'"/>'
            +'</form>')
        $('#templateFormSubmit').submit();
    } else {
        if (typeof toastMessage == 'function'){
            toastMessage(errorMessage,3000,'alert alert-warning');
        } else {
            alert(errorMessage);
        }
    }
    return false;
}