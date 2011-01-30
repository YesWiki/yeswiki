<?php
# ***** BEGIN LICENSE BLOCK *****
# This file is part of DotClear.
# Copyright (c) 2004 Olivier Meunier and contributors. All rights
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

/* Missing functions
source : http://pear.php.net/package/PHP_Compat
*/

/* files_get_contents */
if (!function_exists('file_get_contents'))
{
	function file_get_contents($filename, $incpath = false, $resource_context = null)
	{
		if (false === $fh = fopen($filename, 'rb', $incpath))
		{
			trigger_error('file_get_contents() failed to open stream: No such file or directory', E_USER_WARNING);
			return false;
		}
		clearstatcache();
		if ($fsize = filesize($filename))
		{
			$data = fread($fh, $fsize);
		}
		else
		{
			while (!feof($fh)) {
				$data .= fread($fh, 8192);
			}
		}
		fclose($fh);
		return $data;
	}
}

if (!function_exists('is_a'))
{
	function is_a($obj, $classname)
	{
		if (strtolower(get_class($obj)) == strtolower($classname)) {
			return true;
		} else {
			return(is_subclass_of($obj, $classname));
		}
	}
}
?>
