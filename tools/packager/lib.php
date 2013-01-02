<?php
# ***** BEGIN LICENSE BLOCK *****
# This file is part of DotClear.
# Copyright (c) 2004 Geoffrey Bachelet and contributors. All rights
# reserved.
#
# DotClear is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
# 
# DotClear is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with DotClear; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#
# ***** END LICENSE BLOCK *****
#
# Contributors :
# - Olivier Meunier
#

class dcPackager
{
	function packIt($name,$root,$fname,$save,$redir,$err_msg,&$err)
	{
		# Création du pack
		if (($res = files::makePackage($name,$root.'/'.$name,$root.'/',1)) !== false)
		{
			if ($save)
			{
				if (($fp = fopen(DC_SHARE_DIR.'/'.$fname,'w')) !== false)
				{
					fwrite($fp,$res,strlen($res));
					fclose($fp);
					header('Location: '.$redir);
					exit;
				}
				else
				{
					$err = '<p>'.$err_msg.'</p>';
				}
			}
			else
			{
				header('Content-Type: application/dotclear-pkg');
				header('Content-Disposition: attachment; filename='.$fname);
				echo $res;
				exit;
			}
		}
		else
		{
			$err = '<p>'.$err_msg.'</p>';
		}
	}
}
?>
