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
    var primaryColor = $(this).data('primary-color') || '#1a89a0';
    var secondaryColor1 = $(this).data('secondary-color-1') || '#d8604c';
    var secondaryColor2 = $(this).data('secondary-color-2') || '#d78958';
    var neutralColor = $(this).data('neutral-color') || '#4e5056';
    var neutralSoftColor = $(this).data('neutral-soft-color') || '#b0b1b3';
    var neutralLightColor = $(this).data('neutral-light-color') || '#ffffff';
    var mainTextFontsize = $(this).data('main-text-fontsize') || '17px';
    var mainTextFontfamily = $(this).data('main-text-fontfamily') || '\'Nunito\', sans-serif';
    var mainTitleFontfamily = $(this).data('main-title-fontfamily') || '\'Nunito\', sans-serif';
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
                if (data.status) {
                    console.log(key+' deleted !');
                    $(elem).parent().remove();
                } else {
                    let message = key+' not deleted !';
                    console.log(message+' Message :'+JSON.stringify(data));
                    if (typeof toastMessage == 'function'){
                        toastMessage(message,3000,'alert alert-warning');
                    } else {
                        alert(message);
                    }
                }
            },
            method: 'DELETE',
            cache: false,
            error: function(jqXHR,textStatus,errorThrown){
                console.log('trying DELETE '+url+' ; but error obtained:'+textStatus);
                if (typeof toastMessage == 'function'){
                    toastMessage(key+' not deleted !',3000,'alert alert-warning');
                } else {
                    alert(key+' not deleted !');
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
function saveCSSPreset(elem,url){
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
            if (data.status) {
                let resultFileName = data.filename ?? fullFileName ;
                console.log(resultFileName+' added !');
                var url = window.location.toString();
                let urlAux = url.split("&theme=");
                window.location =
                    urlAux[0] +
                    "&theme=" +
                    $("#changetheme").val() +
                    "&squelette=" +
                    $("#changesquelette").val() +
                    "&style=" +
                    $("#changestyle").val()+
                    "&preset=" +customCSSPresetsPrefix+
                    resultFileName;
            } else {
                let message = fullFileName+' not added !';
                console.log(message+"\n"+JSON.stringify(data));
                if (typeof toastMessage == 'function'){
                    toastMessage(message,3000,'alert alert-warning');
                } else {
                    alert(message);
                }
            }
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
            console.log('trying POST '+url+' ; but error obtained:'+textStatus);
            let message = fullFileName+' not added !';
            if (typeof toastMessage == 'function'){
                toastMessage(message,3000,'alert alert-warning');
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