export function recursivelyCalculateRelations(node, parentChain = []) {
  const allParents = [...parentChain]
  const descendants = []

  // Recursively calculate relations for children
  if (node.children && node.children.length > 0) {
    node.children.forEach((child) => {
      const childNode = recursivelyCalculateRelations(child, [node, ...allParents])
      descendants.push(child, ...childNode.descendants)
    })
  }

  node.parent = allParents[0]
  node.parents = allParents
  node.descendants = descendants
  return node
}

// deepGet({ comment: { label: 'foo' }}, 'comment.label') => 'foo'
export function deepGet(obj, path) {
  if (obj === undefined) return undefined
  if (typeof path === 'string') return deepGet(obj, path.split('.'))
  if (path.length === 0) return obj
  return deepGet(obj[path[0]], path.slice(1))
}

/**
 * Parse a condition like bf_myfield == value1, value2
 *
 * @param pValue <string> : the condition string to parse
 *
 * @return <object> : an object in the form { name : <string>, operator : <string>, values : [ string, ... ] }
 */

export function parseCondition (pValue) {
	// Extraire nom, opérateur et valeurs
	const regex = /\s*([^=!<>]*)\s*(==|!=|<=|>=|=|<|>)(.*)/
	const matches = pValue.match(regex)

	if (!matches) return null

	const vName = matches[1].trim()
	let vOperator = matches[2].trim()
	const rawValues = matches[3].trim()

	// Convertir l'opérateur "=" en "=="
	if (vOperator === '=') vOperator = '=='

	// Transformer la liste en tableau avec valeurs uniques
	const vUniqueValues = Array.from(
		new Set(
		    rawValues.split(',').map((v) => v.trim()).filter((v) => v !== '')
		)
	)

	// Retourner la structure

	const vResult = {
		name: vName,
		operator: vOperator,
		values: vUniqueValues
	}

	return vResult
}

/**
 * Parse a keywords search string
 * Keywords search string are composed of tokens
 * Tokens can be single words (without space) or expression composed of several words seperated by spaces enclosed in quote or double quote.
 * Tokens may be separated by |
 * | stands for logical AND
 * A token may be prefixed with - to exclude the results containing the token
 * The position of excluded tokens is not relevant
 * Ex : cat "my dog" -parrot | bulldog "small bird" -"cocker spaniel"
 *    will match result that contain ("cat" or "my dog") and ("bulldog" or "small bird)
 *    excluding results containing "parrot" or "cocker spaniel"
 *
 * @param pKeywords <string> : the keywords search string
 *
 * @return <array> : the parsed string as an associative array containing the keys :
 * 	- CNF =	the Conjonctive Normal Form (= [a OR b] AND [d or e]) of the keywords search string
 *			(ie : an AND-array of OR-arrays)
 *	- excludeds = <array> an array of excluded tokens
 */
 
