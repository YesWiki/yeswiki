<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                        |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2012 Outils-Réseaux (accueil@outils-reseaux.org)                                       |
// +------------------------------------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or                                        |
// | modify it under the terms of the GNU Lesser General Public                                           |
// | License as published by the Free Software Foundation; either                                         |
// | version 2.1 of the License, or (at your option) any later version.                                   |
// |                                                                                                      |
// | This library is distributed in the hope that it will be useful,                                      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
// | Lesser General Public License for more details.                                                      |
// |                                                                                                      |
// | You should have received a copy of the GNU Lesser General Public                                     |
// | License along with this library; if not, write to the Free Software                                  |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
// 
/**
* Arquivo de tradução em português (do Brasil) da extensão templates
*
*@package 		templates
*@author        François Labastie <francois@outils-reseaux.org>
*@copyright     2014 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

// actions/button.php
'TEMPLATE_ACTION_BUTTON' => 'Ação {{button ...}}',
'TEMPLATE_LINK_PARAMETER_REQUIRED' => 'parâmetro "link" obrigatório',

'TEMPLATE_RSS_LAST_CHANGES' => 'RSS Feed para páginas recentemente modificados',
'TEMPLATE_RSS_LAST_COMMENTS' => 'RSS Feed dos ùltimos comentários',

'TEMPLATE_DEFAULT_THEME_USED' => 'O template padrão esta utilisado',
'TEMPLATE_NO_THEME_FILES' => 'Alguns (ou todos) arquivos do template desapareceram',
'TEMPLATE_NO_DEFAULT_THEME' => 'Os arquivos do template desapareceram. O uso do template fica impossível. <br /> Por favor, reinstale o tools template ou contacte o administrador do site',
'TEMPLATE_CUSTOM_GRAPHICS' => 'Aspecto da página',
'TEMPLATE_SAVE' => 'Salvar',
'TEMPLATE_APPLY' => 'Aplicar',
'TEMPLATE_CANCEL' => 'Cancelar',
'TEMPLATE_THEME' => 'Tema',
'TEMPLATE_SQUELETTE' => 'Esqueleto',
'TEMPLATE_STYLE' => 'Estilo',
'TEMPLATE_BG_IMAGE' => 'Imagem de fundo',
'TEMPLATE_ERROR_NO_DATA' => 'ERRO: nada a adicionar nos meta-dados.',
'TEMPLATE_ERROR_NO_ACCESS' => 'ERRO: sem direitos de acesso',

// barre de redaction
'TEMPLATE_VIEW_PAGE' => 'Ver a página',
'TEMPLATE_EDIT' => 'Editar',
'TEMPLATE_EDIT_THIS_PAGE' => 'Editar a página',
'TEMPLATE_CLICK_TO_SEE_REVISIONS' => 'As últimas alterações na página',
'TEMPLATE_LAST_UPDATE' => 'Modificado em',
'TEMPLATE_OWNER' => 'Proprietário',
'TEMPLATE_YOU' => 'Você',
'TEMPLATE_NO_OWNER' => 'Nenhum proprietário',
'TEMPLATE_CLAIM' => 'Apropriação',
'TEMPLATE_CLICK_TO_CHANGE_PERMISSIONS' => 'Editar permissões ad página',
'TEMPLATE_PERMISSIONS' => 'Permissões',
'TEMPLATE_DELETE' => 'Remover',
'TEMPLATE_DELETE_PAGE' => 'Excluir página',
'TEMPLATE_CLICK_TO_SEE_REFERENCES' => 'URLs referentes da página',
'TEMPLATE_REFERENCES' => 'Referências',
'TEMPLATE_SLIDESHOW_MODE' => 'Ver esta página em modo de apresentação de slides.',
'TEMPLATE_SLIDESHOW' => 'Slideshow',
'TEMPLATE_SEE_SHARING_OPTIONS' => 'Compartilhar página',
'TEMPLATE_SHARE' => 'Compartilhar',

// formatage des dates
'TEMPLATE_DATE_FORMAT' => 'd.m.Y \&\a\g\r\a\v\e; H:i:s',

// recherche
'TEMPLATE_SEARCH_INPUT_TITLE' => 'Pesquisar no YesWiki [alt-shift-C]',
'TEMPLATE_SEARCH_BUTTON_TITLE' => 'Encontrar páginas com esse texto..',
'TEMPLATE_SEARCH_PLACEHOLDER' => 'Procurar...',

// handler widget
'TEMPLATE_WIDGET_TITLE' => 'Widget : integrar o conteúdo desta página em outro lugar',
'TEMPLATE_WIDGET_COPY_PASTE' => 'Copiar e pastar o código HTML acima para incorporar o conteúdo de tal forma que apareça abaixo.',

// handler share
'TEMPLATE_SHARE_INCLUDE_CODE' => 'Código de integração de conteúdo numa página HTML',
'TEMPLATE_SHARE_MUST_READ' => 'Leia-se: : ',
'TEMPLATE_SHARE_FACEBOOK' => 'Share on Facebook',
'TEMPLATE_SHARE_TWITTER' => 'Compartilhar no Twitter',
'TEMPLATE_SHARE_NETVIBES' => 'Compartilhar no Netvibes',
'TEMPLATE_SHARE_DELICIOUS' => 'Compartilhar no Delicious',
'TEMPLATE_SHARE_GOOGLEREADER' => 'Compartilhar no Google Reader',
'TEMPLATE_SHARE_MAIL' => 'Enviar o conteúdo desta página por e-mail',
'TEMPLATE_ADD_SHARE_BUTTON' => 'Adicionar um botão de compartilhamento no topo direito da página',
'TEMPLATE_ADD_EDIT_BAR' => 'Adicionar o menu de edição no rodapé',

// handler diaporama
'TEMPLATE_NO_ACCESS_TO_PAGE' => 'Você não têm o direito de acesso nesta página.',
'TEMPLATE_PAGE_DOESNT_EXIST' => 'Página inexistente',
'PAGE_CANNOT_BE_SLIDESHOW' => 'A página não pode ser cortada em lâminas (sem título nível 2)',

// handler edit
'TEMPLATE_CUSTOM_PAGE' => 'Preferências da página',
'TEMPLATE_PAGE_PREFERENCES' => 'Definições da página',
'PAGE_LANGUAGE' => 'Idioma da página',
'CHOOSE_PAGE_FOR' => 'Escolha uma página para',
'HORIZONTAL_MENU_PAGE' => 'No menu horizontal',
'FAST_ACCESS_RIGHT_PAGE' => 'os atalhos no topo direito',
'HEADER_PAGE' => 'o cabeçalho (banner)',
'FOOTER_PAGE' => 'o rodapé',
'FOR_2_OR_3_COLUMN_THEMES' => 'Para temas 2 ou 3 colunas',
'VERTICAL_MENU_PAGE' => 'o menu vertical',
'RIGHT_COLUMN_PAGE' => 'coluna da direita',

// actions/yeswikiversion.php
'RUNNING_WITH' => 'Galopa no',

'TEMPLATE_NO_THEME_FILES' => 'Faltam os arquivos de tema',
'TEMPLATE_DEFAULT_THEME_USED' => 'O tema padrão será usado',

// actions/end.php
'TEMPLATE_ACTION_END' => 'Ação {{end ...}}',
'TEMPLATE_ELEM_PARAMETER_REQUIRED' => 'parâmetro "elem" obrigatório',

// actions/col.php
'TEMPLATE_ACTION_COL' => 'Ação {{col ...}}',
'TEMPLATE_SIZE_PARAMETER_REQUIRED' => 'parâmetro "size" obrigatório',
'TEMPLATE_SIZE_PARAMETER_MUST_BE_INTEGER_FROM_1_TO_12' => 'o parâmetro "size" deve ser um número inteiro entre 1 e 12',
'TEMPLATE_ELEM_COL_NOT_CLOSED' => 'ação {{col ...}} deve ser fechada por ação {{elem end = "col"}}',

// actions/grid.php
'TEMPLATE_ACTION_GRID' => 'Ação {{grid ...}}',
'TEMPLATE_ELEM_GRID_NOT_CLOSED' => 'ação {{grid ...}} deve ser fechada por ação {{end elem="grid"}}',

// actions/buttondropdown.php
'TEMPLATE_ACTION_BUTTONDROPDOWN' => 'Ação {{buttondropdown ...}}',
'TEMPLATE_ELEM_BUTTONDROPDOWN_NOT_CLOSED' => 'ação {{buttondropdown ...}} deve ser fechada por ação {{end elem="buttondropdown"}}',

));