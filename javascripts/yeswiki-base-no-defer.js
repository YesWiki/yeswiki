
function _t(message,replacements = {}){
    var translation = wiki.lang[message] ?? message ;
    for (var key in replacements) {
        translation = translation.replace('{'+key+'}',replacements[key]);
    }
    return translation;
  }
  