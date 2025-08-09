// TODO better list and translatable
const wordsToExcludeFromSearch = ['le', 'la', 'les', 'du', 'en', 'un', 'une']

export default {
  data: {
    isLoading: false,
    pendingRequest: null
  },
  methods: {
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
     * @pKeywords <string> : the keywords search string
     *
     * @return <array> : the parsed string as an associative array containing the keys :
     * 	- CNF =	the Conjonctive Normal Form (= [a OR b] AND [d or e]) of the keywords search string
     *			(ie : an AND-array of OR-arrays)
     *	- excludeds = <array> an array of excluded tokens
     */
  	parseKeywords(pKeywords)
  	{
		const _t = (key) => 'BAZ_MOT_CLE'; // Remplace ça par ton système de traduction si besoin

		// Résultat par défaut
		const results = { CNF: [], excludeds: [] };

		// Vérification de validité
		if (
		    typeof pKeywords !== 'string' ||
		    pKeywords.trim() === '' ||
		    pKeywords === _t('BAZ_MOT_CLE')
		) {
		    return results;
		}

		// Séparation des clauses AND par "|"
		const andClauses = pKeywords.split('|').map(clause => clause.trim());

		for (const andClause of andClauses) {
		    // Extraction des tokens via RegEx
		    const regex = /(-)?("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|\S+)/gu;
		    let match;
		    const ors = [];

		    while ((match = regex.exec(andClause)) !== null) {
		        const isExcluded = match[1] === '-';
		        const rawToken = match[2];
		        const cleanedToken = rawToken.replace(/^["']|["']$/g, ''); // Supprime les guillemets

		        if (isExcluded) {
		            results.excludeds.push(cleanedToken);
		        } else {
		            ors.push(cleanedToken);
		        }
		    }

		    results.CNF.push(ors);
		}

		return results;
	},  
	/**
     * Test if a string represents a regexp
	 * A string is considered as a regexp if it contains at least on ".*"
	 * of if it begins and ends with "/"
	 * @pString <string> : the string to test
	 * @return <boolean> : true if the string represent a regexp, false otherwise
	 */	
	isRegExp(str)
	{
	    return (typeof str === 'string' && (str.includes('.*') || (str.startsWith('/') && str.endsWith('/'))));
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
	toLowerCaseWithoutAccent(str)
	{
		if (typeof str !== 'string') return '';

		// 1. Lowercase unicode
		str = str.toLowerCase();

		// 2. Remplacer les ligatures
		const replacements = {
		    œ: 'oe',
		    æ: 'ae',
		    ß: 'ss',
		    ø: 'o',
		    ð: 'd',
		    þ: 'th',
		};

		str = str.replace(/œ|æ|ß|ø|ð|þ/g, match => replacements[match]);

		// 3. Décomposition unicode (NFD) + suppression des diacritiques
		str = str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');

		// 4. Translitération ASCII (approximative via normalisation)
		// Pas d'équivalent direct à `iconv`, mais `normalize` fait une bonne partie du travail

		return str;
	},
	/**
     * Extract and transform a regexp string from a string recognized by isRegExp as a regexp
	 * + It removes beginning and ending "/" if it exists
	 * + Optionnaly, it add alternatives for each character that has an accented version
	 * @pString : <string> a regexp string recognized by isRegExp as a regexp
	 * @pAccentInsensitive : <boolean> true to make the regexp accent insensitive
	 * @return <string> : the transformed regexp string
	 */
	extractRegExp(pString, accentInsensitive = true)
	{
		var vString;

		if (pString.startsWith('/') && pString.endsWith('/')) {
		    vString = pString.slice(1, -1);
		} else {
		    vString = pString;
		}

		if (accentInsensitive) {
		    vString = this.toLowerCaseWithoutAccent(vString);

		    vString = vString.replace(/a/g, '(a|à|á|â|ã|ä|A|À|Á|Â|Ã|Ä)');
		    vString = vString.replace(/c/g, '(c|ç|C|Ç)');
		    vString = vString.replace(/e/g, '(e|è|é|ê|ë|E|È|É|Ê|Ë)');
		    vString = vString.replace(/i/g, '(i|ì|í|î|ï|I|Ì|Í|Î|Ï)');
		    vString = vString.replace(/n/g, '(n|ñ|N|Ñ)');
		    vString = vString.replace(/o/g, '(o|ò|ó|ô|õ|ö|O|Ò|Ó|Ô|Õ|Ö)');
		    vString = vString.replace(/u/g, '(u|ù|ú|û|ü|U|Ù|Ú|Û|Ü)');
		    vString = vString.replace(/y/g, '(y|ý|ÿ|Y|Ý)');
		}

		return vString;
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
    // Search throught API
    distantSearch(entries, search) {
      if (this.isLoading) {
        // Do not send multiple request in parrallel, wait for the first oen to finish
        this.pendingRequest = search
        return
      }
      this.isLoading = true
      this.pendingRequest = null
      const params = { ...this.params, ...{ q: search } }
      $.getJSON(wiki.url('?api/entries/bazarlist'), params, (data) => {
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
    
    	var vThis = this;
    
		// Parse search as a keywords search string

		var vParsedKeywords = vThis.parseKeywords (search);

		vParsedKeywords.CNF = vParsedKeywords.CNF
								.map (	(pAnd) => 
										pAnd
										.map ((pOr) => vThis.removeDiatrics(pOr))
										.filter ((pOr) => pOr.length > 2 && !wordsToExcludeFromSearch.includes(pOr))
									)
								.filter ((pAnd) => pAnd.length > 0);

		vParsedKeywords.excludeds = vParsedKeywords.excludeds
										.map ((pExcluded) => vThis.removeDiatrics(pExcluded))
										.filter((pExcluded) => pExcluded.length > 2 && !wordsToExcludeFromSearch.includes(pExcluded));
								
		var vResult = entries.filter((pEntry) =>
		{
			pEntry.searchScore = 0;

			var vMatchedAnds = 0;

			vParsedKeywords.CNF.every (function (pAnd)
			{
				var vMatchedOrs = 0;
				var vAndScore = 0;

				pAnd.forEach ((pOr) => 
				{
					var vMatchedFields = 0;
					var vOrScore = 0;
								
					vThis.params.searchfields.forEach(function (pField)
					{
						var vFieldValue = pEntry[pField] ? pEntry[pField] : '';
						
						if (Array.isArray(vFieldValue)) vFieldValue = vFieldValue.join(' ');

						vFieldValue = vThis.removeDiatrics(vFieldValue);
							
						vFieldValue = vFieldValue.trim ();
							
						var vRegExp = vThis.extractRegExp (pOr);

						if (vFieldValue)
						{
							var vMatches = vFieldValue.match (new RegExp (vRegExp, "gi"));
								
							if (vMatches)				        		
							{
								vMatches.forEach (function (pMatch)
								{
									vOrScore += pField == 'bf_titre' ? 2 * (pMatch.length+1) : pMatch.length+1;	
									vMatchedFields++;
								});
						   }
						}
					})
					
					vOrScore *= vMatchedFields+1;
					
					vAndScore += vOrScore;
					
					if (vMatchedFields > 0) vMatchedOrs++;
				})

				if (vAndScore == 0)
				{
					pEntry.searchScore = 0;	
					return false;
				}

				vAndScore *= vMatchedOrs+1;

				pEntry.searchScore += vAndScore;

				if (vMatchedOrs > 0) vMatchedAnds++;

				return true;
		    });
		    
			pEntry.searchScore *= vMatchedAnds;

			vParsedKeywords.excludeds.forEach (function (pExcluded)
			{
				vThis.params.searchfields.forEach(function (pField)
				{
					var vFieldValue = pEntry[pField] ? pEntry[pField] : '';
					
					if (Array.isArray(vFieldValue)) vFieldValue = vFieldValue.join(' ');

					vFieldValue = vThis.removeDiatrics(vFieldValue);
						
					vFieldValue = vFieldValue.trim ();
						
					var vRegExp = vThis.extractRegExp (pExcluded);

					if (vFieldValue)
					{
						var vMatches = vFieldValue.match (new RegExp (pExcluded, "g"));
							
						if (vMatches)				        		
						{			        						        
							pEntry.searchScore = 0;
						}
					}
				})
			});
			
	        return pEntry.searchScore > 0;
		})

		vResult = vResult.sort((a, b) => ((a.searchScore > b.searchScore) ? -1 : 1));
		return vResult;
    },
    removeDiatrics(str) {
      return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
    }
  }
}
