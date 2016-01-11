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

class form
{
    public function combo($name, $arryData, $default='', $class='', $id='', $tabindex='')
    {
        $res = '<select name="'.$name.'" ';
        
        if ($class != '') {
            $res .= 'class="'.$class.'" ';
        }
        
        if ($tabindex != '') {
            $res .= 'tabindex="'.$tabindex.'" ';
        }
        
        if ($id != '') {
            $res .= 'id="'.$id.'" ';
        } else {
            $res .= 'id="'.$name.'" ';
        }
        
        $res .= '>'."\n";
        
        foreach ($arryData as $k => $v) {
            $res .= '<option value="'.$v.'"';
            
            if ($v == $default) {
                $res .= ' selected="selected"';
            }
            
            $res .= '>'.$k.'</option>'."\n";
        }
        
        $res .= '</select>'."\n";
        
        return $res;
    }
    
    public function radio($name, $value, $checked='', $class='', $id='')
    {
        $res = '<input type="radio" name="'.$name.'" value="'.$value.'" ';
        
        if ($class != '') {
            $res .= 'class="'.$class.'" ';
        }
        
        if ($id != '') {
            $res .= 'id="'.$id.'" ';
        }
        
        if (($checked === 0) or $checked >= 1) {
            $res .= 'checked="checked" ';
        }
        
        $res .= '/>'."\n";
        
        return $res;
    }

    public function checkbox($name, $value, $checked='', $class='', $id='')
    {
        $res = '<input type="checkbox" name="'.$name.'" value="'.$value.'"';
        
        if ($class != '') {
            $res .= 'class="'.$class.'" ';
        }
        
        if ($id != '') {
            $res .= 'id="'.$id.'" ';
        }

        if ($checked != '') {
            $res.='checked="checked"';
        }
        
        $res .= ' />'."\n";

        return $res;
    }

    public function field($id, $size, $max, $default='', $tabindex='', $html='')
    {
        if (is_array($id)) {
            $name = $id[0];
            $id = isset($id[1]) ? $id[1] : '';
        } else {
            $name = $id;
        }
        
        $res = '<input type="text" size="'.$size.'" name="'.$name.'" ';
        
        $res .= ($id != '') ? 'id="'.$id.'" ' : '';
        $res .= ($max != '') ? 'maxlength="'.$max.'" ' : '';
        $res .= ($tabindex != '') ? 'tabindex="'.$tabindex.'" ' : '';
        $res .= ($default != '') ? 'value="'.$default.'" ' : '';
        $res .= $html;
        
        $res .= ' />';
        
        return $res;
    }
    
    public function textArea($id, $cols, $rows, $default='', $tabindex='', $html='')
    {
        $res = '<textarea cols="'.$cols.'" rows="'.$rows.'" ';
        $res .= 'name="'.$id.'" id="'.$id.'" ';
        $res .= ($tabindex != '') ? 'tabindex="'.$tabindex.'" ' : '';
        $res .= $html.'>';
        $res .= $default;
        $res .= '</textarea>';
        
        return $res;
    }
    
    public function hidden($id, $value)
    {
        return '<input type="hidden" name="'.$id.'" value="'.$value.'" />';
    }
}
