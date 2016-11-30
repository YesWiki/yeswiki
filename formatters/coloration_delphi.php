<?php
/*
* $Id: coloration_delphi.php 797 2007-07-23 22:31:56Z lordfarquaad $
*
* Souligneur syntaxique Delphi
*
* copyright Eric Feldstein 2003 2004 mailto:garfield_fr@tiscali.fr
* copyright Didier Loiseau 2004
*
* Licence : la meme que wikini (voir le fichier LICENCE).
* Vous étes libre d'utiliser et de modifier ce code é condition de laisser le copyright
* d'origine. Vous pouvez  bien sur vous ajouter é la liste des auteurs.
*
* Installation : copier le fichier dans le repertoire "formatters" de WikiNi
*/
// protection contre les appels directs
if (!defined('WIKINI_VERSION')) {
    die('Appel direct interdit');
}

include_once('formatters/highlighter.class.php');

$DH = new Highlighter();
$DH->isCaseSensitiv = false;

//************* commentaires *************
$DH->comment = array('({[^$][^}]*})',			//commentaires: { ... }
					'(\(\*[^$](.*)\*\))'		      //commentaires: (* ... *)
                );
$DH->commentLine = array('(//.*(\n|$))'); 			//commentaire //
$DH->commentStyle = "color: red; font-style: italic";	//style CSS pour balise SPAN

//************* directives de compilation *************
$DH->directive = array('({\\$[^{}]*})',			//directive {$....}
					'(\(\*\\$(.*)\*\))'			      //directive (*$....*)
				);
$DH->directiveStyle = "color: green";			   //style CSS pour balise SPAN

//************* chaines de caracteres *************
/**
* @todo correction pour l'échappement des guillemets simples
*/
$DH->string = array("('[^']*')",'(#\d+)');		//chaine = 'xxxxxxxx' ou #23
$DH->stringStyle = "background: yellow";

//************* nombres *************
$DH->number[] = '(\b\d+(\.\d*)?([eE][+-]?\d+)?)';	//123 ou 123. ou 123.456 ou 123.E-34 ou 123.e-34 123.45E+34 ou 4e54
$DH->number[] = '(\$[0-9A-Fa-f]+\b)';				//ajout des nombres hexadecimaux : $AF
$DH->numberStyle = 'color: blue';

//************* mots clé *************
$DH->keywords['MotCle']['words'] = array('absolute','abstract','and','array','as','asm',
						'begin',
						'case','class','const','constructor',
						'default','destructor','dispinterface','div','do','downto',
						'else','end','except','exports','external',
						'file','finalization','finally','for','function',
						'goto',
						'if','implementation','inherited','initialization','inline','interface','is',
						'label','library','loop','message',
						'mod',
						'nil','not',
						'object','of','or','out','overload','override',
						'packed','private','procedure','program','property','protected','public','published',
						'raise','read','record','repeat','resourcestring',
						'set','shl','shr','stdcall','string',
						'then','threadvar','to','try','type','unit','until',
						'use','uses',
						'var','virtual','while',
						'with','write',
						'xor'
					);
$DH->keywords['MotCle']['style'] = 'font-weight: bold';   //style CSS pour balise SPAN

//************* liste des symboles *************
$DH->symboles = array('#','$','&','(','(.',')','*','+',',','-','.','.)','..',
					'/',':',':=',';','<','<=','<>','=','>','>=','@','[',']','^');
$DH->symbolesStyle = '';

//************* identifiants *************
$DH->identifier = array('[_A-Za-z]?[_A-Za-z0-9]+');
$DH->identStyle = '';

echo "<pre>".$DH->Analyse($text)."</pre>";
unset($DH);
?>
