<?php
// vim: set expandtab tabstop=4 shiftwidth=4 fdm=marker:
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Martin Jansen <mj@php.net>                                  |
// |                                                                      |
// +----------------------------------------------------------------------+
//
// $Id: RSS.php,v 1.1 2008-07-07 18:00:48 mrflos Exp $
//

require_once BAZ_CHEMIN.'libs'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'XML/Parser.php';

/**
* RSS parser class.
*
* This class is a parser for Resource Description Framework (RDF) Site
* Summary (RSS) documents. For more information on RSS see the
* website of the RSS working group (http://www.purl.org/rss/).
*
* @author Martin Jansen <mj@php.net>
* @version $Revision: 1.1 $
* @access  public
*/
class XML_RSS extends XML_Parser
{
    // {{{ properties

    /**
     * @var string
     */
    public $insideTag = '';

    /**
     * @var string
     */
    public $activeTag = '';

    /**
     * @var array
     */
    public $channel = array();

    /**
     * @var array
     */
    public $items = array();

    /**
     * @var array
     */
    public $item = array();

    /**
     * @var array
     */
    public $image = array();

    /**
     * @var array
     */
    public $textinput = array();

    /**
     * @var array
     */
    public $textinputs = array();

    /**
     * @var array
     */
    public $parentTags = array('CHANNEL', 'ITEM', 'IMAGE', 'TEXTINPUT');

    /**
     * @var array
     */
    public $channelTags = array('TITLE', 'LINK', 'DESCRIPTION', 'IMAGE',
                              'ITEMS', 'TEXTINPUT');

    /**
     * @var array
     */
    public $itemTags = array('TITLE', 'LINK', 'DESCRIPTION', 'PUBDATE');

    /**
     * @var array
     */
    public $imageTags = array('TITLE', 'URL', 'LINK');

    public $textinputTags = array('TITLE', 'DESCRIPTION', 'NAME', 'LINK');

    /**
     * List of allowed module tags
     *
     * Currently Dublin Core Metadata and the blogChannel RSS module
     * are supported.
     *
     * @var array
     */
    public $moduleTags = array('DC:TITLE', 'DC:CREATOR', 'DC:SUBJECT', 'DC:DESCRIPTION',
                            'DC:PUBLISHER', 'DC:CONTRIBUTOR', 'DC:DATE', 'DC:TYPE',
                            'DC:FORMAT', 'DC:IDENTIFIER', 'DC:SOURCE', 'DC:LANGUAGE',
                            'DC:RELATION', 'DC:COVERAGE', 'DC:RIGHTS',
                            'BLOGCHANNEL:BLOGROLL', 'BLOGCHANNEL:MYSUBSCRIPTIONS',
                            'BLOGCHANNEL:MYSUBSCRIPTIONS', 'BLOGCHANNEL:CHANGES');

    // }}}
    // {{{ Constructor

    /**
     * Constructor
     *
     * @access public
     * @param mixed File pointer or name of the RDF file.
     * @return void
     */
    public function XML_RSS($handle = '')
    {
        $this->XML_Parser();

        if (@is_resource($handle)) {
            $this->setInput($handle);
        } elseif ($handle != '') {
            $this->setInputFile($handle);
        } else {
            $this->raiseError('No filename passed.');
        }
    }

    // }}}
    // {{{ startHandler()

    /**
     * Start element handler for XML parser
     *
     * @access private
     * @param  object XML parser object
     * @param  string XML element
     * @param  array  Attributes of XML tag
     * @return void
     */
    public function startHandler($parser, $element, $attribs)
    {
        switch ($element) {
            case 'CHANNEL':
            case 'ITEM':
            case 'IMAGE':
            case 'TEXTINPUT':
                $this->insideTag = $element;
                break;

            default:
                $this->activeTag = $element;
        }
    }

    // }}}
    // {{{ endHandler()

    /**
     * End element handler for XML parser
     *
     * If the end of <item>, <channel>, <image> or <textinput>
     * is reached, this function updates the structure array
     * $this->struct[] and adds the field "type" to this array,
     * that defines the type of the current field.
     *
     * @access private
     * @param  object XML parser object
     * @param  string
     * @return void
     */
    public function endHandler($parser, $element)
    {
        if ($element == $this->insideTag) {
            $this->insideTag = '';
            $this->struct[] = array_merge(array('type' => strtolower($element)),
                                          $this->last);
        }

        if ($element == 'ITEM') {
            $this->items[] = $this->item;
            $this->item = '';
        }

        if ($element == 'IMAGE') {
            $this->images[] = $this->image;
            $this->image = '';
        }

        if ($element == 'TEXTINPUT') {
            $this->textinputs = $this->textinput;
            $this->textinput = '';
        }

        $this->activeTag = '';
    }

    // }}}
    // {{{ cdataHandler()

    /**
     * Handler for character data
     *
     * @access private
     * @param  object XML parser object
     * @param  string CDATA
     * @return void
     */
    public function cdataHandler($parser, $cdata)
    {
        if (in_array($this->insideTag, $this->parentTags)) {
            $tagName = strtolower($this->insideTag);
            $var = $this->{$tagName . 'Tags'};

            if (in_array($this->activeTag, $var) ||
                in_array($this->activeTag, $this->moduleTags)) {
                $this->_add($tagName, strtolower($this->activeTag),
                            $cdata);
            }

        }
    }

    // }}}
    // {{{ defaultHandler()

    /**
     * Default handler for XML parser
     *
     * @access private
     * @param  object XML parser object
     * @param  string CDATA
     * @return void
     */
    public function defaultHandler($parser, $cdata)
    {
        return;
    }

    // }}}
    // {{{ _add()

    /**
     * Add element to internal result sets
     *
     * @access private
     * @param  string Name of the result set
     * @param  string Fieldname
     * @param  string Value
     * @return void
     * @see    cdataHandler
     */
    public function _add($type, $field, $value)
    {
        if (empty($this->{$type}) || empty($this->{$type}[$field])) {
            $this->{$type}[$field] = $value;
        } else {
            $this->{$type}[$field] .= $value;
        }

        $this->last = $this->{$type};
    }

    // }}}
    // {{{ getStructure()

    /**
     * Get complete structure of RSS file
     *
     * @access public
     * @return array
     */
    public function getStructure()
    {
        return (array) $this->struct;
    }

    // }}}
    // {{{ getchannelInfo()

    /**
     * Get general information about current channel
     *
     * This function returns an array containing the information
     * that has been extracted from the <channel>-tag while parsing
     * the RSS file.
     *
     * @access public
     * @return array
     */
    public function getChannelInfo()
    {
        return (array) $this->channel;
    }

    // }}}
    // {{{ getItems()

    /**
     * Get items from RSS file
     *
     * This function returns an array containing the set of items
     * that are provided by the RSS file.
     *
     * @access public
     * @return array
     */
    public function getItems()
    {
        return (array) $this->items;
    }

    // }}}
    // {{{ getImages()

    /**
     * Get images from RSS file
     *
     * This function returns an array containing the set of images
     * that are provided by the RSS file.
     *
     * @access public
     * @return array
     */
    public function getImages()
    {
        return (array) $this->images;
    }

    // }}}
    // {{{ getTextinputs()

    /**
     * Get text input fields from RSS file
     *
     * @access public
     * @return array
     */
    public function getTextinputs()
    {
        return (array) $this->textinputs;
    }

    // }}}

}
