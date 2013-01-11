<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contains the Pager_Common class
 *
 * PHP versions 4 and 5
 *
 * LICENSE: Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category  HTML
 * @package   Pager
 * @author    Lorenzo Alberton <l.alberton@quipo.it>
 * @author    Richard Heyes <richard@phpguru.org>
 * @copyright 2003-2007 Lorenzo Alberton, Richard Heyes
 * @license   http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 * @version   CVS: $Id: Common.php,v 1.1 2008-07-07 18:00:48 mrflos Exp $
 * @link      http://pear.php.net/package/Pager
 */

/**
 * Two constants used to guess the path- and file-name of the page
 * when the user doesn't set any other value
 */
if (substr($_SERVER['PHP_SELF'], -1) == '/') {
    $http = (isset($_SERVER['HTTPS']) && ('on' == strtolower($_SERVER['HTTPS']))) ? 'https://' : 'http://';
    define('PAGER_CURRENT_FILENAME', '');
    define('PAGER_CURRENT_PATHNAME', $http.$_SERVER['HTTP_HOST'].str_replace('\\', '/', $_SERVER['PHP_SELF']));
} else {
    define('PAGER_CURRENT_FILENAME', preg_replace('/(.*)\?.*/', '\\1', basename($_SERVER['PHP_SELF'])));
    define('PAGER_CURRENT_PATHNAME', str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])));
}
/**
 * Error codes
 */
define('PAGER_OK',                         0);
define('ERROR_PAGER',                     -1);
define('ERROR_PAGER_INVALID',             -2);
define('ERROR_PAGER_INVALID_PLACEHOLDER', -3);
define('ERROR_PAGER_INVALID_USAGE',       -4);
define('ERROR_PAGER_NOT_IMPLEMENTED',     -5);

/**
 * Pager_Common - Common base class for [Sliding|Jumping] Window Pager
 * Extend this class to write a custom paging class
 *
 * @category  HTML
 * @package   Pager
 * @author    Lorenzo Alberton <l.alberton@quipo.it>
 * @author    Richard Heyes <richard@phpguru.org>
 * @copyright 2003-2007 Lorenzo Alberton, Richard Heyes
 * @license   http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 * @link      http://pear.php.net/package/Pager
 */
class Pager_Common
{
    // {{{ class vars

    /**
     * @var integer number of items
     * @access private
     */
    public $_totalItems;

    /**
     * @var integer number of items per page
     * @access private
     */
    public $_perPage     = 10;

    /**
     * @var integer number of page links for each window
     * @access private
     */
    public $_delta       = 10;

    /**
     * @var integer current page number
     * @access private
     */
    public $_currentPage = 1;

    /**
     * @var integer total pages number
     * @access private
     */
    public $_totalPages  = 1;

    /**
     * @var string CSS class for links
     * @access private
     */
    public $_linkClass   = '';

    /**
     * @var string wrapper for CSS class name
     * @access private
     */
    public $_classString = '';

    /**
     * @var string path name
     * @access private
     */
    public $_path        = PAGER_CURRENT_PATHNAME;

    /**
     * @var string file name
     * @access private
     */
    public $_fileName    = PAGER_CURRENT_FILENAME;

    /**
     * @var boolean If false, don't override the fileName option. Use at your own risk.
     * @access private
     */
    public $_fixFileName = true;

    /**
     * @var boolean you have to use FALSE with mod_rewrite
     * @access private
     */
    public $_append      = true;

    /**
     * @var string specifies which HTTP method to use
     * @access private
     */
    public $_httpMethod  = 'GET';

    /**
     * @var string specifies which HTML form to use
     * @access private
     */
    public $_formID      = '';

    /**
     * @var boolean whether or not to import submitted data
     * @access private
     */
    public $_importQuery = true;

    /**
     * @var string name of the querystring var for pageID
     * @access private
     */
    public $_urlVar      = 'pageID';

    /**
     * @var array data to pass through the link
     * @access private
     */
    public $_linkData    = array();

    /**
     * @var array additional URL vars
     * @access private
     */
    public $_extraVars   = array();

    /**
     * @var array URL vars to ignore
     * @access private
     */
    public $_excludeVars = array();

    /**
     * @var boolean TRUE => expanded mode (for Pager_Sliding)
     * @access private
     */
    public $_expanded    = true;

    /**
     * @var boolean TRUE => show accesskey attribute on <a> tags
     * @access private
     */
    public $_accesskey   = false;

    /**
     * @var string extra attributes for the <a> tag
     * @access private
     */
    public $_attributes  = '';

    /**
     * @var string onclick
     * @access private
     */
    public $_onclick = '';

    /**
     * @var string alt text for "first page" (use "%d" placeholder for page number)
     * @access private
     */
    public $_altFirst     = 'first page';

    /**
     * @var string alt text for "previous page"
     * @access private
     */
    public $_altPrev     = 'previous page';

    /**
     * @var string alt text for "next page"
     * @access private
     */
    public $_altNext     = 'next page';

    /**
     * @var string alt text for "last page" (use "%d" placeholder for page number)
     * @access private
     */
    public $_altLast     = 'last page';

    /**
     * @var string alt text for "page" (use optional "%d" placeholder for page number)
     * @access private
     */
    public $_altPage     = 'page';

    /**
     * @var string image/text to use as "prev" link
     * @access private
     */
    public $_prevImg     = '&lt;&lt; Back';

    /**
     * image/text to use as "prev" link when no prev link is needed  (e.g. on the first page)
     * NULL deactivates it
     *
     * @var string
     * @access private
     */
    public $_prevImgEmpty = null;

