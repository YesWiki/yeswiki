<?php


list(,$endq)=split('&',$_SERVER[QUERY_STRING]);
list($val,$lieu)=split('=',$endq);

			$value=array();
			
			if (($val=='lieu') && isset($lieu)) {
				$lieu=ereg_replace('\*+','%',$lieu);
	            if ((strlen($lieu) > 1 ) && ($lieu != '%')) {
				    $query="SELECT DISTINCT name, code  FROM locations WHERE " .
				    "maj_name LIKE '".mysql_real_escape_string($lieu)."%' OR name LIKE '".mysql_real_escape_string(urldecode($lieu))."%' ORDER BY name LIMIT 50";
		           	$locations=$this->LoadAll($query);
	            }
	            else {
					$locations=array();	
				}
			}
			else {
				$locations=array();	
			}
		
                

			print "showQueryDiv(";	
	        print "\"".$lieu."\",";
	        $out1='';
	        $out2='';
	        foreach ($locations  as $row) {
	        	$out1.="\"".$row['name']." (".sprintf("%02s",$row['code']).")"."\"".",";	
	        	$out2.="\"\"".",";
    	    }
	        print "new Array(";
	        $out1=preg_replace('/,$/','',$out1);
	        print $out1;
	        print ") ";
	        print ",new Array(";
	        $out2=preg_replace('/,$/','',$out2);
	        print $out2;
	        print ")";
	        print ")";
	                
			

 
?>
