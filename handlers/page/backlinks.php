<?php

/*
backlinks.php : an handler allowing to get every backlink to any WikiNi page, even if it does not exist

Copyright 2004  Didier Loiseau
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
if(!defined('WIKINI_VERSION')){
    die ('acc&egrave;s direct interdit');
}

$res = $this->Action('backlinks');
echo $this->Header();
echo "<div class=\"page\" style=\"padding: 1em\">\n";
echo $res;
echo "\n</div>\n";
echo $this->Footer();

?> 