    /**
     * @var string image/text to use as "next" link
     * @access private
     */
    public $_nextImg     = 'Next &gt;&gt;';

    /**
     * image/text to use as "next" link when
     * no next link is needed (e.g. on the last page)
     * NULL deactivates it
     *
     * @var string
     * @access private
     */
    public $_nextImgEmpty = null;

    /**
     * @var string link separator
     * @access private
     */
    public $_separator   = '';

    /**
     * @var integer number of spaces before separator
     * @access private
     */
    public $_spacesBeforeSeparator = 0;

    /**
     * @var integer number of spaces after separator
     * @access private
     */
    public $_spacesAfterSeparator  = 1;

    /**
     * @var string CSS class name for current page link
     * @access private
     */
    public $_curPageLinkClassName  = '';

    /**
     * @var string Text before current page link
     * @access private
     */
    public $_curPageSpanPre        = '';

    /**
     * @var string Text after current page link
     * @access private
     */
    public $_curPageSpanPost       = '';

    /**
     * @var string Text before first page link
     * @access private
     */
    public $_firstPagePre  = '[';

    /**
     * @var string Text to be used for first page link
     * @access private
     */
    public $_firstPageText = '';

    /**
     * @var string Text after first page link
     * @access private
     */
    public $_firstPagePost = ']';

    /**
     * @var string Text before last page link
     * @access private
     */
    public $_lastPagePre   = '[';

    /**
     * @var string Text to be used for last page link
     * @access private
     */
    public $_lastPageText  = '';

    /**
     * @var string Text after last page link
     * @access private
     */
    public $_lastPagePost  = ']';

    /**
     * @var string Will contain the HTML code for the spaces
     * @access private
     */
    public $_spacesBefore  = '';

    /**
     * @var string Will contain the HTML code for the spaces
     * @access private
     */
    public $_spacesAfter   = '';

    /**
     * @var string $_firstLinkTitle
     * @access private
     */
    public $_firstLinkTitle = 'first page';

    /**
     * @var string $_nextLinkTitle
     * @access private
     */
    public $_nextLinkTitle = 'next page';

    /**
     * @var string $_prevLinkTitle
     * @access private
     */
    public $_prevLinkTitle = 'previous page';

    /**
     * @var string $_lastLinkTitle
     * @access private
     */
    public $_lastLinkTitle = 'last page';

    /**
     * @var string Text to be used for the 'show all' option in the select box
     * @access private
     */
    public $_showAllText   = '';

    /**
     * @var array data to be paged
     * @access private
     */
    public $_itemData      = null;

    /**
     * @var boolean If TRUE and there's only one page, links aren't shown
     * @access private
     */
    public $_clearIfVoid   = true;

    /**
     * @var boolean Use session for storing the number of items per page
     * @access private
     */
    public $_useSessions   = false;

    /**
     * @var boolean Close the session when finished reading/writing data
     * @access private
     */
    public $_closeSession  = false;

    /**
     * @var string name of the session var for number of items per page
     * @access private
     */
    public $_sessionVar    = 'setPerPage';

    /**
     * Pear error mode (when raiseError is called)
     * (see PEAR doc)
     *
     * @var integer $_pearErrorMode
     * @access private
     */
    public $_pearErrorMode = null;

    // }}}
    // {{{ public vars

    /**
     * @var string Complete set of links
     * @access public
     */
    public $links = '';

    /**
     * @var string Complete set of link tags
     * @access public
     */
    public $linkTags = '';

    /**
     * @var array Complete set of raw link tags
     * @access public
     */
    public $linkTagsRaw = array();

    /**
     * @var array Array with a key => value pair representing
     *            page# => bool value (true if key==currentPageNumber).
     *            can be used for extreme customization.
     * @access public
     */
    public $range = array();

    /**
     * @var array list of available options (safety check)
     * @access private
     */
    public $_allowed_options = array(
        'totalItems',
        'perPage',
        'delta',
        'linkClass',
        'path',
        'fileName',
        'fixFileName',
        'append',
        'httpMethod',
        'formID',
        'importQuery',
        'urlVar',
        'altFirst',
        'altPrev',
        'altNext',
        'altLast',
        'altPage',
        'prevImg',
        'prevImgEmpty',
        'nextImg',
        'nextImgEmpty',
        'expanded',
        'accesskey',
        'attributes',
        'onclick',
        'separator',
        'spacesBeforeSeparator',
        'spacesAfterSeparator',
        'curPageLinkClassName',
        'curPageSpanPre',
        'curPageSpanPost',
        'firstPagePre',
        'firstPageText',
        'firstPagePost',
        'lastPagePre',
        'lastPageText',
        'lastPagePost',
        'firstLinkTitle',
        'nextLinkTitle',
        'prevLinkTitle',
        'lastLinkTitle',
        'showAllText',
        'itemData',
        'clearIfVoid',
        'useSessions',
        'closeSession',
        'sessionVar',
        'pearErrorMode',
        'extraVars',
        'excludeVars',
        'currentPage',
    );

    // }}}
    // {{{ build()

