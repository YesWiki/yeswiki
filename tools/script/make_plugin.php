<?php

require ('../libs/lib.files.php');

if (!empty($argv[1]))
{
        $fname = 'plugin-'.$argv[1];
        if (!empty($argv[2])) {
                $fname .= '-'.$argv[2];
        }
        $fname .= '.pkg.gz';

        packIt($argv[1], 'tmp/distrib/extension_base/tools', $fname, true, 'An error occured while creating the plugin.', $err);
	print $err;
}

function packIt($name,$root,$fname,$save,$err_msg,&$err)
{
	# Cr.ation du pack
	if (($res = files::makePackage($name,$root.'/'.$name,$root.'/',1)) !== false)
	{
		if ($save)
		{
			if (($fp = fopen('tmp/distrib/plugins/'.$fname,'w')) !== false)
			{
				fwrite($fp,$res,strlen($res));
				fclose($fp);
			}
			else
			{
				$err = '<p>'.$err_msg.'</p>';
			}
		}
	}
	else
	{
		$err = '<p>'.$err_msg.'</p>';
	}
}
