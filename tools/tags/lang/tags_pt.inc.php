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
* Fichier de traduction en francais de l'extension Hashcash
*
*@package 		tags
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2012 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

'TAGS_ACTION_ADMINTAGS' => 'Ação {{admintags ...}}',
'TAGS_ACTION_ADMINTAGS_ONLY_FOR_ADMINS' => 'a ação é restrita ao grupo de administradores',
'TAGS_NO_WRITE_ACCESS' => 'Você não tem acesso de gravação para esta página!',
'TAGS_FROM_THIS_PAGE' => 'dsta página',
'TAGS_FROM_ALL_PAGES' => 'de todas as páginas',
'TAGS_PRESENT_IN' => 'presente em',
'TAGS_DELETE_MINUSCULE' => 'remover',
'TAGS_CANCEL' => 'Cancelar',
'TAGS_MODIFY' => 'Alterar',
'TAGS_ADD_TAGS' => 'Adicionar palavras-chave',
'TAGS_COMMENTS_ACTIVATED' => 'Os comentários nesta página foram ativados.',
'TAGS_ACTIVATE_COMMENTS' => 'Ativar comentários',
'TAGS_ACTIVATE_COMMENTS_ON_THIS_PAGE' => 'Ativar comentários nesta página',
'TAGS_DESACTIVATE_COMMENTS' => 'Desativar os comentários',
'TAGS_DESACTIVATE_COMMENTS_ON_THIS_PAGE' => 'Desativar comentários nesta página',
'TAGS_COMMENTS_ON_THIS_PAGE' => 'Comentários sobre esta página.',
'TAGS_COMMENTS_DESACTIVATED' => 'Comentários desativados.',
'TAGS_SEE_ALL_PAGES_WITH_THIS_TAGS' => 'Ver todas as páginas que contenham essa palavra-chave',
'TAGS_ALERT_PAGE_ALREADY_MODIFIED' => 'ATENÇÃO: Esta página foi modificada por outra pessoa enquanto você a editava<br /> Por favor, copie suas alterações e re-editar esta página.',
'TAGS_ANSWER_THIS_COMMENT' => 'Responder a este comentário',
'TAGS_DATE_FORMAT' => "\l\e d.m.Y &\a\g\\r\av\e; H:i:s",
'TAGS_WRITE_YOUR_COMMENT_HERE' => 'Escreva o seu comentário aqui...',
'TAGS_ADD_YOUR_COMMENT' => 'Adicione seu comentário',
'TAGS_ACTION_FILTERTAGS' => 'Ação {{filtertags ...}}',
'TAGS_NO_FILTERS' => 'O parâmetro "filter1" não foi encontrado, enquanto é obrigatório.',
'TAGS_ONLY_ONE_DOUBLEPOINT' => 'Os dois pontos (:) não pode estar presente mais de uma vez para indicar a étiqueta. Vários encontrados.',

'TAGS_ACTION_INCLUDEPAGES' => 'Ação {{includepages ...}}',
'TAGS_NO_PARAM_PAGES' => 'O parâmetro "pages" não foi encontrado, enquanto é obrigatório.',

'TAGS_NO_RESULTS' => 'Nenhum resultado com essas palavras-chave.',
'TAGS_RESULTS' => 'resultados',
'TAGS_FILTER' => 'Filtrar',
'TAGS_CONTAINING_TAG' => 'com a palavra-chave.',
'TAGS_ONE_PAGE' => 'Uma página',
'TAGS_PAGES' => 'páginas',

// actions/rss.php
'TAGS_RSS_FEED_FOR_NEW_PAGES_WITH_TAGS' => 'Fluxo RSS das novas páginas com os tags',

// handler exportpages
'TAGS_YOUR_EBOOK' => 'Seu Ebook',
'TAGS_SPAM_RISK' => 'Você deve habilitar o JavaScript para não ser considerado spam.',
'TAGS_GENERATE_EBOOK' => 'Fazer o Ebook',
'TAGS_EXPORT_PAGES_INFO' => 'Selecione suas páginas clicando no Ebook ',
'TAGS_ORDER_PAGES_INFO' => 'Mova as páginas e colocá-las na ordem que quiser.',
'TAGS_EBOOK_TITLE' => 'Título do livro',
'TAGS_EBOOK_DESC' => 'Descrição',
'TAGS_EBOOK_AUTHOR' => 'Primeiro nome e nome do autor',
'TAGS_EXAMPLE_AUTHOR' => 'Ex: Victor Hugo',
'TAGS_EBOOK_BIBLIO_AUTHOR' => 'Nome do autor, vírgula nome',
'TAGS_EXAMPLE_BIBLIO_AUTHOR' => 'Ex: Hugo, Victor',
'TAGS_EBOOK_COVER_IMAGE' => 'Link para a imagem da capa do livro',
'TAGS_NO_TITLE_FOUND' => 'ERRO: O título não foi definido.',
'TAGS_NO_DESC_FOUND' => 'ERRO: A descrição não foi definida.',
'TAGS_NO_AUTHOR_FOUND' => 'ERRO: O autor não foi definido.',
'TAGS_NO_BIBLIO_AUTHOR_FOUND' => 'ERRO: O autor (versão biblioteca) não foi definido.',
'TAGS_NO_IMAGE_FOUND' => 'ERRO : O link para a imagem da capa do livro não foi definido.',
'TAGS_NOT_IMAGE_FILE' => 'ERRO : O link para a imagem da capa não é uma imagem com a extensão jpg.',
'TAGS_EBOOK_PAGE_CREATED' => 'A página do Ebook foi criada com sucesso',
'TAGS_GOTO_EBOOK_PAGE' => 'Ir para a página : ',
'TAGS_FILTER_PAGES' => 'Filtrar as páginas',
'TAGS_SEE_PAGE' => 'Ver a página',
'TAGS_SELECT_PAGE' => 'Escolher a página',
'TAGS_DELETE_PAGE' => 'Retirar a página',
'TAGS_DELETE' => 'Remover',
'TAGS_FOR_THE_EBOOK' => 'para Ebook',
'TAGS_FROM_THE_EBOOK' => 'do Ebook',
'TAGS_AVAILABLE_PAGES' => 'Páginas disponíveis',
'TAGS_START_PAGE' => 'Página de introdução',
'TAGS_END_PAGE' => 'Página final',
'TAGS_PUBLISHED_UNDER_CREATIVE_COMMONS_BY_SA' => 'Este trabalho é publicado sob licença Creative Commons BY SA.',
'TAGS_BY' => 'Por',
'TAGS_ABOUT_THIS_EBOOK' => 'Informação sobre este trabalho',
'TAGS_DOWNLOAD_EPUB' => '.epub',
'TAGS_DOWNLOAD' => 'Baixar',
'TAGS_CONTENT_VISIBLE_ONLINE_FROM_PAGE' => 'Conteúdo on-line na página',
'TAGS_NO_EBOOK_METADATAS' => 'Esta página não tem os metadados necessários para criar o ebook.',
'TAGS_NO_EBOOK_FOUND' => 'Nenhum ebook encontrado.'

));