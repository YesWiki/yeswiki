<?php
/*
$Id: wakka.php 861 2007-11-23 15:38:57Z gandon $
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Charles NEPOTE
Copyright 2002, 2003 Patrick PAUL
Copyright 2003  Eric DELORD
Copyright 2003  Eric FELDSTEIN
Copyright 2004  Jean Christophe ANDRé
Copyright 2006  Didier LOISEAU
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
// This may look a bit strange, but all possible formatting tags have to be in a single regular expression for this to work correctly. Yup!

if (!class_exists('WikiniFormatter'))
{
	class WikiniFormatter
	{
		var $wiki;
		var $oldIndentLevel = 0;
		var $oldIndentLength = 0;
		var $indentClosers = array ();
		var $newIndentSpace = array ();
		var $br = 1;
		var $title = 0;

		// in-line boxes stack
		// array(0 => array(tag => 'span', attr => array('style' => 'color: red;', 'onmouseover' => 'alert(\'Hello World !\')')))
		var $inLineStack = array();

		function __construct(& $wiki)
		{
			$this->wiki = & $wiki;
		}

		function & getInstance(& $wiki)
		{
			static $inst = null;
			if (empty ($inst))
				$inst = new WikiniFormatter($wiki);
			return $inst;
		}

		/**
		 * Formats the given $text.
		 * @param string $text The text to format,
		 * passed by reference for performances reasons
		 * and to avoid huge memory consumption.
		 * NB.: this method _DOES_ modify $text so that
		 * after the call it will be replaced by the formated
		 * text. To avoid this behaviour you might copy the value
		 * in another variable.
		 * @return string The formated text
		 * NB.: the value is returned by reference, and is
		 * a reference to the given $text. Be carrefull if
		 * you want to re-assign the parameter and keep
		 * the results (use unset());
		 */
		function &format(&$text)
		{
			$text = str_replace("\r", "", $text);
			$text = chop($text)."\n";
			$text = preg_replace_callback(
				"/\%\%.*?\%\%|"
				."\"\".*?\"\"|"
				."\[\[.*?\]\]|"
				.'\b[a-z0-9]+:\/\/[^ \t\n\r\f"\|\\\\\^\`\{\}\[\]><]+|'
				.'([\*\#@£_\/])\\1|'// attention à la référence arriére \1, à changer s'il y a d'autres parenthèses capturantes
				.'[<>"]|'
				.'&(?!(\#[xX][a-fA-F0-9]+|\#[0-9]+|[a-zA-Z0-9]+);)|'
				.'={2,6}|'
				.'-{3,}|'
				."\n(\t+|([ ]{1})+)(-|[[:alnum:]]+\))?|"
				."^(\t+|([ ]{1})+)(-|[[:alnum:]]+\))?|"
				."\{\{.*?\}\}|"
				.'\b' . WN_INTERWIKI_LINK . '\b|'
				.'\b' . WN_WIKI_LINK . '\b|'
				."\n/ms", array ($this, 'callback'), $text);

			// we're cutting the last <br />
			$text = preg_replace("/<br \/>$/", "", trim($text));
			return $text;
		}

		function titleHeader($level)
		{
			$this->br = 0;
			if ($this->title > 0)
			{
				$ret = '</h'.$this->title.'>';
				$this->title = 0;
				return $ret;
			}
			$this->title = $level;
			return "<h$level>";
		}

		function openTag($tag, $attr = array())
		{
			$res = '<' . $tag;
			foreach ($attr as $key => $value)
			{
				$res .= ' ' . $key . '="' . $value . '"';
			}
			$res .= '>';
			return $res;
		}

		function closeTag($tag)
		{
			return '</' . $tag . '>';
		}

		/**
		 * Opens all in-line tag from the given tag to the top of the stack
		 * @param int $from The first in-line tag to open (defaults to 0)
		 * @return string The HTML string that opens those tags
		 * @example If the stack is something like: array(0 => 'u', 1 => 'b', 2 => 'i')
		 * openAllInLine(1) would open the "b" and then the "i" (returns '<b><i>')
		 * openAllInLine() would open all elements in order (returns '<u><b><i>')
		 */
		function openAllInLine($from = 0)
		{
			$stack = &$this->inLineStack;
			$stack_size = count($stack);
			$res = '';
			for ($i = $from; $i < $stack_size; $i++)
			{
				$res .= $this->openTag($stack[$i]['tag'], $stack[$i]['attr']);
			}
			return $res;
		}

		/**
		 * Closes all in-line tag from the top of the stack to the given tag
		 * @param int $to The last in-line tag to close (defaults to 0)
		 * @return string The HTML string that closes those tags
		 * @example If the stack is something like: array(0 => 'u', 1 => 'b', 2 => 'span')
		 * closeAllInLine(1) would close the "span" and then the "b" (returns '</span></b>')
		 * closeAllInLine() would close all elements in reverse-order (returns '</span></b></u>')
		 */
		function closeAllInLine($to = 0)
		{
			$stack = &$this->inLineStack;
			$res = '';
			for ($i = count($stack) - 1; $i >= $to; $i--)
			{
				$res .= $this->closeTag($stack[$i]['tag']);
			}
			return $res;
		}

		/**
		 * Declares an in-line tag. If such a tag is already open,
		 * it will be closed (with respect to the other already opened
		 * tags), else it will be opened.
		 * @param string $tag The HTML in-line tag to open (for example 'u' or 'span')
		 * @param string $class The associated optionnal 'class=""' attribute
		 * (defaults to <tt>null</tt>)
		 * @param array $attr The associated optionnal other attributes
		 * @return string The HTML result of opening/closing this tag
		 */
		function inLineTag($tag, $class = null, $attr = array())
		{
			if ($class !== null)
			{
				$attr['class'] = $class;
			}
			$stack = &$this->inLineStack;
			$elem = array('tag' => $tag, 'attr' => $attr);
			$idx = array_search($elem, $stack);
			if ($idx === null || $idx === false) // depends on php version
			{   // not in array
				$stack[] = $elem;
				return $this->openTag($tag, $attr);
			}
			else
			{
				if (count($stack) == $idx - 1) // it's the last one !
				{
					array_pop($stack); // unset would not change the next insert index
					return $this->closeTag($tag);
				}
				else
				{
					$res = $this->closeAllInLine($idx);
					unset($stack[$idx]);
					$stack = array_values($stack); // keys 0 1 3 4 => 0 1 2 3
					return $res . $this->openAllInLine($idx);
				}
			}
		}

		function callback($things)
		{
			$thing = $things[0];
			$result = '';

			/**
			 *
			 * @var Wiki
			 */
			$wiki = $this->wiki;

			// convert HTML thingies
			switch ($thing)
			{
				case '<' :
					return "&lt;";
				case '>' :
					return "&gt;";
				case '&' :
					return "&amp;";
				case '"' :
					return "&quot;";
				case '**' : // bold
					return $this->inLineTag('b');
				case '//' : // italic
					return $this->inLineTag('i');
				case '__' : // underlinue
					return $this->inLineTag('u');
				case '##' : // monospace
					return $this->inLineTag('tt');
				case '@@' : // Deleted
					return $this->inLineTag('span', 'del');
				case '££' : // Inserted
					return $this->inLineTag('span', 'add');
				case '==' : // header level 5
					return $this->titleHeader(5);
				case '===' : // header level 4
					return $this->titleHeader(4);
				case '====' : // header level 3
					return $this->titleHeader(3);
				case '=====' : // header level 2
					return $this->titleHeader(2);
				case '======' : // header level 1
					return $this->titleHeader(1);
				case '---' : // forced line breaks
					return "<br />\n";
				case "\n" : // new lines
					//fermeture des balises de liste
					$c = count($this->indentClosers);
					if ($c)
					{
						$result .= $this->closeAllInLine();
					}
					for ($i = 0; $i < $c; $i ++)
					{
						$result .= array_pop($this->indentClosers);
						$this->br = 0;
					}
					if ($c)
					{
						$result .= $this->openAllInLine();
					}
					$this->oldIndentLevel = 0;
					$this->oldIndentLength = 0;
					$this->newIndentSpace = array ();

					$result .= ($this->br ? "<br />\n" : "\n");
					$this->br = 1;
					return $result;
				default : // more complex tags
					// urls
					if (preg_match('/^\b[a-z0-9]+:\/\/[^ \t\n\r\f"\|\\\\\^\`\{\}\[\]><]+$/', $thing))
					{
						// Retrieve url and transform it into valid HTML (htmlspecialchars)
						$url = htmlspecialchars($thing, ENT_COMPAT, YW_CHARSET);
						return "<a href=\"$url\">$url</a>";
					}
					// escaped text
					elseif (preg_match("/^\"\"(.*)\"\"$/s", $thing, $matches))
					{
						if ($wiki->getConfigValue('allow_raw_html'))
						{
							return $matches[1];
						}
						$res = htmlspecialchars($matches[1], ENT_COMPAT, YW_CHARSET);
						return preg_replace('/&amp;(\\#[xX][a-fA-F0-9]+|\\#[0-9]+|[a-zA-Z0-9]+);/', '&$1;', $res);
					}
					// code text
					elseif (preg_match("/^\%\%(.*)\%\%$/s", $thing, $matches))
					{
						// check if a language has been specified
						$code = $matches[1];
						if (preg_match("/^\((.+?)\)(.*)$/s", $code, $matches))
						{
							list (, $language, $code) = $matches;
						}
						else
						{
							$language = '';
						}

						//Select formatter for syntaxe hightlighting
						if (file_exists("formatters/coloration_".$language.".php"))
						{
							$formatter = "coloration_".$language;
						}
						else
						{
							$formatter = "code";
						}

						$output = "<div class=\"code\">";
						$output .= $wiki->Format(trim($code), $formatter);
						$output .= "</div>\n";

						return $output;
					}
					// raw inclusion from another wiki
					// (regexp documentation : see "forced link" below)
					elseif (preg_match("/^\[\[\|(\S*)(\s+(.+))?\]\]$/", $thing, $matches))
					{
						if (isset ($matches[3]))
						{
							list (, $url,, $text) = $matches;
						}
						else
						{
							$url = $matches[1];
							$text = '404';
						}

						if ($url)
						{
							$url .= "/wakka.php?wiki=".$text."/raw";
							return $wiki->Format($wiki->Format($url, "raw"), "wakka");
						}
						else
						{
							return htmlspecialchars($text, ENT_COMPAT, YW_CHARSET);
						}
					}
					// forced links
					// \S : any character that is not a whitespace character
					// \s : any whitespace character
					elseif (preg_match("/^\[\[(\S*)(\s+(.+))?\]\]$/", $thing, $matches))
					{
						if (isset ($matches[3]))
						{
							list (, $url,, $text) = $matches;
						}
						else
						{
							$url = $matches[1];
						}

						if ($url)
						{
                            // Early start/end of Inserted or Deleted ?
							if ($url != ($url = (preg_replace("/@@|££|\[\[/", "", $url))))
								$result = "</span>";
                            // Same filtering in the text (no need to
                            // filter ]] because there are none here
                            // by construct)
							$text = isset($text) ? preg_replace("/@@|££|\[\[/", "", $text) : '';
							return $result.$wiki->Link($url, "", $text);
						}
						else
						{ // if there is no URL, return at least the text
							return htmlspecialchars($text, ENT_COMPAT, YW_CHARSET);
						}
					}
					// events / action
					// process this regex before "indented text" regex to permits linebreak and space in action tag formatting
					elseif (preg_match("/^\{\{(.*?)\}\}$/s", $thing, $matches))
					{
						if ($matches[1]) {
							$this->br = 0;
							return $wiki->Action($matches[1]);
						}
						else
							return "{{}}";
					}
					// indented text
					elseif (preg_match('`(^|\n)(\t+|([ ]{1})+)(-|([[:alnum:]]+)\))?`s', $thing, $matches))
					{
						return $this->indentedText($matches);
					}
					// interwiki links!
					elseif (preg_match('`^' . WN_INTERWIKI_LINK . '$`', $thing))
					{
						return $wiki->Link($thing);
					}
					// wiki links!
					elseif (preg_match('`^' . WN_WIKI_LINK . '`', $thing))
					{
						return $wiki->Link($thing);
					}
					// separators
					elseif (preg_match("/-{4,}/", $thing, $matches))
					{
						// TODO: This could probably be improved for situations where someone puts text on the same line as a separator.
						//       Which is a stupid thing to do anyway! HAW HAW! Ahem.
						$this->br = 0;
						return "<hr />\n";
					}
					// if we reach this point, it must have been an accident.
					return htmlspecialchars($thing, ENT_COMPAT, YW_CHARSET);
			} // switch($thing)
		} // function callback

		function indentedText($matches)
		{
			$result = '';
			$closeLI = true;

			// S'il n'y a pas de NL avant l'item (c'est le cas oé on
			// est au debut de la page), alors on empéche qu'un <br />
			// ne soit produit
			if (strpos($matches[1], "\n") === false) {
				$this->br = 0;
			}
			// Ajout un saut de ligne si necessaire (c'est le cas oé
			// on est au debut d'une liste et pas au début d'une page,
			// car $this->br vaut encore 1)
			$result .= ($this->br ? "<br />\n" : "");

			// Les "\n" entre les Item de liste sont "mangés" par la
			// regexp des listes, et ceci évite que le NL de fin de
			// liste ne soit transformé abusivement en <br />
			$this->br = 0;

			//recherche du type de la liste
			if (isset ($matches[4]))
			{
				$newIndentType = $matches[4];
			}
			else
			{
				$newIndentType = '';
			}

			//calcul de la balise ouvrante/fermante selon le type de liste
			if (!$newIndentType)
			{
				$opener = "\n".'<ul style="list-style: none; ">';
				$closer = "</li>\n</ul>\n";
			}
			elseif ($newIndentType == '-')
			{
				$opener = "\n<ul>";
				$closer = "</li>\n</ul>\n";
			}
			else
			{
				// NB: <ol type="..."> est deprecié depuis HTML4.01 -> utilisation d'un style a la place
				if (preg_match('`[0-9]+`', $matches[4]))
					$style = 'style="list-style: decimal;"';
				if (preg_match('`[a-hj-z]+`', $matches[4]))
					$style = 'style="list-style: lower-alpha;"';
				if (preg_match('`[A-HJ-Z]+`', $matches[4]))
					$style = 'style="list-style: upper-alpha;"';
				if (preg_match('`[i]+`', $matches[4]))
					$style = 'style="list-style: lower-roman;"';
				if (preg_match('`[I]+`', $matches[4]))
					$style = 'style="list-style: upper-roman;"';
				$opener = "\n<ol $style>";
				$closer = "</li>\n</ol>\n";
			}

			//calcul du niveau d'indentation
			//si il y a des tabulations devant la liste alors le niveau = nbr de tab
			if (strpos($matches[2], "\t"))
			{
				$newIndentLevel = strlen($matches[2]);
			}
			else
			{ //pas de tab => la difference du nbre d'espace definie le niveau d'indentation
				$newIndentLevel = $this->oldIndentLevel;
				//longeur de la chaine d'indentaton
				$newIndentLength = strlen($matches[2]);
				if ($newIndentLength > $this->oldIndentLength)
				{
					//si la chaine d'indentation est plus longue que la precedente
					//on incremente le niveau d'indentation
					$newIndentLevel ++;
					//on stock la niveau correspondant a la longueur de la chaine d'indentation
					//la boucle for permet de corriger les erreurs de saisie d'espace.
					//$this->newIndentSpace[$newIndentLength]=$newIndentLevel;
					for ($i = $this->oldIndentLength + 1; $i <= $newIndentLength; $i ++)
					{
						$this->newIndentSpace[$i] = $newIndentLevel;
					}
				}
				elseif ($newIndentLength < $this->oldIndentLength)
				//si la chaine d'indentation est plus courte que la precedente
				//on recupere le niveau d'indentation correspondant a la longueur de la chaune
				$newIndentLevel = $this->newIndentSpace[$newIndentLength];
			}

			// quoi qu'il en soit, on va ouvrir ou fermer des boites de type bloc
			// il faut donc fermer les boites de type in-line
			$result .= $this->closeAllInLine();

			//si le nouveau level est plus grand
			if ($newIndentLevel > $this->oldIndentLevel)
			{
				for ($i = 0; $i < $newIndentLevel - $this->oldIndentLevel; $i ++)
				{
					//on ajoute le tag d'ouverture de liste
					$result .= $opener;
					//sauvegarde du tag de fermeture dans la pile
					array_push($this->indentClosers, $closer);
					$closeLI = false;
				}
			}
			//si le nouveau level est plus petit
			elseif ($newIndentLevel < $this->oldIndentLevel)
			{
				for ($i = 0; $i < $this->oldIndentLevel - $newIndentLevel; $i ++)
				{
					//on depile le tag de fermeture
					$result .= array_pop($this->indentClosers);
				}
				$closeLI = true;
			}
			//si c'est le meme level
			elseif ($newIndentLevel == $this->oldIndentLevel)
			{
				$closeLI = true;
			}

			$result .= ($closeLI ? '</li>' : '')."\n<li>";

			// quoi qu'il se soit passe, on a ouvert ou ferme des boites de type bloc
			// et on a du fermer les boites de type in-line
			// il faut donc les re-ouvrir maintenant
			$result .= $this->openAllInLine();

			$this->oldIndentLevel = $newIndentLevel;
			$this->oldIndentLength = $newIndentLength;

			return $result;
		} // function indentedText
	} // class WikiniFormatter
} // if !class_exists


//$form = & WikiniFormatter :: getInstance($this);
$form = new WikiniFormatter($this);
//$form->getInstance($this);
echo $form->format($text);

?>
