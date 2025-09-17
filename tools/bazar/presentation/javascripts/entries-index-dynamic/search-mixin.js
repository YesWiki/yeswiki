// TODO better list and translatable
const wordsToExcludeFromSearch = ['le', 'la', 'les', 'du', 'en', 'un', 'une']

export default {
  data: {
    isLoading: false,
    pendingRequest: null
  },
  methods:
  {
  	 /**
     * Parse a condition like bf_myfield == value1, value2
     *
     * @param pValue <string> : the condition string to parse
     *
     * @return <object> : an object in the form { name : <string>, operator : <string>, values : [ string, ... ] }
     */
    parseCondition(pValue) {
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
    },
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
    parseKeywords(pKeywords) {
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
    },
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
    parseSearchParams(pParams) // Return params as a structured object
    {
      const vParams = new URLSearchParams(pParams)

      const vParseds = {}

      for (const cKey of vParams.keys()) {
        const vValue = vParams.get(cKey)

        if ((cKey == 'q') || (cKey == 'keywords')) // keywords supports for clarity (q parameter is confusing with query parameter)
        {
          if (vValue && vValue.trim() !== '') vParseds.keywords = decodeURIComponent(vValue) // privilegiate use of "keywords"
        } else if ((cKey == 'champ') || (cKey == 'ordre')) {
          vParseds[cKey] = vValue
        } else if (cKey == 'query') {
          if (vValue && vValue.trim() !== '') vParseds[cKey] = decodeURIComponent(vValue).split('|').map(this.parseCondition)
        } else {
          vParseds[cKey] = vValue
        }
      }

      return vParseds
    },
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
    mergeSearchParams(pParams1, pParams2, pOptions = { returnMode: 'string', overrideKeywords: false, overrideQuery: false }) {
	  	const vMerged = {}
      let vQuery
      let vKeywords

      const vParamsObject1 =	typeof (pParams1) == 'string' ? this.parseSearchParams(pParams1) : pParams1
      const vParamsObject2 =	typeof (pParams2) == 'string' ? this.parseSearchParams(pParams2) : pParams2

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
    },
    /**
     * Test if a string represents a regexp
	 * A string is considered as a regexp if it contains at least on ".*"
	 * of if it begins and ends with "/"
	 * @pString <string> : the string to test
	 * @return <boolean> : true if the string represent a regexp, false otherwise
	 */
    isRegExp(str) {
	    return (typeof str === 'string' && (str.includes('.*') || (str.startsWith('/') && str.endsWith('/'))))
    },
    /**
	 * Normalise une chaîne :
	 *   - met en minuscules (Unicode-safe)
	 *   - transforme les caractères accentués en leur équivalent non accentué
	 *   - gère les ligatures courantes (œ, æ, ß, etc.)
	 *
	 * @param <string> : chaîne d'entrée (n'importe quel encodage détectable)
	 * @return <string> : chaîne lowercase, sans accents
	 */
    toLowerCaseWithoutAccent(str) {
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
    },
    /**
     * Extract and transform a regexp string from a string recognized by isRegExp as a regexp
	 * + It removes beginning and ending "/" if it exists
	 * + Optionnaly, it add alternatives for each character that has an accented version
	 * @param pString : <string> a regexp string recognized by isRegExp as a regexp
	 * @param pAccentInsensitive : <boolean> true to make the regexp accent insensitive
	 *
	 * @return <string> : the transformed regexp string
	 */
    extractRegExp(pString, accentInsensitive = true) {
      let vString

      if (pString.startsWith('/') && pString.endsWith('/')) {
		    vString = pString.slice(1, -1)
      } else {
		    vString = pString
      }

      if (accentInsensitive) {
		    vString = this.toLowerCaseWithoutAccent(vString)

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
    },
    searchEntries(entries, search) {
      switch (this.params.search) {
        case 'dynamic':
          return this.localSearch(entries, search)
        case 'true':
          return this.distantSearch(entries, search)
        default:
          return entries
      }
    },
    // Search through API
    distantSearch(entries, search) {
      if (this.isLoading) {
        // Do not send multiple request in parrallel, wait for the first one to finish
        this.pendingRequest = search
        return
      }
      this.isLoading = true
      this.pendingRequest = null

      const vParams = this.mergeSearchParams(this.params, { keywords: search }, { returnMode: 'object', overrideKeywords: false, overrideQuery: false })

      $.getJSON(wiki.url('?api/entries/bazarlist'), vParams, (data) => {
        this.isLoading = false
        const searchedIds = data.entries.map((entry) => entry[0])
        this.searchedEntries = entries.filter((entry) => searchedIds.includes(entry.id_fiche))
        this.filterEntries()
        if (this.pendingRequest) {
          this.distantSearch(entries, this.pendingRequest)
        }
      })
      return this.searchedEntries
    },
    // Search with existing data in javascript
    localSearch(entries, search) {
    	const vThis = this

      // Parse search as a keywords search string

      const vParsedKeywords = vThis.parseKeywords(search)

      vParsedKeywords.CNF = vParsedKeywords.CNF
        .map((pAnd) => pAnd
          .map((pOr) => vThis.removeDiacritics(pOr))
          .filter((pOr) => pOr.length > 2 && !wordsToExcludeFromSearch.includes(pOr)))
        .filter((pAnd) => pAnd.length > 0)

      vParsedKeywords.excludeds = vParsedKeywords.excludeds
        .map((pExcluded) => vThis.removeDiacritics(pExcluded))
        .filter((pExcluded) => pExcluded.length > 2 && !wordsToExcludeFromSearch.includes(pExcluded))

      let vResult = entries.filter((pEntry) => {
        pEntry.searchScore = 1

        let vMatchedAnds = 0
        let vAndsCount = 0

        vParsedKeywords.CNF.every((pAnd) => {
          let vMatchedOrs = 0
          let vAndScore = 0

          vAndsCount++

          pAnd.forEach((pOr) => {
            let vMatchedFields = 0
            let vOrScore = 0

            vThis.params.searchfields.forEach((pField) => {
              let vFieldValue = pEntry[pField] ? pEntry[pField] : ''

              if (Array.isArray(vFieldValue)) vFieldValue = vFieldValue.join(' ')

              vFieldValue = vThis.removeDiacritics(vFieldValue)

              vFieldValue = vFieldValue.trim()

              const vRegExp = vThis.extractRegExp(pOr)

              if (vFieldValue) {
                const vMatches = vFieldValue.match(new RegExp(vRegExp, 'gi'))

                if (vMatches) {
                  vMatches.forEach((pMatch) => {
                    vOrScore += pField == 'bf_titre' ? 2 * (pMatch.length + 1) : pMatch.length + 1
                    vMatchedFields++
                  })
						   }
              }
            })

            vOrScore *= vMatchedFields + 1

            vAndScore += vOrScore

            if (vMatchedFields > 0) vMatchedOrs++
          })

          if (vAndScore == 0) {
            pEntry.searchScore = 0
            return false
          }

          vAndScore *= vMatchedOrs + 1

          pEntry.searchScore += vAndScore

          if (vMatchedOrs > 0) vMatchedAnds++

          return true
		    })

		    if (vAndsCount > 0) {
          pEntry.searchScore *= vMatchedAnds / vAndsCount
        }

        vParsedKeywords.excludeds.forEach((pExcluded) => {
          vThis.params.searchfields.forEach((pField) => {
            let vFieldValue = pEntry[pField] ? pEntry[pField] : ''

            if (Array.isArray(vFieldValue)) vFieldValue = vFieldValue.join(' ')

            vFieldValue = vThis.removeDiacritics(vFieldValue)

            vFieldValue = vFieldValue.trim()

            const vRegExp = vThis.extractRegExp(pExcluded)

            if (vFieldValue) {
              const vMatches = vFieldValue.match(new RegExp(pExcluded, 'g'))

              if (vMatches) {
                pEntry.searchScore = 0
              }
            }
          })
        })

	        return pEntry.searchScore > 0
      })

      vResult = vResult.sort((a, b) => ((a.searchScore > b.searchScore) ? -1 : 1))
      return vResult
    },
    removeDiacritics(str) {
      return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
    }
  }
}