    /**
     * Generate or refresh the links and paged data after a call to setOptions()
     *
     * @return void
     * @access public
     */
    public function build()
    {
        //reset
        $this->_pageData   = array();
        $this->links       = '';
        $this->linkTags    = '';
        $this->linkTagsRaw = array();

        $this->_generatePageData();
        $this->_setFirstLastText();

        if ($this->_totalPages > (2 * $this->_delta + 1)) {
            $this->links .= $this->_printFirstPage();
        }

        $this->links .= $this->_getBackLink();
        $this->links .= $this->_getPageLinks();
        $this->links .= $this->_getNextLink();

        $this->linkTags .= $this->_getFirstLinkTag();
        $this->linkTags .= $this->_getPrevLinkTag();
        $this->linkTags .= $this->_getNextLinkTag();
        $this->linkTags .= $this->_getLastLinkTag();

        $this->linkTagsRaw['first'] = $this->_getFirstLinkTag(true);
        $this->linkTagsRaw['prev']  = $this->_getPrevLinkTag(true);
        $this->linkTagsRaw['next']  = $this->_getNextLinkTag(true);
        $this->linkTagsRaw['last']  = $this->_getLastLinkTag(true);

        if ($this->_totalPages > (2 * $this->_delta + 1)) {
            $this->links .= $this->_printLastPage();
        }
    }

    // }}}
    // {{{ getPageData()

    /**
     * Returns an array of current pages data
     *
     * @param integer $pageID Desired page ID (optional)
     *
     * @return array Page data
     * @access public
     */
    public function getPageData($pageID = null)
    {
        $pageID = empty($pageID) ? $this->_currentPage : $pageID;

        if (!isset($this->_pageData)) {
            $this->_generatePageData();
        }
        if (!empty($this->_pageData[$pageID])) {
            return $this->_pageData[$pageID];
        }

        return array();
    }

    // }}}
    // {{{ getPageIdByOffset()

    /**
     * Returns pageID for given offset
     *
     * @param integer $index Offset to get pageID for
     *
     * @return integer PageID for given offset
     * @access public
     */
    public function getPageIdByOffset($index)
    {
        $msg = 'function "getPageIdByOffset()" not implemented.';

        return $this->raiseError($msg, ERROR_PAGER_NOT_IMPLEMENTED);
    }

    // }}}
    // {{{ getOffsetByPageId()

    /**
     * Returns offsets for given pageID. Eg, if you
     * pass it pageID one and your perPage limit is 10
     * it will return (1, 10). PageID of 2 would
     * give you (11, 20).
     *
     * @param integer $pageID PageID to get offsets for
     *
     * @return array First and last offsets
     * @access public
     */
    public function getOffsetByPageId($pageID = null)
    {
        $pageID = isset($pageID) ? $pageID : $this->_currentPage;
        if (!isset($this->_pageData)) {
            $this->_generatePageData();
        }

        if (isset($this->_pageData[$pageID]) || is_null($this->_itemData)) {
            return array(
                        max(($this->_perPage * ($pageID - 1)) + 1, 1),
                        min($this->_totalItems, $this->_perPage * $pageID)
                   );
        }

        return array(0, 0);
    }

    // }}}
    // {{{ getPageRangeByPageId()

    /**
     * Given a PageId, it returns the limits of the range of pages displayed.
     *
     * @param integer $pageID PageID to get offsets for
     *
     * @return array First and last offsets
     * @access public
     */
    public function getPageRangeByPageId($pageID = null)
    {
        $msg = 'function "getPageRangeByPageId()" not implemented.';

        return $this->raiseError($msg, ERROR_PAGER_NOT_IMPLEMENTED);
    }

    // }}}
    // {{{ getLinks()

    /**
     * Returns back/next/first/last and page links,
     * both as ordered and associative array.
     *
     * NB: in original PEAR::Pager this method accepted two parameters,
     * $back_html and $next_html. Now the only parameter accepted is
     * an integer ($pageID), since the html text for prev/next links can
     * be set in the factory. If a second parameter is provided, then
     * the method act as it previously did. This hack was done to mantain
     * backward compatibility only.
     *
     * @param integer $pageID Optional pageID. If specified, links for that
     *                           page are provided instead of current one.
     *                           [ADDED IN NEW PAGER VERSION]
     * @param string $next_html HTML to put inside the next link
     *                           [deprecated: use the factory instead]
     *
     * @return array back/next/first/last and page links
     * @access public
     */
    public function getLinks($pageID=null, $next_html='')
    {
        $msg = 'function "getLinks()" not implemented.';

        return $this->raiseError($msg, ERROR_PAGER_NOT_IMPLEMENTED);
    }

    // }}}
    // {{{ getCurrentPageID()

    /**
     * Returns ID of current page
     *
     * @return integer ID of current page
     * @access public
     */
    public function getCurrentPageID()
    {
        return $this->_currentPage;
    }

    // }}}
    // {{{ getNextPageID()

    /**
     * Returns next page ID. If current page is last page
     * this function returns FALSE
     *
     * @return mixed Next page ID or false
     * @access public
     */
    public function getNextPageID()
    {
        return ($this->getCurrentPageID() == $this->numPages() ? false : $this->getCurrentPageID() + 1);
    }

    // }}}
    // {{{ getPreviousPageID()

    /**
     * Returns previous page ID. If current page is first page
     * this function returns FALSE
     *
     * @return mixed Previous page ID or false
     * @access public
     */
    public function getPreviousPageID()
    {
        return $this->isFirstPage() ? false : $this->getCurrentPageID() - 1;
    }

    // }}}
    // {{{ numItems()

    /**
     * Returns number of items
     *
     * @return integer Number of items
     * @access public
     */
    public function numItems()
    {
        return $this->_totalItems;
    }

    // }}}
    // {{{ numPages()

