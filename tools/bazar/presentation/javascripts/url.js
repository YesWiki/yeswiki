import { updateExportLinks } from './export.js'
import { parseCondition } from './search.js'

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

