
function _t(message,replacements = {}){
    // init translation
    if (wiki.lang !== null && typeof wiki.lang === "object" && Object.keys(wiki.lang) == 0){
        try {
            let translation = $.ajax({method:"GET",url:wiki.url("api/translations/js",{lang:wiki.locale}),async:false,cache:true}).responseJSON.translation;
            if (translation !== null && typeof translation === "object" && Object.keys(translation) != 0){
                wiki.lang = translation;
            }
        } catch (error) {
        }
    }
    var translation = wiki.lang[message] ?? null ;
    if (!translation){
        translation = message;
        if (wiki.isDebugEnabled){
            console.warn('Translation was not found in wiki.lang for "'+message+'", (wiki.locale = '+wiki.locale+')');
        }
    }
    for (var key in replacements) {
        while (translation.includes(`{${key}}`)){
            translation = translation.replace(`{${key}}`,replacements[key])
        }
    }
    return translation;
  }

var wiki = {
    ...((typeof wiki !== 'undefined') ? wiki : null),
    ...{
        url: function(url, params = {}) {
            let result = wiki.baseUrl + url
            result = result.replace('??', '?')
            stringParams = []
            for(let key in params) {
                stringParams.push(key + '=' + encodeURIComponent(params[key]))
            }
            if (stringParams.length) {
                result += result.includes('?') ? '&' : '?';
                result += stringParams.join('&')
            }
            return result;
        },
    }
};
  