    /**
     * Returns number of pages
     *
     * @return integer Number of pages
     * @access public
     */
    public function numPages()
    {
        return (int) $this->_totalPages;
    }

    // }}}
    // {{{ isFirstPage()

    /**
     * Returns whether current page is first page
     *
     * @return bool First page or not
     * @access public
     */
    public function isFirstPage()
    {
        return ($this->_currentPage < 2);
    }

    // }}}
    // {{{ isLastPage()

    /**
     * Returns whether current page is last page
     *
     * @return bool Last page or not
     * @access public
     */
    public function isLastPage()
    {
        return ($this->_currentPage == $this->_totalPages);
    }

    // }}}
    // {{{ isLastPageComplete()

    /**
     * Returns whether last page is complete
     *
     * @return bool Last age complete or not
     * @access public
     */
    public function isLastPageComplete()
    {
        return !($this->_totalItems % $this->_perPage);
    }

    // }}}
    // {{{ _generatePageData()

    /**
     * Calculates all page data
     *
     * @return void
     * @access private
     */
    public function _generatePageData()
    {
        // Been supplied an array of data?
        if (!is_null($this->_itemData)) {
            $this->_totalItems = count($this->_itemData);
        }
        $this->_totalPages = ceil((float) $this->_totalItems / (float) $this->_perPage);
        $i = 1;
        if (!empty($this->_itemData)) {
            foreach ($this->_itemData as $key => $value) {
                $this->_pageData[$i][$key] = $value;
                if (count($this->_pageData[$i]) >= $this->_perPage) {
                    $i++;
                }
            }
        } else {
            $this->_pageData = array();
        }

        //prevent URL modification
        $this->_currentPage = min($this->_currentPage, $this->_totalPages);
    }

    // }}}
    // {{{ _renderLink()

    /**
     * Renders a link using the appropriate method
     *
     * @param string $altText  Alternative text for this link (title property)
     * @param string $linkText Text contained by this link
     *
     * @return string The link in string form
     * @access private
     */
    public function _renderLink($altText, $linkText)
    {
        if ($this->_httpMethod == 'GET') {
            if ($this->_append) {
                $href = '?' . $this->_http_build_query_wrapper($this->_linkData);
            } else {
                $href = str_replace('%d', $this->_linkData[$this->_urlVar], $this->_fileName);
            }
            $onclick = '';
            if (array_key_exists($this->_urlVar, $this->_linkData)) {
                $onclick = str_replace('%d', $this->_linkData[$this->_urlVar], $this->_onclick);
            }

            return sprintf('<a href="%s"%s%s%s%s title="%s">%s</a>',
                           htmlentities($this->_url . $href, ENT_COMPAT, 'UTF-8'),
                           empty($this->_classString) ? '' : ' '.$this->_classString,
                           empty($this->_attributes)  ? '' : ' '.$this->_attributes,
                           empty($this->_accesskey)   ? '' : ' accesskey="'.$this->_linkData[$this->_urlVar].'"',
                           empty($onclick)            ? '' : ' onclick="'.$onclick.'"',
                           $altText,
                           $linkText
            );
        } elseif ($this->_httpMethod == 'POST') {
            $href = $this->_url;
            if (!empty($_GET)) {
                $href .= '?' . $this->_http_build_query_wrapper($_GET);
            }

            return sprintf("<a href='javascript:void(0)' onclick='%s'%s%s%s title='%s'>%s</a>",
                           $this->_generateFormOnClick($href, $this->_linkData),
                           empty($this->_classString) ? '' : ' '.$this->_classString,
                           empty($this->_attributes)  ? '' : ' '.$this->_attributes,
                           empty($this->_accesskey)   ? '' : ' accesskey=\''.$this->_linkData[$this->_urlVar].'\'',
                           $altText,
                           $linkText
            );
        }

        return '';
    }

    // }}}
    // {{{ _generateFormOnClick()

    /**
     * Mimics http_build_query() behavior in the way the data
     * in $data will appear when it makes it back to the server.
     *  For example:
     * $arr =  array('array' => array(array('hello', 'world'),
     *                                'things' => array('stuff', 'junk'));
     * http_build_query($arr)
     * and _generateFormOnClick('foo.php', $arr)
     * will yield
     * $_REQUEST['array'][0][0] === 'hello'
     * $_REQUEST['array'][0][1] === 'world'
     * $_REQUEST['array']['things'][0] === 'stuff'
     * $_REQUEST['array']['things'][1] === 'junk'
     *
     * However, instead of  generating a query string, it generates
     * Javascript to create and submit a form.
     *
     * @param string $formAction where the form should be submitted
     * @param array  $data       the associative array of names and values
     *
     * @return string A string of javascript that generates a form and submits it
     * @access private
     */
    public function _generateFormOnClick($formAction, $data)
    {
        // Check we have an array to work with
        if (!is_array($data)) {
            trigger_error(
                '_generateForm() Parameter 1 expected to be Array or Object. Incorrect value given.',
                E_USER_WARNING
            );

            return false;
        }

        if (!empty($this->_formID)) {
            $str = 'var form = document.getElementById("'.$this->_formID.'"); var input = ""; ';
        } else {
            $str = 'var form = document.createElement("form"); var input = ""; ';
        }

        // We /shouldn't/ need to escape the URL ...
        $str .= sprintf('form.action = "%s"; ', htmlentities($formAction, ENT_COMPAT, 'UTF-8'));
        $str .= sprintf('form.method = "%s"; ', $this->_httpMethod);
        foreach ($data as $key => $val) {
            $str .= $this->_generateFormOnClickHelper($val, $key);
        }

        if (empty($this->_formID)) {
            $str .= 'document.getElementsByTagName("body")[0].appendChild(form);';
        }

        $str .= 'form.submit(); return false;';

        return $str;
    }

