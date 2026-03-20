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


