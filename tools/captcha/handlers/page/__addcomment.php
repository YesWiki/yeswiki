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

if($this->HasAccess('comment')) {

        if (!empty($_POST['submit'])) {
                $submit = $_POST['submit'];
        } else {
                $submit = false;
        }

	define("CAPTCHA_INCLUDE", TRUE);
        include 'tools/captcha/captcha.php';

	if(empty($_POST['captcha']))
	{
		$this->SetMessage("Ce commentaire n\'a pas &eacute;t&eacute; enregistr&eacute; car vous n\'avez pas entr&eacute; le mot de v&eacute;rification.");
		$this->Redirect($this->href());
		
	} elseif(!empty($_POST['captcha'])) {
		$wdcrypt = cryptWord($_POST['captcha']);
		if($wdcrypt != $_POST['captcha_hash'])
		{
			$this->SetMessage("Ce commentaire n\'a pas &eacute;t&eacute; enregistr&eacute; car le mot entr&eacute; ne correspond pas...");
			$this->Redirect($this->href());
		}
	}
}

?>