    // }}}
    // {{{ _generateFormOnClickHelper

    /**
     * This is used by _generateFormOnClick().
     * Recursively processes the arrays, objects, and literal values.
     *
     * @param mixed  $data Data that should be rendered
     * @param string $prev The name so far
     *
     * @return string A string of Javascript that creates form inputs
     *                representing the data
     * @access private
     */
    public function _generateFormOnClickHelper($data, $prev = '')
    {
        $str = '';
        if (is_array($data) || is_object($data)) {
            // foreach key/visible member
            foreach ((array) $data as $key => $val) {
                // append [$key] to prev
                $tempKey = sprintf('%s[%s]', $prev, $key);
                $str .= $this->_generateFormOnClickHelper($val, $tempKey);
            }
        } else {  // must be a literal value
            // escape newlines and carriage returns
            $search  = array("\n", "\r");
            $replace = array('\n', '\n');
            $escapedData = str_replace($search, $replace, $data);
            // am I forgetting any dangerous whitespace?
            // would a regex be faster?
            // if it's already encoded, don't encode it again
            if (!$this->_isEncoded($escapedData)) {
                $escapedData = urlencode($escapedData);
            }
            $escapedData = htmlentities($escapedData, ENT_QUOTES, 'UTF-8');

            $str .= 'input = document.createElement("input"); ';
            $str .= 'input.type = "hidden"; ';
            $str .= sprintf('input.name = "%s"; ', $prev);
            $str .= sprintf('input.value = "%s"; ', $escapedData);
            $str .= 'form.appendChild(input); ';
        }

        return $str;
    }

    // }}}
    // {{{ _getLinksData()

    /**
     * Returns the correct link for the back/pages/next links
     *
     * @return array Data
     * @access private
     */
    public function _getLinksData()
    {
        $qs = array();
        if ($this->_importQuery) {
            if ($this->_httpMethod == 'POST') {
                $qs = $_POST;
            } elseif ($this->_httpMethod == 'GET') {
                $qs = $_GET;
            }
        }
        foreach ($this->_excludeVars as $exclude) {
            if (array_key_exists($exclude, $qs)) {
                unset($qs[$exclude]);
            }
        }
        if (count($this->_extraVars)) {
            $this->_recursive_urldecode($this->_extraVars);
            $qs = array_merge($qs, $this->_extraVars);
        }
        if (count($qs) && function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
            $this->_recursive_stripslashes($qs);
        }

        return $qs;
    }

    // }}}
    // {{{ _recursive_stripslashes()

    /**
     * Helper method
     *
     * @param string|array &$var variable to clean
     *
     * @return void
     * @access private
     */
    public function _recursive_stripslashes(&$var)
    {
        if (is_array($var)) {
            foreach (array_keys($var) as $k) {
                $this->_recursive_stripslashes($var[$k]);
            }
        } else {
            $var = stripslashes($var);
        }
    }

    // }}}
    // {{{ _recursive_urldecode()

    /**
     * Helper method
     *
     * @param string|array &$var variable to decode
     *
     * @return void
     * @access private
     */
    public function _recursive_urldecode(&$var)
    {
        if (is_array($var)) {
            foreach (array_keys($var) as $k) {
                $this->_recursive_urldecode($var[$k]);
            }
        } else {
            $trans_tbl = array_flip(get_html_translation_table(HTML_ENTITIES));
            $var = strtr($var, $trans_tbl);
        }
    }

    // }}}
    // {{{ _getBackLink()

    /**
     * Returns back link
     *
     * @param string $url  URL to use in the link  [deprecated: use the factory instead]
     * @param string $link HTML to use as the link [deprecated: use the factory instead]
     *
     * @return string The link
     * @access private
     */
    public function _getBackLink($url='', $link='')
    {
        //legacy settings... the preferred way to set an option
        //now is passing it to the factory
        if (!empty($url)) {
            $this->_path = $url;
        }
        if (!empty($link)) {
            $this->_prevImg = $link;
        }
        $back = '';
        if ($this->_currentPage > 1) {
            $this->_linkData[$this->_urlVar] = $this->getPreviousPageID();
            $back = $this->_renderLink($this->_altPrev, $this->_prevImg)
                  . $this->_spacesBefore . $this->_spacesAfter;
        } elseif ($this->_prevImgEmpty !== null) {
            $back = $this->_prevImgEmpty
                  . $this->_spacesBefore . $this->_spacesAfter;
        }

        return $back;
    }

    // }}}
    // {{{ _getPageLinks()

    /**
     * Returns pages link
     *
     * @param string $url URL to use in the link [deprecated: use the factory instead]
     *
     * @return string Links
     * @access private
     */
    public function _getPageLinks($url='')
    {
        $msg = 'function "_getPageLinks()" not implemented.';

        return $this->raiseError($msg, ERROR_PAGER_NOT_IMPLEMENTED);
    }

    // }}}
    // {{{ _getNextLink()

