
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
  