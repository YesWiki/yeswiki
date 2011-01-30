<?php
/*
action.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2003 David DELON
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

if (!function_exists("wakka2callback"))
{
	function wakka2callback($things)
	{
		$thing = $things[1];

		global $wiki;
	
		// events
		if (preg_match("/^\{\{(.*?)\}\}$/s", $thing, $matches))
		{
			if ($matches[1])
				return $wiki->Action($matches[1]);
			else
				return "{{}}";
		}
		else if (preg_match("/^.*$/s", $thing, $matches))
		{
			return "";
		}

		// if we reach this point, it must have been an accident.
		return $thing;
	}
}


$text = str_replace("\r", "", $text);
$text = trim($text)."\n";
$text = preg_replace_callback(
	"/(\{\{.*?\}\}|.*)/msU", "wakka2callback", $text);

echo $text ;
?>