    /**
     * Returns next link
     *
     * @param string $url  URL to use in the link  [deprecated: use the factory instead]
     * @param string $link HTML to use as the link [deprecated: use the factory instead]
     *
     * @return string The link
     * @access private
     */
    public function _getNextLink($url='', $link='')
    {
        //legacy settings... the preferred way to set an option
        //now is passing it to the factory
        if (!empty($url)) {
            $this->_path = $url;
        }
        if (!empty($link)) {
            $this->_nextImg = $link;
        }
        $next = '';
        if ($this->_currentPage < $this->_totalPages) {
            $this->_linkData[$this->_urlVar] = $this->getNextPageID();
            $next = $this->_spacesAfter
                  . $this->_renderLink($this->_altNext, $this->_nextImg)
                  . $this->_spacesBefore . $this->_spacesAfter;
        } elseif ($this->_nextImgEmpty !== null) {
            $next = $this->_spacesAfter
                  . $this->_nextImgEmpty
                  . $this->_spacesBefore . $this->_spacesAfter;
        }

        return $next;
    }

    // }}}
    // {{{ _getFirstLinkTag()

    /**
     * Returns first link tag
     *
     * @param bool $raw should tag returned as array
     *
     * @return mixed string with html link tag or separated as array
     * @access private
     */
    public function _getFirstLinkTag($raw = false)
    {
        if ($this->isFirstPage() || ($this->_httpMethod != 'GET')) {
            return $raw ? array() : '';
        }
        if ($raw) {
            return array(
                'url'   => $this->_getLinkTagUrl(1),
                'title' => $this->_firstLinkTitle
            );
        }

        return sprintf('<link rel="first" href="%s" title="%s" />'."\n",
            $this->_getLinkTagUrl(1),
            $this->_firstLinkTitle
        );
    }

    // }}}
    // {{{ _getPrevLinkTag()

    /**
     * Returns previous link tag
     *
     * @param bool $raw should tag returned as array
     *
     * @return mixed string with html link tag or separated as array
     * @access private
     */
    public function _getPrevLinkTag($raw = false)
    {
        if ($this->isFirstPage() || ($this->_httpMethod != 'GET')) {
            return $raw ? array() : '';
        }
        if ($raw) {
            return array(
                'url'   => $this->_getLinkTagUrl($this->getPreviousPageID()),
                'title' => $this->_prevLinkTitle
            );
        }

        return sprintf('<link rel="previous" href="%s" title="%s" />'."\n",
            $this->_getLinkTagUrl($this->getPreviousPageID()),
            $this->_prevLinkTitle
        );
    }

    // }}}
    // {{{ _getNextLinkTag()

    /**
     * Returns next link tag
     *
     * @param bool $raw should tag returned as array
     *
     * @return mixed string with html link tag or separated as array
     * @access private
     */
    public function _getNextLinkTag($raw = false)
    {
        if ($this->isLastPage() || ($this->_httpMethod != 'GET')) {
            return $raw ? array() : '';
        }
        if ($raw) {
            return array(
                'url'   => $this->_getLinkTagUrl($this->getNextPageID()),
                'title' => $this->_nextLinkTitle
            );
        }

        return sprintf('<link rel="next" href="%s" title="%s" />'."\n",
            $this->_getLinkTagUrl($this->getNextPageID()),
            $this->_nextLinkTitle
        );
    }

    // }}}
    // {{{ _getLastLinkTag()

    /**
     * Returns last link tag
     *
     * @param bool $raw should tag returned as array
     *
     * @return mixed string with html link tag or separated as array
     * @access private
     */
    public function _getLastLinkTag($raw = false)
    {
        if ($this->isLastPage() || ($this->_httpMethod != 'GET')) {
            return $raw ? array() : '';
        }
        if ($raw) {
            return array(
                'url'   => $this->_getLinkTagUrl($this->_totalPages),
                'title' => $this->_lastLinkTitle
            );
        }

        return sprintf('<link rel="last" href="%s" title="%s" />'."\n",
            $this->_getLinkTagUrl($this->_totalPages),
            $this->_lastLinkTitle
        );
    }

    // }}}
    // {{{ _getLinkTagUrl()

    /**
     * Helper method
     *
     * @param integer $pageID page ID
     *
     * @return string the link tag url
     * @access private
     */
    public function _getLinkTagUrl($pageID)
    {
        $this->_linkData[$this->_urlVar] = $pageID;
        if ($this->_append) {
            $href = '?' . $this->_http_build_query_wrapper($this->_linkData);
        } else {
            $href = str_replace('%d', $this->_linkData[$this->_urlVar], $this->_fileName);
        }

        return htmlentities($this->_url . $href, ENT_COMPAT, 'UTF-8');
    }

    // }}}
    // {{{ getPerPageSelectBox()

    /**
     * Returns a string with a XHTML SELECT menu,
     * useful for letting the user choose how many items per page should be
     * displayed. If parameter useSessions is TRUE, this value is stored in
     * a session var. The string isn't echoed right now so you can use it
     * with template engines.
     *
     * @param integer $start       starting value for the select menu
     * @param integer $end         ending value for the select menu
     * @param integer $step        step between values in the select menu
     * @param boolean $showAllData If true, perPage is set equal to totalItems.
     * @param array   $extraParams (or string $optionText for BC reasons)
     *                - 'optionText': text to show in each option.
     *                  Use '%d' where you want to see the number of pages selected.
     *                - 'attributes': (html attributes) Tag attributes or
     *                  HTML attributes (id="foo" pairs), will be inserted in the
     *                  <select> tag
     *
     * @return string xhtml select box
     * @access public
     */
    public function getPerPageSelectBox($start=5, $end=30, $step=5, $showAllData=false, $extraParams=array())
    {
        include_once 'Pager/HtmlWidgets.php';
        $widget = new Pager_HtmlWidgets($this);

        return $widget->getPerPageSelectBox($start, $end, $step, $showAllData, $extraParams);
    }

