
function _t(message,replacements = {}){
    var translation = wiki.lang[message] ?? null ;
    if (!translation){
        translation = message;
        if (wiki.isDebugEnabled){
            console.warn('Translation was not found in wiki.lang for "'+message+'", (wiki.locale = '+wiki.locale+')');
        }
    }
    for (var key in replacements) {
        translation = translation.replace('{'+key+'}',replacements[key]);
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
  