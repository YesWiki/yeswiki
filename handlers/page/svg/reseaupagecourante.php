<?php
/*
 * $Id: reseaupagecourante.php 845 2007-08-23 19:10:25Z lordfarquaad $
 * Description : Handler pour l'affichage SVG du réseau de liens d'une page wikini
 * auteurs : Yann Le Guennec - Charles Nepote
 * version : 0.0.6
 * 
 * Paramétres utilisateurs :
 * &script=on : active le script de déplacement des mots
 * &subnet=off : desactive le réseau de liens secondaires
 * &center=off : ne centre pas le rectangle de la page courante
 * &style=dark | white (defaut) : styles de couleurs
 * 
 * Licence GPL
 */


$styles = array("white"=>"", "dark"=>"");
$affich_param = ""; // pour affichage dans le commentaire du source SVG 
$svg_uri = "/svg&amp;svg=reseaupagecourante";
if(isset($_GET['script']) && $_GET['script'] == 'on') {
  $script = true;
  $svg_uri .= "&amp;script=on";
  $affich_param .= "    script : on\n";
} else {
  $script = false;
  $affich_param .= "    script : off\n";
}
if(isset($_GET['subnet']) && $_GET['subnet'] == 'off') {
  $subnet = false;
  $svg_uri .= "&amp;subnet=off";
  $affich_param .= "    subnet : off\n";
} else {
  $subnet = true;
  $affich_param .= "    subnet : on\n";
}
if(isset($_GET['center']) && $_GET['center'] == 'off') {
  $center = false;
  $svg_uri .= "&amp;center=off";
  $affich_param .= "    center : off\n";
} else {
  $center = true;
  $affich_param .= "    center : on\n";
}
if(isset($_GET['style'])) {
  $style = $_GET['style'];
  if(isset($styles[$style])) {
    $svg_uri .= "&amp;style=$style";
  } else {
    $style = "white";
  }
} else {
  $style = "white";
}
$affich_param .= "    style : $style\n";

$n = 0; // nombre de pages liées é la page courante
$p = array();

//page courante
$id = $this->getPageTag();
$p[$id]['n'] = $id;         // nom de la page
$p[$id]['c'] = 'cur';       // classe css
$p[$id]['w'] = 10;          // largeur de zone par defaut


//Backlinks
if ($pagesFrom = $this->LoadPagesLinkingTo($this->getPageTag()))
{    
    foreach ($pagesFrom as $pageFrom)
    {
        $id = $pageFrom["tag"];
        if($id != $this->getPageTag())// exclusion des liens de la page vers elle méme
        {
            if(!array_key_exists($id, $p)) // si le noeud n'est pas deja enregistré
            {
                $p[$id]['n'] = $id;
                $p[$id]['c'] = 'in';
                $p[$id]['w'] = 10;
                $n++; // incremente le nombre de noeuds
            }
            // le noeud existe deja ou pas : on incremente la taille du rectangle
            $p[$id]['w']++;
            $p[$this->getPageTag()]['w']++;
        }
    }
}
//Liens sortants
$query = "select to_tag as tag from ".$this->config["table_prefix"]."links where from_tag = '".mysqli_real_escape_string($this->dblink, $this->getPageTag())."' order by tag";
if ($pagesTo = $this->LoadAll($query))
{
    foreach ($pagesTo as $pageTo)
    {
        $id = $pageTo["tag"];
        if($id != $this->getPageTag()) //exclusion des liens de la page vers elle méme
        {
            if(!array_key_exists($id, $p)) // si le neud n'est pas deja enregistré
            {
                $p[$id]['n'] = $pageTo["tag"];
                $p[$id]['c'] = 'out';
                $p[$id]['w'] = 10;
                $n++;
            }
            else
            {
                $p[$id]['c'] = 'inout'; // lien entrant et sortant
            }
            $p[$id]['w']++;
            $p[$this->getPageTag()]['w']++;
        }
    }
}



// taille de police
// la taille de police augmente de 1 em é chaque 30 noeuds supplémentaires dans le graphe
$fontsize = ceil($n/30); 