    // }}}
    // {{{ getPageSelectBox()

    /**
     * Returns a string with a XHTML SELECT menu with the page numbers,
     * useful as an alternative to the links
     *
     * @param array $params - 'optionText': text to show in each option.
     *                                  Use '%d' where you want to see the number
     *                                  of pages selected.
     *                                - 'autoSubmit': if TRUE, add some js code
     *                                  to submit the form on the onChange event
     * @param string $extraAttributes (html attributes) Tag attributes or
     *                                HTML attributes (id="foo" pairs), will be
     *                                inserted in the <select> tag
     *
     * @return string xhtml select box
     * @access public
     */
    public function getPageSelectBox($params = array(), $extraAttributes = '')
    {
        include_once 'Pager/HtmlWidgets.php';
        $widget = new Pager_HtmlWidgets($this);

        return $widget->getPageSelectBox($params, $extraAttributes);
    }

    // }}}
    // {{{ _printFirstPage()

    /**
     * Print [1]
     *
     * @return string String with link to 1st page,
     *                or empty string if this is the 1st page.
     * @access private
     */
    public function _printFirstPage()
    {
        if ($this->isFirstPage()) {
            return '';
        }
        $this->_linkData[$this->_urlVar] = 1;

        return $this->_renderLink(
                str_replace('%d', 1, $this->_altFirst),
                $this->_firstPagePre . $this->_firstPageText . $this->_firstPagePost
        ) . $this->_spacesBefore . $this->_spacesAfter;
    }

    // }}}
    // {{{ _printLastPage()

    /**
     * Print [numPages()]
     *
     * @return string String with link to last page,
     *                or empty string if this is the 1st page.
     * @access private
     */
    public function _printLastPage()
    {
        if ($this->isLastPage()) {
            return '';
        }
        $this->_linkData[$this->_urlVar] = $this->_totalPages;

        return $this->_renderLink(
                str_replace('%d', $this->_totalPages, $this->_altLast),
                $this->_lastPagePre . $this->_lastPageText . $this->_lastPagePost
        );
    }

    // }}}
    // {{{ _setFirstLastText()

    /**
     * sets the private _firstPageText, _lastPageText variables
     * based on whether they were set in the options
     *
     * @return void
     * @access private
     */
    public function _setFirstLastText()
    {
        if ($this->_firstPageText == '') {
            $this->_firstPageText = '1';
        }
        if ($this->_lastPageText == '') {
            $this->_lastPageText = $this->_totalPages;
        }
    }

    // }}}
    // {{{ _http_build_query_wrapper()

    /**
     * This is a slightly modified version of the http_build_query() function;
     * it heavily borrows code from PHP_Compat's http_build_query().
     * The main change is the usage of htmlentities instead of urlencode,
     * since it's too aggressive
     *
     * @param array $data array of querystring values
     *
     * @return string
     * @access private
     */
    public function _http_build_query_wrapper($data)
    {
        $data = (array) $data;
        if (empty($data)) {
            return '';
        }
        $separator = ini_get('arg_separator.output');
        if ($separator == '&amp;') {
            $separator = '&'; //the string is escaped by htmlentities anyway...
        }
        $tmp = array ();
        foreach ($data as $key => $val) {
            if (is_scalar($val)) {
                //array_push($tmp, $key.'='.$val);
                $val = urlencode($val);
                array_push($tmp, $key .'='. str_replace('%2F', '/', $val));
                continue;
            }
            // If the value is an array, recursively parse it
            if (is_array($val)) {
                array_push($tmp, $this->__http_build_query($val, urlencode($key)));
                continue;
            }
        }

        return implode($separator, $tmp);
    }

    // }}}
    // {{{ __http_build_query()

