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


if ($this->HasAccess("write") && $this->HasAccess("read"))
{
	if (isset($_POST["submit"]) && $_POST["submit"] != 'Aperçu') {
		define("CAPTCHA_INCLUDE", TRUE);
		include 'tools/captcha/captcha.php';
		$crypt = cryptWord($textes[array_rand($textes)]);
		

		// afficher les champs de formulaire et de l'image		
		$ChampsCaptcha = '
  			<br />
			<h3>V&eacute;rification</h3>
			<div id="captcha">
				<img src="tools/captcha/captcha.php?'. $crypt .'" alt="captcha" />
				<div id="capt_f">
					<p>Veuillez r&eacute;ecrire le mot pr&eacute;sent dans l\'image si vous souhaitez <strong>sauvegarder la page</strong>.</p>
					<input type="hidden" name="captcha_hash" value="'. $crypt .'" />
					<label><input type="text" name="captcha" value="" /> (actualisez la page si le mot est illisible)</label>
				</div>
			</div>
		';
		$plugin_output_new=preg_replace ('/\<input name=\"submit\" type=\"submit\" value=\"Sauver\"/',
		$ChampsCaptcha.
		'<input name="submit" type="submit" value="Sauver"',
		$plugin_output_new);
	}
}

?>
