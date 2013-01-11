<?php
/*
Code original de ce fichier : Julien BALLESTRACCI
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Charles NEPOTE
Copyright  2003,2004  Eric FELDSTEIN
Copyright  2003  Jean-Pascal MILCENT
Patch: Captcha (c) 2007 Julien Ballestracci <julien@ecole-et-nature.org>
--
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
--
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

$tag = $this->GetPageTag();
if (
($this->GetMethod() == "edit") || 
((($_SESSION["show_comments"][$tag] == 1 || (isset($_GET['show_comments']) && $_GET['show_comments'] == '1')) 
&& $this->GetMethod() == "show"))
) {
	$plugin_output_new=preg_replace ('/<\/head>/',
	'
	<style type="text/css">
	#captcha {display: block;position: relative;margin-top: 15px;width: 100%;height: 70px;}
	#captcha img {float: left;position: relative;margin-right: 10px;}
	#capt_f {float: left;position: relative;display: block;}
	#capt_f p {margin: 0; margin-bottom: 7px;}
	#capt_f label {font-size: 10px;}
	</style>
	</head> 
	',
	$plugin_output_new);
}	