    /**
     * Helper function
     *
     * @param array  $array array of querystring values
     * @param string $name  key
     *
     * @return string
     * @access private
     */
    public function __http_build_query($array, $name)
    {
        $tmp = array ();
        $separator = ini_get('arg_separator.output');
        if ($separator == '&amp;') {
            $separator = '&'; //the string is escaped by htmlentities anyway...
        }
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                //array_push($tmp, $this->__http_build_query($value, sprintf('%s[%s]', $name, $key)));
                array_push($tmp, $this->__http_build_query($value, $name.'%5B'.$key.'%5D'));
            } elseif (is_scalar($value)) {
                //array_push($tmp, sprintf('%s[%s]=%s', $name, htmlentities($key), htmlentities($value)));
                array_push($tmp, $name.'%5B'.urlencode($key).'%5D='.urlencode($value));
            } elseif (is_object($value)) {
                //array_push($tmp, $this->__http_build_query(get_object_vars($value), sprintf('%s[%s]', $name, $key)));
                array_push($tmp, $this->__http_build_query(get_object_vars($value), $name.'%5B'.$key.'%5D'));
            }
        }

        return implode($separator, $tmp);
    }

    // }}}
    // {{{ _isEncoded()

    /**
     * Helper function
     * Check if a string is an encoded multibyte string
     *
     * @param string $string string to check
     *
     * @return boolean
     * @access private
     */

    public function _isEncoded($string)
    {
        $hexchar = '&#[\dA-Fx]{2,};';

        return preg_match("/^(\s|($hexchar))*$/Uims", $string) ? true : false;
    }

    // }}}
    // {{{ raiseError()

    /**
     * conditionally includes PEAR base class and raise an error
     *
     * @param string  $msg  Error message
     * @param integer $code Error code
     *
     * @return PEAR_Error
     * @access private
     */
    public function raiseError($msg, $code)
    {
        include_once 'PEAR.php';
        if (empty($this->_pearErrorMode)) {
            $this->_pearErrorMode = PEAR_ERROR_RETURN;
        }

        return PEAR::raiseError($msg, $code, $this->_pearErrorMode);
    }

    // }}}
    // {{{ setOptions()

    /**
     * Set and sanitize options
     *
     * @param mixed $options An associative array of option names and their values
     *
     * @return integer error code (PAGER_OK on success)
     * @access public
     */
    public function setOptions($options)
    {
        foreach ($options as $key => $value) {
            if (in_array($key, $this->_allowed_options) && (!is_null($value))) {
                $this->{'_' . $key} = $value;
            }
        }

        //autodetect http method
        if (!isset($options['httpMethod'])
            && !isset($_GET[$this->_urlVar])
            && isset($_POST[$this->_urlVar])
        ) {
            $this->_httpMethod = 'POST';
        } else {
            $this->_httpMethod = strtoupper($this->_httpMethod);
        }

        $this->_fileName = ltrim($this->_fileName, '/');  //strip leading slash
        $this->_path     = rtrim($this->_path, '/');      //strip trailing slash

        if ($this->_append) {
            if ($this->_fixFileName) {
                $this->_fileName = PAGER_CURRENT_FILENAME; //avoid possible user error;
            }
            $this->_url = $this->_path.(!empty($this->_path) ? '/' : '').$this->_fileName;
        } else {
            $this->_url = $this->_path;
            if (0 != strncasecmp($this->_fileName, 'javascript', 10)) {
                $this->_url .= (!empty($this->_path) ? '/' : '');
            }
            if (false === strpos($this->_fileName, '%d')) {
                trigger_error($this->errorMessage(ERROR_PAGER_INVALID_USAGE), E_USER_WARNING);
            }
        }
        if (false === strpos($this->_altPage, '%d')) {
            //by default, append page number at the end
            $this->_altPage .= ' %d';
        }

        $this->_classString = '';
        if (strlen($this->_linkClass)) {
            $this->_classString = 'class="'.$this->_linkClass.'"';
        }

        if (strlen($this->_curPageLinkClassName)) {
            $this->_curPageSpanPre  .= '<span class="'.$this->_curPageLinkClassName.'">';
            $this->_curPageSpanPost = '</span>' . $this->_curPageSpanPost;
        }

        $this->_perPage = max($this->_perPage, 1); //avoid possible user errors

        if ($this->_useSessions && !isset($_SESSION)) {
            session_start();
        }
        if (!empty($_REQUEST[$this->_sessionVar])) {
            $this->_perPage = max(1, (int) $_REQUEST[$this->_sessionVar]);
            if ($this->_useSessions) {
                $_SESSION[$this->_sessionVar] = $this->_perPage;
            }
        }

        if (!empty($_SESSION[$this->_sessionVar]) && $this->_useSessions) {
             $this->_perPage = $_SESSION[$this->_sessionVar];
        }

        if ($this->_closeSession) {
            session_write_close();
        }

        $this->_spacesBefore = str_repeat('&nbsp;', $this->_spacesBeforeSeparator);
        $this->_spacesAfter  = str_repeat('&nbsp;', $this->_spacesAfterSeparator);

        if (isset($_REQUEST[$this->_urlVar]) && empty($options['currentPage'])) {
            $this->_currentPage = (int) $_REQUEST[$this->_urlVar];
        }
        $this->_currentPage = max($this->_currentPage, 1);
        $this->_linkData = $this->_getLinksData();

        return PAGER_OK;
    }

    // }}}
    // {{{ getOption()

    /**
     * Return the current value of a given option
     *
     * @param string $name option name
     *
     * @return mixed option value
     * @access public
     */
    public function getOption($name)
    {
        if (!in_array($name, $this->_allowed_options)) {
            $msg = 'invalid option: '.$name;

            return $this->raiseError($msg, ERROR_PAGER_INVALID);
        }

        return $this->{'_' . $name};
    }

    // }}}
    // {{{ getOptions()

    /**
     * Return an array with all the current pager options
     *
     * @return array list of all the pager options
     * @access public
     */
    public function getOptions()
    {
        $options = array();
        foreach ($this->_allowed_options as $option) {
            $options[$option] = $this->{'_' . $option};
        }

        return $options;
    }

    // }}}
    // {{{ errorMessage()

    /**
     * Return a textual error message for a PAGER error code
     *
     * @param integer $code error code
     *
     * @return string error message
     * @access public
     */
    public function errorMessage($code)
    {
        static $errorMessages;
        if (!isset($errorMessages)) {
            $errorMessages = array(
                ERROR_PAGER                     => 'unknown error',
                ERROR_PAGER_INVALID             => 'invalid',
                ERROR_PAGER_INVALID_PLACEHOLDER => 'invalid format - use "%d" as placeholder.',
                ERROR_PAGER_INVALID_USAGE       => 'if $options[\'append\'] is set to false, '
                                                  .' $options[\'fileName\'] MUST contain the "%d" placeholder.',
                ERROR_PAGER_NOT_IMPLEMENTED     => 'not implemented'
            );
        }

        return (isset($errorMessages[$code]) ?
            $errorMessages[$code] : $errorMessages[ERROR_PAGER]);
    }

    // }}}
}