export function parseKeywords(pKeywords) {
  const _t = (key) => 'BAZ_MOT_CLE' // Remplace ça par ton système de traduction si besoin

  // Résultat par défaut
  const results = { CNF: [], excludeds: [] }

  // Vérification de validité
  if (
	    typeof pKeywords !== 'string'
	    || pKeywords.trim() === ''
	    || pKeywords === _t('BAZ_MOT_CLE')
  ) {
	    return results
  }

  // Séparation des clauses AND par "|"
  const andClauses = pKeywords.split('|').map((clause) => clause.trim())

  for (const andClause of andClauses) {
	    // Extraction des tokens via RegEx
	    const regex = /(-)?("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|\S+)/gu
	    let match
	    const ors = []

	    while ((match = regex.exec(andClause)) !== null) {
	        const isExcluded = match[1] === '-'
	        const rawToken = match[2]
	        const cleanedToken = rawToken.replace(/^["']|["']$/g, '') // Supprime les guillemets

	        if (isExcluded) {
	            results.excludeds.push(cleanedToken)
	        } else {
	            ors.push(cleanedToken)
	        }
	    }

	    results.CNF.push(ors)
  }

  return results
}

/**
 * Parse URL parameters string and return a structured object reprensenting it
 * @param <string> pParams
 * @return <object> in the form :
 * 	{
 *		keywords : <string> the keywords string like : 'toto "tata tata" | titi -tutu'
 *		champ : <string> the sort field
 *		ordre : <string> the direction of the sort "asc" or "desc"
 *		query : <array> of condition object { name : <string>, operator : string, values : [ <string>, ... ]}
 *		any other parameter... : <string>
 *	}
 */
 
export function parseSearchParams(pParams) // Return params as a structured object
{
  const vParams = new URLSearchParams(pParams)

  const vParseds = {}

  for (const cKey of vParams.keys()) {
    const vValue = vParams.get(cKey)

    if ((cKey == 'q') || (cKey == 'keywords')) // keywords supports for clarity (q parameter is confusing with query parameter)
    {
      if (vValue && vValue.trim() !== '') vParseds.keywords = decodeURIComponent(vValue) // privilegiate use of "keywords"
    } else if ((cKey == 'champ') || (cKey == 'ordre')) {
      vParseds[cKey] = vValue
    } else if (cKey == 'query') {
      if (vValue && vValue.trim() !== '') vParseds[cKey] = decodeURIComponent(vValue).split('|').map(parseCondition)
    } else {
      vParseds[cKey] = vValue
    }
  }

  return vParseds
}

/**
 * Merge 2 search parameters.
 * @param pParam1, pParam1 :
 *		<string> = URL parameters (ex :"param1=toto&param2=tata")
 *			or
 *		<object> = structured object of parameters like the ones returned by parseSearchParams
 * @pOptions : <object> containing optional keys
 *		- returnMode : <string> set the return type of this method = "string" returns concatenated URL parameters as string, or "object" returns concatenated URL parameters as object
 *		- overrideKeywords : <boolean> false = concatenate keywords with |, true = override value with pParam2's keywords
 *		- overrideQuery : <boolean>
 *			false = replace query's field parameter with same name and operator == by those of pParam2's or concatenate the 2 queries with | if it doesn't exist,
 *			true = merge values with pParam2's keywords	overriding the complete query of pParam1
 * @return the concatenated URL parameters as <string> or <object>
 */
 
export function mergeSearchParams(pParams1, pParams2, pOptions = { returnMode: 'string', overrideKeywords: false, overrideQuery: false }) {
  	const vMerged = {}
  let vQuery
  let vKeywords

  const vParamsObject1 =	typeof (pParams1) == 'string' ? parseSearchParams(pParams1) : pParams1
  const vParamsObject2 =	typeof (pParams2) == 'string' ? parseSearchParams(pParams2) : pParams2

  $.extend(true, vMerged, vParamsObject1, vParamsObject2)

  // Merge query parameter

  if (vParamsObject1.query && vParamsObject1.query.length > 0 && vParamsObject2.query && vParamsObject2.query.length > 0) {
    if (pOptions.overrideQuery) {
      vQuery = vParamsObject2.query
    } else {
      vParamsObject2
        .query
        .forEach((pCondition2) => {
          let vFound = false

          vParamsObject1
            .query
            .map((pCondition1) => {
              if (pCondition1.name === pCondition2.name && pCondition1.operator === pCondition2.operator) {
                vFound = true
                return pCondition2
              }
              return pCondition1
            })

          if (!vFound) vParamsObject1.query.push(pCondition2)
        })

      vQuery = vParamsObject1.query
    }
  } else
    if (vParamsObject1.query && vParamsObject1.query.length > 0) {
      vQuery = vParamsObject1.query
    } else
      if (vParamsObject2.query && vParamsObject2.query.length > 0) {
        vQuery = vParamsObject2.query
      }

  if (vQuery !== undefined) {
	    // Remove duplicates and rebuild the query string

    vQuery = [...new Set(vQuery.map(({ name, operator, values }) => name + operator + values))].join('|')

    if (vQuery.trim() != '') vMerged.query = vQuery
  }

  // Merge keywords parameter

  if (vParamsObject1.keywords && vParamsObject2.keywords) {
    vKeywords = `${vParamsObject1.keywords}|${vParamsObject2.keywords}`
  } else
    if (vParamsObject1.keywords) {
      vKeywords = vParamsObject1.keywords
    } else
      if (vParamsObject2.keywords) {
        vKeywords = vParamsObject2.keywords
      }

  if (vKeywords != undefined && vKeywords.trim() != '') {
    // URI encode the keywords

	    // Remove duplicates and rebuild the query string

    vKeywords = [...new Set(vKeywords.split('|'))].join('|')

    if (vKeywords.trim() != '') vMerged.keywords = vKeywords
  }

  if (pOptions.returnMode == 'string') return $.param(vMerged)
  return vMerged
}

/* update all export buttons in a page with the given search parameters */

export function updateExportLinks(pSearchParams) {
    document
    .querySelectorAll('.export-links > a')
    .forEach((pLink) => {
		let vOldHREF = pLink.getAttribute('data-href') // Get the original href

		let vNewHREF

		if (vOldHREF.trim() === '') {
			console.error('Invalid URL provided.')
		} else {
			const vNewURL = new URL(vOldHREF)

			 	const vHandler = vNewURL.searchParams.keys().next()
			let vHandlerValue = vHandler.value

			if (vHandler) vNewURL.searchParams.delete(vHandlerValue)
			else vHandlerValue = ''

			const vParams = mergeSearchParams(vNewURL.searchParams.toString(), pSearchParams, { returnMode: 'string', overrideKeywords: false, overrideQuery: false })

			pLink.setAttribute(
			'href',
			vNewURL.origin + 
			vNewURL.pathname + 
			"?" + vHandlerValue + 
			(vParams ? "&" + vParams : "") +
			vNewURL.hash
			)
		}
	})
}

/*
 * updateHash with given parameters
 */
 
export function updateHash(pSavedHash = "", pKeywords = "", pSortField = "", pSortOrder = "", pFilters = []) {
    const cCurrentHash = pSavedHash

    const vQuery = []
    const vCurrentParams = {}
    let vMergedParams

    let vSearch = (pKeywords != undefined)?pKeywords.trim():'';

    if (vSearch.length < wiki.minSearchKeywordLength) vSearch = ''

    if (vSearch != '') vCurrentParams.keywords = vSearch
    if (pSortField && pSortField != '') vCurrentParams.champ = pSortField
    if (pSortOrder && pSortOrder != '') vCurrentParams.ordre = pSortOrder

    let bHasFilter = false

    for (const cFilterId in pFilters) {
      bHasFilter = true

      vQuery.push({
        name: cFilterId,
        operator: '==',
        values:	pFilters[cFilterId]
          .map((pString) => pString
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/&#039;/g, "'"))
          .join(',')
      })
    }

    if (bHasFilter) vCurrentParams.query = vQuery

    vMergedParams = mergeSearchParams(cCurrentHash, vCurrentParams, { returnMode: 'string', overrideKeywords: true, overrideQuery: true })

    // Encode the hash to avoid confusion between &-separated hash parameters and &-separated search parameters

    history.pushState({}, '', `#${encodeURIComponent(vMergedParams)}`)

    updateExportLinks(vMergedParams) // Export
}            

/**
 * Test if a string represents a regexp
 * A string is considered as a regexp if it contains at least one ".*"
 * or if it begins and ends with "/"
 * @pString <string> : the string to test
 * @return <boolean> : true if the string represent a regexp, false otherwise
 */
 
export function isRegExp(str) {
    return (typeof str === 'string' && (str.includes('.*') || (str.startsWith('/') && str.endsWith('/'))))
}

export function removeDiacritics(str) {
  return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
}

/**
 * Normalise une chaîne :
 *   - met en minuscules (Unicode-safe)
 *   - transforme les caractères accentués en leur équivalent non accentué
 *   - gère les ligatures courantes (œ, æ, ß, etc.)
 *
 * @param <string> : chaîne d'entrée (n'importe quel encodage détectable)
 * @return <string> : chaîne lowercase, sans accents
 */

export function toLowerCaseWithoutAccent(str) {
  if (typeof str !== 'string') return ''

  // 1. Lowercase unicode
  str = str.toLowerCase()

  // 2. Remplacer les ligatures
  const replacements = {
	    œ: 'oe',
	    æ: 'ae',
	    ß: 'ss',
	    ø: 'o',
	    ð: 'd',
	    þ: 'th'
  }

  str = str.replace(/œ|æ|ß|ø|ð|þ/g, (match) => replacements[match])

  // 3. Décomposition unicode (NFD) + suppression des diacritiques
  str = str.normalize('NFD').replace(/[\u0300-\u036f]/g, '')

  // 4. Translitération ASCII (approximative via normalisation)
  // Pas d'équivalent direct à `iconv`, mais `normalize` fait une bonne partie du travail

  return str
}

/**
 * Extract and transform a regexp string from a string recognized by isRegExp as a regexp
 * + It removes beginning and ending "/" if it exists
 * + Optionnaly, it add alternatives for each character that has an accented version
 * @param pString : <string> a regexp string recognized by isRegExp as a regexp
 * @param pAccentInsensitive : <boolean> true to make the regexp accent insensitive
 *
 * @return <string> : the transformed regexp string
 */
 
export function extractRegExp(pString, accentInsensitive = true) {
  let vString

  if (pString.startsWith('/') && pString.endsWith('/')) {
	    vString = pString.slice(1, -1)
  } else {
	    vString = pString
  }

  if (accentInsensitive) {
	    vString = toLowerCaseWithoutAccent(vString)

	    vString = vString.replace(/a/g, '(a|à|á|â|ã|ä|A|À|Á|Â|Ã|Ä)')
	    vString = vString.replace(/c/g, '(c|ç|C|Ç)')
	    vString = vString.replace(/e/g, '(e|è|é|ê|ë|E|È|É|Ê|Ë)')
	    vString = vString.replace(/i/g, '(i|ì|í|î|ï|I|Ì|Í|Î|Ï)')
	    vString = vString.replace(/n/g, '(n|ñ|N|Ñ)')
	    vString = vString.replace(/o/g, '(o|ò|ó|ô|õ|ö|O|Ò|Ó|Ô|Õ|Ö)')
	    vString = vString.replace(/u/g, '(u|ù|ú|û|ü|U|Ù|Ú|Û|Ü)')
	    vString = vString.replace(/y/g, '(y|ý|ÿ|Y|Ý)')
  }

  return vString
}