$styles["white"] = " 
    #fond         { fill:#fff; }  
    text          { fill:#808080; font-family:arial; font-size:".$fontsize."em;} 
    rect.cur      { fill:#ffffff; fill-opacity:0.7; stroke:#ff0000; stroke-width:1;} 
    rect.in       { fill:#ffff00; fill-opacity:0.5; stroke:#ffff00; stroke-width:1; }
    rect.out      { fill:#ffcccc; fill-opacity:0.5; stroke:#ff0000; stroke-width:1; } 
    rect.inout    { fill:#ff0000; fill-opacity:0.5; stroke:#ff0000; stroke-width:1; } 
    line          { stroke:#808080; opacity:0.1; stroke-width:1;}  
    line.in       { stroke:#ffff00; opacity:0.5 }
    line.out      { stroke:#ffcccc; opacity:0.5 } 
    line.inout    { stroke:#ff0000; opacity:0.5; stroke-width:3; } 
";

$styles["dark"] = " 
    #fond         { fill:#000; }  
    text          { fill:#ccc; font-family:arial; font-size:".$fontsize."em;} 
    rect.cur      { fill:#ff0000; fill-opacity:1; } 
    rect.in       { fill:#00ff00; fill-opacity:0.5; stroke:#00ff00; stroke-width:1; }
    rect.out      { fill:#0000ff; fill-opacity:0.5; stroke:#0000ff; stroke-width:1; } 
    rect.inout    { fill:#ff00ff; fill-opacity:0.5; stroke:#ff00ff; stroke-width:1; } 
    line          { stroke:#808080; opacity:0.1; stroke-width:1;}  
    line.in       { stroke:#00ff00; opacity:0.5 }
    line.out      { stroke:#0000ff; opacity:0.5 } 
    line.inout    { stroke:#ff00ff; opacity:0.5; stroke-width:3; } 
";


// calcul de la viewbox
if($n < 3) { $n = 3; }
$vw = $n * 50;
$vh = round($n*100/3);

//agrandissement pour limiter les debordements
//largeur de la viewbox
$vxw = round($vw + 1000/$n); 
//hauteur de la viewbox
$vxh = round($vh + 1000/$n);
//$vxw = $vw+10;
//$vxh = $vh+10;

$viewbox = "0 -10 $vxw $vxh";

// attribution de coordonnées aléatoires
while(list($k,$v) = each($p))
{
    $p[$k]['x'] = rand(0, $vw);
    $p[$k]['y'] = rand(0, $vh);
}


if($center) {
    $p[$this->getPageTag()]['x'] = $vxw / 2;
    $p[$this->getPageTag()]['y'] = $vxh / 2;
}


// coordonnées du centre du rectangle de la page courante
//$p[$this->getPageTag()]['w'] = $n;
$cur_w = $p[$this->getPageTag()]['w'];
$cur_x = $p[$this->getPageTag()]['x'];
$cur_y = $p[$this->getPageTag()]['y'];
$x1 = ($cur_w/2) + $cur_x;
$y1 = ($cur_w/2) + $cur_y;


$svg_head = "";     // début du fichier svg
$svg_links = "";    // couche de carrés cliquables
$svg_txt = "";      // nom des pages
$svg_lines = "";    // liaisons avec la page courante
$svg_rs = "";       // reseau de liens secondaires
$svg_foot = "";     // fin de fichier

//ecriture du SVG
$svg_head =  "<?xml version=\"1.0\"  encoding=\"iso-8859-1\" ?> \r\n";
$svg_head .= "<!-- 
    Cartographie des ".$n." pages liées é la page ".$this->getPageTag().". 
    Généré par svg.php - version 0.0.6.
    Le ".date("Y-m-d H:i:s")." 
$affich_param 
--> \n";
    
$svg_head .= "<!DOCTYPE svg PUBLIC \"-//W3C//DTD SVG 1.0//EN\" \n     \"http://www.w3.org/TR/2001/PR-SVG-20010719/DTD/svg10.dtd\">\n";
$svg_head .= "<svg width=\"100%\" height=\"100%\"  viewBox=\"$viewbox\" preserveAspectRatio=\"none\" 
     xml:base=\"http://x-arn.org/y/\"
     xmlns=\"http://www.w3.org/2000/svg\" 
     xmlns:xlink=\"http://www.w3.org/1999/xlink\"> \n";
$svg_head .= "<style type=\"text/css\"> \n";
$svg_head .= "   <![CDATA[ \n";
$svg_head .= $styles[$style];
$svg_head .= "   ]]> \n";
$svg_head .= " </style> \n";

$svg_head .= "<rect id=\"fond\" x=\"0\" y=\"0\" width=\"100%\" height=\"100%\" />\n";

if($script)
{
$svg_head .= " <script type=\"text/ecmascript\"><![CDATA[  
function a(evt,i) {
    //doc = evt.target.ownerDocument;
    doc =  evt.getTarget().getOwnerDocument();
    text_id = 't[' + i + ']';
    text_element = doc.getElementById(text_id);
    x= text_element.getAttribute('x');
    if(x==10) {
        b(evt,i);
    } else {
        text_element.setAttribute('x', 10);
        text_element.setAttribute('style', 'fill:#ccc');
    }
}
function b(evt,i) {
    doc =  evt.getTarget().getOwnerDocument();
    text_id = 't[' + i + ']';
    rect_id = 'r[' + i + ']';
    text_element = doc.getElementById(text_id);
    rect_element = doc.getElementById(rect_id);
    x= rect_element.getAttribute('x');
    text_element.setAttribute('x', x);
    text_element.setAttribute('style', 'fill:#000');
}
function c(evt,i) {
    doc =  evt.getTarget().getOwnerDocument();
    text_id = 't[' + i + ']';
    text_element = doc.getElementById(text_id);
    text_element.setAttribute('style', 'fill:#ff0000');
}
function d(evt,i) {
    doc =  evt.getTarget().getOwnerDocument();
    text_id = 't[' + i + ']';
    text_element = doc.getElementById(text_id);
    x = text_element.getAttribute('x');
    if(x==10) {
        text_element.setAttribute('style', 'fill:#ccc');
    } else {
        text_element.setAttribute('style', 'fill:#000');
    }
}
]]> 
</script>\n";
}


//variables pour les identifiants des éléments
$i = 0;    
$j = 0;  

//valeurs par defaut : pas de script
$mouseEvent1 = "";
$mouseEvent2 = "";
$mouseEvent3 = "";

foreach ($p as $page)
{
    // réseau de liens secondaires
    if($subnet)
    {
        $nompage = $page['n'];
        if($nompage != $this->getPageTag()) // exclusion de la recherche de backlinks sur la page courante
        {
            if ($retroLiens = $this->LoadPagesLinkingTo($nompage)) //backlinks
            {
                foreach ($retroLiens as $retroLien)
                {
                    $id = $retroLien["tag"]; //$p[$id]['n'] est donc une page qui pointe vers p[$nompage]
                    if(array_key_exists($id, $p))
                    {
                    	if($p[$id]['n'] != $this->getPageTag()) // si le noeud n'est pas deja enregistré
                        {
	                        $svg_rs .= "<line id=\"l[$i]\" x1=\"".$p[$id]['x']."\" y1=\"".$p[$id]['y']."\" x2=\"".$page['x']."\" y2=\"".$page['y']."\"/>\n";
	                        $page['w']++; //incrementation de la taille de la page liée
	                        $i++;
	                    }
	                }
                }
            }
        }
    }
    
    if($script)
    {
      $mouseEvent1 = " onmouseover=\"b(evt,$j)\" ";
      $mouseEvent2 = " onmouseover=\"c(evt,$j)\" onmouseout=\"d(evt,$j)\"  ";
      $mouseEvent3 = " onmouseover=\"a(evt,$j)\" ";
    }
    
    //ajuste les coords de ligne au centre des carrés
    $page['cx'] =  ($page['w']/2) + $page['x'];
    $page['cy'] =  ($page['w']/2) + $page['y'];

    //rectangle de page en lien vers le SVG correspondant
    $svg_links .= "<a id=\"ar[$j]\" $mouseEvent1 xlink:href=\"".$this->config["base_url"].$page['n']."$svg_uri\"><rect id=\"r[$j]\" x=\"".$page['x']."\" y=\"".$page['y']."\" width=\"".$page['w']."\" height=\"".$page['w']."\" class=\"".$page['c']."\"/></a>\n";

    //rectangle equivalent sous le premier pour lien vers page dans wiki
    $y2 = $page['y'] + $page['w'];
    $svg_links .= "<a id=\"ar2[$j]\" $mouseEvent2 xlink:href=\"".$this->config["base_url"].$page['n']."\"><rect id=\"r2[$j]\" x=\"".$page['x']."\" y=\"$y2\" width=\"".$page['w']."\" height=\"".$page['w']."\" class=\"".$page['c']."\"/></a>\n";
    
    //texte : nom de la page 
    $script ?  $x = 10 : $x = $page['x'];
    $script ?  $svg_txt .= "" : $svg_txt .= "<a xlink:href=\"".$this->config["base_url"].$page['n']."$svg_uri\">";
    $svg_txt .= "<text id=\"t[$j]\" $mouseEvent3  x=\"$x\"  y=\"".$page['y']."\">".$page['n'];
    $svg_txt .= "</text>";
    $script ?  $svg_txt .= "" : $svg_txt .= "</a>"; 
    $svg_txt .= "\n";
    
    //ligne sur la page courante
    $svg_lines .= "<line id=\"l[$i]\" class=\"".$page['c']."\" x1=\"$x1\" y1=\"$y1\" x2=\"".$page['cx']."\" y2=\"".$page['cy']."\"/>\n";

    $i++;
    $j++;
}

$svg_foot .= "</svg>";

$svg = $svg_head . $svg_rs . $svg_lines . $svg_txt . $svg_links . $svg_foot;
print($svg);
?>
