<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                        |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2014 Outils-Réseaux (accueil@outils-reseaux.org)                                       |
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
* Arquivo de tradução em português (do Brasil) da Yeswiki
*
*@package 		yeswiki
*@author        François Labastie <francois@outils-reseaux.org>
*@author        Jérémy Dufraisse <jeremy.dufraisse-info@orange.fr>
*@copyright     2014 Outils-Réseaux
*/

return [

    // Commons
    'BY' => 'por',
    // 'CLEAN' => 'Nettoyer',
    // 'DELETE' => 'Supprimer',
    // 'DELETE_ALL_SELECTED_ITEMS' => 'Supprimer tous les éléments sélectionnés',
    // 'DELETE_SELECTION' => 'Supprimer la sélection',
    // 'DEL' => 'Suppr.', // fives chars max.
    // 'EMAIL' => 'Email',
    // 'INVERT' => 'Inverser',
    // 'MODIFY' => 'Modifier',
    // 'NAME' => 'Nom',
    // 'SUBSCRIPTION' => 'Inscription',
    'TRIPLES' => 'Triplos',
    'UNKNOWN' => 'Desconhecido',
    // 'WARNING' => 'Attention',

    // wakka.php
    'INVALID_ACTION' => 'A&ccedil;&atilde;o inv&aacute;lida',
    'ERROR_NO_ACCESS' => 'Erro: você não tem acesso a essa ação',
    // 'NOT_FOUND' => 'N\'existe pas',
    'NO_REQUEST_FOUND' => '$ _REQUEST[] não foi encontrado. Wakka requer PHP 4.1.0 ou mais reciente!',
    'SITE_BEING_UPDATED' => 'Este site está sendo atualizado. Por favor, tente novamente mais tarde.',
    'DB_CONNECT_FAIL' => 'Por razões além do nosso controle, o conteúdo deste YesWiki está temporariamente indisponível. Por favor, tente novamente mais tarde. Obrigado pela sua compreensão.',
    'LOG_DB_CONNECT_FAIL' => 'YesWiki: a conexao com a base de dados falhou', // sans accents car commande systeme
    'INCORRECT_PAGENAME' => 'O nome da página é incorreto.',
    // 'PERFORMABLE_ERROR' => 'Une erreur inattendue s\'est produite. Veuillez contacter l\'administrateur du site et lui communiquer l\'erreur suivante :',
    'HOMEPAGE_WIKINAME' => 'PaginaInicial',
    'MY_YESWIKI_SITE' => 'Meu site YesWiki',
    // 'FILE_WRITE_PROTECTED' => 'le fichier de configuration est protégé en écriture',

    // ACLs
    // 'DENY_READ' => 'Vous n\'êtes pas autorisé à lire cette page',
    // 'DENY_WRITE' => 'Vous n\'êtes pas autorisé à écrire sur cette page',
    // 'DENY_COMMENT' => 'Vous n\'êtes pas autorisé à commenter cette page',
    // 'DENY_DELETE' => 'Vous n\'êtes pas autorisé à supprimer cette page',

    // tools.php
    'YESWIKI_TOOLS_CONFIG' => 'Configuração extensão(s) YesWiki',
    'DISCONNECT' => 'Sair',
    'RETURN_TO_EXTENSION_LIST' => 'Voltar para a lista de extensões ativas',
    'NO_TOOL_AVAILABLE' => 'Nenhuma ferramenta está disponível ou ativa',
    'LIST_OF_ACTIVE_TOOLS' => 'Lista de extensões ativas',

    // actions/backlinks.php
    'PAGES_WITH_LINK' => 'Páginas que apontam para',
    'PAGES_WITH_LINK_TO_CURRENT_PAGE' => 'Páginas que apontam para a página atual',
    'NO_PAGES_WITH_LINK_TO' => 'Não existem ligações para',

    // actions/changestyle.php
    // 'STYLE_SHEET' => 'Feuille de style',
    // 'CHANGESTYLE_ERROR' => 'Le nom \'{name}\' n\'est pas conforme à la règle de nommage imposée par l\'action ChangeStyle.'.
        // 'Reportez-vous à la documentation de cette action pour plus de précisions',

    // handlers/page/acls.php
    // 'YW_ACLS_LIST' => 'Liste des droits d\'acc&egrave;s de la page',
    // 'YW_ACLS_UPDATED' => 'Droits d\'acc&egrave;s mis &agrave; jour',
    // 'YW_NEW_OWNER' => ' et changement du propri&eacute;taire. Nouveau propri&eacute;taire : ',
    // 'YW_CANCEL' => 'Annuler',
    // 'YW_ACLS_READ' => 'Droits de lecture',
    // 'YW_ACLS_WRITE' => 'Droits d\'écriture',
    // 'YW_ACLS_COMMENT' => 'Droits pour commenter',
    // 'YW_CHANGE_OWNER' => 'Changer le propri&eacute;taire',
    // 'YW_CHANGE_NOTHING' => 'Ne rien modifier',
    // 'YW_CANNOT_CHANGE_ACLS' => 'Vous ne pouvez pas g&eacute;rer les permissions de cette page',

    // actions/editactionsacls.class.php
    'ACTION_RIGHTS' => 'Direitos da acção',
    'SEE' => 'Ver',
    'ERROR_WHILE_SAVING_ACL' => 'Ocorreu um erro ao salvar a ACL para a ação',
    'ERROR_CODE' => 'Código de erro',
    'NEW_ACL_FOR_ACTION' => 'Nova ACL para a ação',
    'NEW_ACL_SUCCESSFULLY_SAVED_FOR_ACTION' => 'Nova ACL registrado com êxito para a ação',
    'EDIT_RIGHTS_FOR_ACTION' => 'Editar direitos da ação',
    'SAVE' => 'Salvar',

    // actions/editgroups.class.php
    'DEFINITION_OF_THE_GROUP' => 'Definição do grupo',
    'DEFINE' => 'definir',
    'CREATE_NEW_GROUP' => 'Ou criar um novo grupo',
    'ONLY_ADMINS_CAN_CHANGE_MEMBERS' => 'Você não pode mudar os membros do grupo de administradores, porque você não for um administrador',
    'YOU_CANNOT_REMOVE_YOURSELF' => 'Você não pode se retirar do grupo de administradores',
    'ERROR_RECURSIVE_GROUP' => 'Erro: você não pode definir um grupo de forma recursiva',
    'ERROR_WHILE_SAVING_GROUP' => 'Ocorreu um erro ao salvar o grupo',
    'NEW_ACL_FOR_GROUP' => 'Nova ACL para o grupo',
    'NEW_ACL_SUCCESSFULLY_SAVED_FOR_THE_GROUP' => 'Nova ACL registrada com êxito para o grupo',
    'EDIT_GROUP' => 'Editar o grupo',
    // 'EDIT_EXISTING_GROUP' => 'Éditer un groupe existant',
    // 'DELETE_EXISTING_GROUP' => 'Supprimer un groupe existant',
    // 'GROUP_NAME' => 'Nom du groupe',
    // 'SEE_EDIT' => 'Voir / Éditer',
    'ONLY_ALPHANUM_FOR_GROUP_NAME' => 'Os nomes dos grupos só pode conter caracteres alfanuméricos',
    // 'LIST_GROUP_MEMBERS' => 'Liste des membres du groupe {groupName}',
    // 'ONE_NAME_BY_LINE' => 'un nom d\'utilisateur par ligne',

    // actions/edithandlersacls.class.php
    'HANDLER_RIGHTS' => 'Direitos do handler',
    'ERROR_WHILE_SAVING_HANDLER_ACL' => 'Ocorreu um erro durante a gravaçao do ACL para o handler',
    // 'NEW_ACL_FOR_HANDLER' => 'Nouvelle ACL pour le handler',
    // 'NEW_ACL_SUCCESSFULLY_SAVED_FOR_HANDLER' => 'Nouvelle ACL enregistr&eacute;e avec succ&egrave;s pour le handler',
    'EDIT_RIGHTS_FOR_HANDLER' => 'Editar direitos do handler',

    // actions/erasespamedcomments.class.php
    // 'ERASED_COMMENTS' => 'Commentaire(s) effacé(s)',
    // 'FORM_RETURN' => 'Retour au formulaire',
    // 'NO_RECENT_COMMENTS' => 'Pas de commentaires récents',
    // 'NO_SELECTED_COMMENTS_TO_ERASE' => 'Aucun commentaire n\'a été sélectionné pour étre effacé',

    // actions/footer.php ignoree, car le tools templates court circuite
    // actions/header.php ignoree, car le tools templates court circuite

    // actions/include.php
    'ERROR' => 'Erro',
    'ACTION' => 'Ação',
    'MISSING_PAGE_PARAMETER' => 'O parâmetro "page" está faltando',
    'IMPOSSIBLE_FOR_THIS_PAGE' => 'Impossível para a página',
    'TO_INCLUDE_ITSELF' => 'se incluir em si mesma',
    'INCLUSIONS_CHAIN' => 'Cadeia de Inclusão',
    'EDITION' => 'edição',
    'READING_OF_INCLUDED_PAGE' => 'Lendo a página incluída',
    'NOT_ALLOWED' => 'não autorizado',
    'INCLUDED_PAGE' => 'A página incluída',
    'DOESNT_EXIST' => 'não aparece',

    // actions/listpages.php
    'THE_PAGE' => 'A página',
    'BELONGING_TO' => ' pertencente ao',
    'LAST_CHANGE_BY' => 'Modificado pela última vez por',
    'LAST_CHANGE' => 'última modificação',
    'PAGE_LIST_WHERE' => 'Lista de páginas a que',
    'HAS_PARTICIPATED' => 'participou',
    'EXCLUDING_EXCLUSIONS' => 'fora exclusões',
    'INCLUDING' => 'e que',
    'IS_THE_OWNER' => 'é o proprietário',
    'NO_PAGE_FOUND' => 'Nenhuma página encontrada',
    'IN_THIS_WIKI' => 'neste wiki',
    'LIST_PAGES_BELONGING_TO' => 'Lista de páginas que pertencem aos',
    'THIS_USER_HAS_NO_PAGE' => 'Este usuário não possui nenhuma página',

    // actions/mychanges.php
    'YOUR_MODIFIED_PAGES_ORDERED_BY_MODIFICATION_DATE' => 'Lista de páginas que você editou, ordenadas por data de modificação',
    'YOUR_MODIFIED_PAGES_ORDERED_BY_NAME' => 'Lista de páginas que você editou, em ordem alfabética',
    'YOU_DIDNT_MODIFY_ANY_PAGE' => 'Você não mudou nenhuma página',
    'YOU_ARENT_LOGGED_IN' => 'Você ainda não se identificou',
    'IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES' => 'impossível ver a lista de páginas que você editou',
    'LIST_OF_PAGES_WHERE_YOU_ARE_THE_OWNER' => 'Lista de páginas que você possui',
    'YOU_DONT_OWN_ANY_PAGE' => 'Você é o proprietário de nenhuma página',

    // actions/nextextsearch.php
    // 'NEWTEXTSEARCH_HINT' => 'Un caractère inconnu peut être remplacé par « ? » plusieurs par « * »',
    // 'NO_SEARCH_RESULT' => 'Désolé mais il n\'y a aucun de résultat pour votre recherche',
    // 'SEARCH_RESULTS' => 'Résultats de la recherche',

    // actions/orphanedpages.php
    'NO_ORPHAN_PAGES' => 'Não há páginas órfãs',

    // actions/recentchanges.php
    'HISTORY' => 'histórico',

    // actions/recentchangesrss.php
    'TO_OBTAIN_RSS_FEED_TO_GO_THIS_ADDRESS' => 'Para obter o feed RSS das últimas mudanças, use o seguinte endereço',
    'LATEST_CHANGES_ON' => 'Mudanças recentes',

    // actions/recentcomments.php
    'NO_RECENT_COMMENTS' => 'Nenhum comentário recente',

    // actions/recentcommentsrss.php
    'TO_OBTAIN_COMMENTS_RSS_FEED_TO_GO_THIS_ADDRESS' => 'Para obter o feed RSS dos últimos comentários, utilize o seguinte endereço',
    'LATEST_COMMENTS_ON' => 'Ultimos comentários sobre',

    // actions/recentlycommented.php
    // 'LAST COMMENT' => 'dernier commentaire',
    'NO_RECENT_COMMENTS_ON_PAGES' => 'Não na nenhuma página recentemente comentada',

    // actions/redirect.php
    'ERROR_ACTION_REDIRECT' => 'Erro ação {{redirect ...}}',
    'CIRCULAR_REDIRECTION_FROM_PAGE' => 'Redirecionamento circular a partir da página',
    'CLICK_HERE_TO_EDIT' => 'Clique aqui para editar',
    'PRESENCE_OF_REDIRECTION_TO' => 'Presença de um redirecionamento hacia',

    // actions/resetpassword.php
    'ACTION_RESETPASSWORD' => 'Ação {{resetpassword ...}}',
    'PASSWORD_UPDATED' => 'Senha alterada',
    'RESETTING_THE_PASSWORD' => 'Modificação de senha',
    'WIKINAME' => 'NomeWiki',
    'NEW_PASSWORD' => 'Nova senha',
    'RESET_PASSWORD' => 'Redefinição de senha',
    'NO_PERMISSIONS_TO_EXECUTE_THIS_ACTION' => 'Você não tem as permissões necessárias para executar esta ação',

    // actions/textsearch.php
    'WHAT_YOU_SEARCH' => 'O que você deseja pesquisar',
    'SEARCH' => 'Pesquisar',
    'SEARCH_RESULT_OF' => 'Resultado(s) da pesquisa de',
    'NO_RESULT_FOR' => 'Nenhum resultado para',

    // actions/testtriples.php
    // 'END_OF_EXEC' => 'Fin de l\'exécKution',

    // actions/trail.php
    'ERROR_ACTION_TRAIL' => 'Erro Ação {{trail ...}}',
    'INDICATE_THE_PARAMETER_TOC' => 'Especifique o nome da página de resumo, parâmetro "toc"',

    // actions/usersettings.php
    // 'USER_SETTINGS' => 'Paramètres utilisateur',
    // 'USER_SIGN_UP' => 'S\'inscrire',
    'YOU_ARE_NOW_DISCONNECTED' => 'Agora você está fora de linha',
    'PARAMETERS_SAVED' => 'Configurações salvas',
    'NO_SPACES_IN_PASSWORD' => 'Não são permitidos espaços em senhas',
    'PASSWORD_TOO_SHORT' => 'Senha curta demais',
    'WRONG_PASSWORD' => 'Senha errada',
    'PASSWORD_CHANGED' => 'Senha alterada',
    'GREETINGS' => 'Olá',
    'YOUR_EMAIL_ADDRESS' => 'O seu endereço de e-mail',
    'DOUBLE_CLICK_TO_EDIT' => 'Edição, clicando duas vezes',
    'SHOW_COMMENTS_BY_DEFAULT' => 'Por padrão, mostrar comentários',
    'MAX_NUMBER_OF_LASTEST_COMMENTS' => 'Número máximo de últimos Comentários',
    'MAX_NUMBER_OF_VERSIONS' => 'Número máximo de versões',
    'YOUR_MOTTO' => 'Manchete',
    'CHANGE_THE_PASSWORD' => 'Alterar senha',
    'YOUR_OLD_PASSWORD' => 'Sua senha antiga',
    'NEW_PASSWORD' => 'Nova senha',
    'CHANGE' => 'Mudança',
    'USERNAME_MUST_BE_WIKINAME' => 'Seu nome de usuário deve ser formatado em NomeWiki',
    'YOU_MUST_SPECIFY_AN_EMAIL' => 'Você deve especificar um endereço de e-mail',
    'THIS_IS_NOT_A_VALID_EMAIL' => 'Isto não se parece com um endereço de e-mail',
    'PASSWORDS_NOT_IDENTICAL' => 'As senhas não eram idênticas',
    'PASSWORD_SHOULD_HAVE_5_CHARS_MINIMUM' => 'deve conter pelo menos 5 carácteres alfanuméricos',
    'YOU_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED' => 'Você deve aceitar cookies para entrar',
    'IF_YOU_ARE_REGISTERED_LOGGIN_HERE' => 'Se você já é cadastrado, faça o login aqui',
    'PASSWORD_5_CHARS_MINIMUM' => 'Senha (pelo menos 5 carácteres)',
    'REMEMBER_ME' => 'Lembre-se de mim',
    'IDENTIFICATION' => 'Identificação',
    'FILL_THE_NEXT_FIELDS_IF_YOU_LOGGIN_FOR_THE_FIRST_TIME_AND_REGISTER' => 'Os seguintes campos devem ser preenchidos se você entrar pela primeira vez (para que você crie uma conta)',
    'PASSWORD_CONFIRMATION' => 'Confirme a senha',
    'NEW_ACCOUNT' => 'Nova conta',
    // 'LOGGED_USERS_ONLY_ACTION' => 'Il faut être connecté pour pouvoir exécuter cette action',
    'USER_DELETE' => 'Eliminar utilizador',

    // actions/wantedpages.php
    'NO_PAGE_TO_CREATE' => 'Nenhuma página para criar',

    // includes/controllers/CsrfController.php
    'NO_CSRF_TOKEN_ERROR' => 'Erro de conceção do local: O formulário de submissão não continha o símbolo de identificação '.
    'único necessário para os mecanismos de segurança interna.',
    'CSRF_TOKEN_FAIL_ERROR' => 'Esta página pode ter sido aberta uma segunda vez. '.
        'Por favor, renove o pedido desta janela (o sinal de segurança interna não foi bom).',

    // javascripts/favorites.js
    'FAVORITES_ADD' => 'Adicionar a aavoritos',
    'FAVORITES_REMOVE' => 'Remova dos favoritos',

    // templates/actions/my-favorites.twig
    'FAVORITES_DELETE_ALL' => 'Apague todos os meus favoritos',
    'FAVORITES_MY_FAVORITES' => 'Os meus favoritos',
    'FAVORITES_NO_FAVORITE' => 'Nenhum favorito foi salvo',
    'FAVORITES_NOT_ACTIVATED' => 'O uso de favoritos não está ativado neste site.',
    'FAVORITES_NOT_CONNECTED' => 'O uso de favoritos só é possível para pessoas ligadas.',

    // templates/actions/my-favorites-table.twig
    'FAVORITES_TITLE' => 'Título',
    'FAVORITES_LINK' => 'Ligação',

    // setup/header.php
    'OK' => 'OK',
    'FAIL' => 'FALHA',
    'END_OF_INSTALLATION_BECAUSE_OF_ERRORS' => 'Fim da instalação devido a erros de configuração',

    // setup/default.php
    'INSTALLATION_OF_YESWIKI' => 'Instalando YesWiki',
    'YOUR_SYSTEM' => 'Seu sistema',
    'EXISTENT_SYSTEM_RECOGNISED_AS_VERSION' => 'existente foi reconhecido como a versão',
    'YOU_ARE_UPDATING_YESWIKI_TO_VERSION' => 'Você está prestes a fazer atualizações YesWiki para a versão',
    'CHECK_YOUR_CONFIG_INFORMATION_BELOW' => 'Por favor, revise as informações de configuração abaixo',
    'FILL_THE_FORM_BELOW' => 'Por favor preencha o seguinte formulário',
    'DEFAULT_LANGUAGE' => 'Idioma padrão',
    // 'NAVIGATOR_LANGUAGE' => 'Langue du navigateur',
    'DEFAULT_LANGUAGE_INFOS' => 'Idioma padrão para o interface YesWiki. E sempre possível alterar o idioma para cada página criada',
    // 'GENERAL_CONFIGURATION' => 'Configuration générale',
    'DATABASE_CONFIGURATION' => 'Configuração do banco de dados',
    'MORE_INFOS' => '+ Saiba mais',
    'MYSQL_SERVER' => 'Máquina MySQL',
    'MYSQL_SERVER_INFOS' => 'O endereço IP ou nome de rede da máquina em que o servidor MySQL',
    'MYSQL_DATABASE' => 'Banco de dados MySQL',
    'MYSQL_DATABASE_INFOS' => 'Esse banco de dados já deve existir antes de continuar',
    'MYSQL_USERNAME' => 'Nome de usuário MySQL',
    'MYSQL_USERNAME_INFOS' => 'Necessário para se conectar ao seu banco de dados',
    'TABLE_PREFIX' => 'Prefixos das tabelas',
    'TABLE_PREFIX_INFOS' => 'Permite o uso de vários YesWiki no mesmo banco de dados : cada novo YesWiki instalado deve ter um prefixo de tabelas diferentes',
    'MYSQL_PASSWORD' => 'Senha MySQL',
    'YESWIKI_WEBSITE_CONFIGURATION' => 'Configuração do seu site YesWiki',
    'YOUR_WEBSITE_NAME' => 'Nome do seu site',
    'YOUR_WEBSITE_NAME_INFOS' => 'Isso pode ser um NomeWiki ou qualquer outro título que aparecerá no abas e janelas',
    'HOMEPAGE' => 'Página inicial',
    'HOMEPAGE_INFOS' => 'Página inicial do seu YesWiki. Deve ser um NomeWiki.',
    'KEYWORDS' => 'Palavras-chave',
    'KEYWORDS_INFOS' => 'Palavras-chave que serão inseridas no código HTML (meta-dados)',
    'DESCRIPTION' => 'Descrição',
    'DESCRIPTION_INFOS' => 'A descrição do seu site que serão inserida no código HTML (meta-dados)',
    'CREATION_OF_ADMIN_ACCOUNT' => 'Criar uma conta de administrador',
    'ADMIN_ACCOUNT_CAN' => 'A conta de administrador permite',
    'MODIFY_AND_DELETE_ANY_PAGE' => 'Editar e apagar qualquer página',
    'MODIFY_ACCESS_RIGHTS_ON_ANY_PAGE' => 'Modificar direitos de acesso a qualquer página',
    'GENERATE_ACCESS_RIGHTS_ON_ANY_ACTION_OR_HANDLER' => 'Gerenciar os direitos de acesso a qualquer ação ou handler',
    'GENERATE_GROUPS' => 'Gerenciar grupos, adicionar / remover usuários do grupo Administrador  (Ter os mesmos direitos que ele)',
    'ALL_ADMIN_TASKS_ARE_DESCRIBED_IN_THE_PAGE' => 'Todas as tarefas de administração são descritos na página "AdministrationDeYesWiki" acessível a partir da página inicial',
    'USE_AN_EXISTING_ACCOUNT' => 'Usar uma conta ja existente',
    'NO' => 'Não',
    // 'YES' => 'Oui',
    'OR_CREATE_NEW_ACCOUNT' => 'Ou criar uma nova conta',
    'ADMIN' => 'Administrador',
    'MUST_BE_WIKINAME' => 'Deve ser um NomeWiki',
    'PASSWORD' => 'Senha',
    'EMAIL_ADDRESS' => 'Endereço de e-mail',
    'MORE_OPTIONS' => 'Opções adicionais',
    'ADVANCED_CONFIGURATION' => '+ Configuração avançada',
    'URL_REDIRECTION' => 'Redirecionamento de URL',
    'NEW_INSTALL_VALUES_CHANGE_ONLY_IF_YOU_KNOW_WHAT_YOU_ARE_DOING' => 'Esta é uma nova instalação. O instalador vai tentar encontrar os valores adequados. Mude-os somente se você souber o que está fazendo',
    'PAGENAME_WILL_BE_ADDED_AFTER_CHANGE_JUST_FOR_REDIRECTION' => 'Os nomes das páginas serão adicionados diretamente ao URL base do seu site YesWiki. Retire a parte "?wiki =" somente se você estiver usando o redirecionamento (veja abaixo)',
    'BASE_URL' => 'URL base',
    'REDIRECTION_SHOULD_BE_ACTIVE_ONLY_IF_USED_IN_YESWIKI' => 'O mode "redirecionamento automático" deve ser selecionado somente se você usar YesWiki com o redirecionamento de URL (se você não sabe o que é o redirecionamento de URL, não ativar essa opção)',
    // 'HTML_INSERTION_HELP_TEXT' => 'Augmente grandement les fonctionnalités du wiki, en permettant l\'ajout de vidéos et iframe par exemple, mais est moins sécurisé',
    // 'INDEX_HELP_TEXT' => 'Indique dans les meta-données html et dans le fichier robots.txt si votre site doit etre indexé par les moteurs de recherche ou pas',
    'ACTIVATE_REDIRECTION_MODE' => 'Ativando o método "Redirecionamento automático"',
    'OTHER_OPTIONS' => 'Outras opções',
    'OBLIGE_TO_PREVIEW_BEFORE_SAVING_PAGE' => 'Para impor uma pré-visualização antes de salvar uma página',
    'AUTHORIZE_HTML_INSERTION' => 'Permitir a inserção de HTML puro',
    // 'AUTHORIZE_INDEX_BY_ROBOTS' => 'Autoriser l\'indexation par les moteurs de recherche',
    'CONTINUE' => 'Continuar',

    // setup/install.php
    'PROBLEM_WHILE_INSTALLING' => 'Problema no procedimento de instalação',
    'VERIFICATION_OF_DATAS_AND_DATABASE_INSTALLATION' => 'Testando a instalação e configuração de base de dados',
    'VERIFY_MYSQL_PASSWORD' => 'Verificando a senha MySQL',
    'INCORRECT_MYSQL_PASSWORD' => 'A senha MySQL está incorreta',
    'TEST_MYSQL_CONNECTION' => 'Teste de conexão MySQL',
    'SEARCH_FOR_DATABASE' => 'Busca de banco de dados',
    'GO_BACK' => 'Retorno',
    'NO_DATABASE_FOUND_TRY_TO_CREATE' => 'O banco de dados que você escolheu não existe. Vamos tentar criar-o',
    'TRYING_TO_CREATE_DATABASE' => 'Tentativa de criação do banco de dados',
    'DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY' => 'Criação de base de dados impossível. Você deve criar o banco de dados manualmente antes de instalar YesWiki',
    'SEARCH' => 'Busca de banco de dados',
    'DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT' => 'O banco de dados que você escolheu não existe, você deve criá-lo antes de instalar YesWiki',
    'CHECKING_THE_ADMIN_PASSWORD' => 'Verificando a senha do administrador',
    'CHECKING_THE_ADMIN_PASSWORD_CONFIRMATION' => 'Verificando a identidade das senhas administradores',
    // 'CHECKING_ROOT_PAGE_NAME' => 'V&eacute;rification du nom de la page d\'accueil',
    // 'INCORRECT_ROOT_PAGE_NAME' => 'Le nom de la page d\'accueil doit uniquement contenir des lettres non accentuées, des chiffres, \'_\', \'-\' ou \'.\'',
    'ADMIN_PASSWORD_ARE_DIFFERENT' => 'As senhas administradores são diferentes',
    'DATABASE_INSTALLATION' => 'Instalando o banco de dados',
    'CREATION_OF_TABLE' => 'Criando tabela',
    // 'SQL_FILE_NOT_FOUND' => 'Fichier SQL non trouv&eacute;',
    // 'NOT_POSSIBLE_TO_CREATE_SQL_TABLES' => 'Impossible de créer les tables SQL.',
    'ALREADY_CREATED' => 'Já criada',
    'ADMIN_ACCOUNT_CREATION' => 'Criando a conta de administrador',
    'INSERTION_OF_PAGE' => 'Inserir a página',
    'ALREADY_EXISTING' => 'Já existe',
    'UPDATING_FROM_WIKINI_0_1' => 'Sendo atualizado WikiNi 0,1',
    'TINY_MODIFICATION_OF_PAGES_TABLE' => 'Muito leve mudança na tabela de página',
    'ALREADY_DONE' => 'Já fiz? Hmm!',
    'INSERTION_OF_USER_IN_ADMIN_GROUP' => 'Inserção do usuário especificado no grupo de administração',
    'NEXT_STEP_WRITE_CONFIGURATION_FILE' => 'No próximo passo o instalador tentará de escrever o arquivo de configuração',
    'VERIFY_YOU_HAVE_RIGHTS_TO_WRITE_FILE' => 'Verifique se o servidor web tem o direito de gravação para este arquivo, caso contrário você terá que alterar-o manualmente',
    // 'CHECK_EXISTING_TABLE_PREFIX' => 'Vérification de l\'existence du préfixe de table',
    // 'TABLE_PREFIX_ALREADY_USED' => 'Le préfixe de table est déjà utilisé. Veuillez en choisir un nouveau.',

    // setup/writeconfig.php
    'WRITING_CONFIGURATION_FILE' => 'Escrevendo o arquivo de configuração',
    'CREATED' => 'Criado',
    'DONT_CHANGE_YESWIKI_VERSION_MANUALLY' => 'não alterar manualmente a yeswiki_version',
    'WRITING_CONFIGURATION_FILE_WIP' => 'Criando o arquivo de configuração',
    'FINISHED_CONGRATULATIONS' => 'Esta feito ! parabéns !',
    'GO_TO_YOUR_NEW_YESWIKI_WEBSITE' => 'Ir para o seu novo site YesWiki',
    'IT_IS_RECOMMANDED_TO_REMOVE_WRITE_ACCESS_TO_CONFIG_FILE' => 'É recomendado remover o acesso em gravação para o arquivo',
    'THIS_COULD_BE_UNSECURE' => 'Isso pode ser uma falha de segurança',
    'WARNING' => 'ATENÇÃO',
    'CONFIGURATION_FILE' => 'o arquivo de configuração',
    'CONFIGURATION_FILE_NOT_CREATED' => 'não pude ser criado',
    'TRY_CHANGE_ACCESS_RIGHTS_OR_FTP_TRANSFERT' => 'Verifique que servidor tenha os direitos de acesso em gravaçao sobre este arquivo. Se por algum motivo você não pode fazer isso, você deve copiar o seguinte num arquivo e transferi-lo por ftp no servidor',
    'DIRECTLY_IN_THE_YESWIKI_FOLDER' => 'diretamente no diretório YesWiki. Depois de ter feito isso, o site YesWiki deve funcionar corretamente',
    'TRY_AGAIN' => 'tentar de novo',
    
    // API
    // 'USERS' => 'Utilisateurs',
    // 'GROUPS' => 'Groupes',

    // YesWiki\User class
    // 'USER_CONFIRM_DELETE' => 'Êtes-vous sûr·e de vouloir supprimer l’utilisateur·ice ?',
    // 'USER_DELETE_LONE_MEMBER_OF_GROUP' => 'Vous ne pouvez pas supprimer un utilisateur qui est seul dans au moins un groupe',
    // 'USER_DELETE_QUERY_FAILED' => 'La requête de suppression de l\'utilisateur dans la base de données a échoué',
    // 'USER_EMAIL_S_MAXIMUM_LENGTH_IS' => 'Le nomnbre maximum de caractères d\'un email d\'utilisateur est',
    // 'USER_LISTGROUPMEMBERSHIPS_QUERY_FAILED' => 'La requête pour lsiter les groupes auquels l\'utilisateur appartient a échoué',
    // 'USER_MUST_BE_ADMIN_TO_DELETE' => 'Vous devez être administrateur pour supprimer un utilisateur',
    // 'USER_NAME_S_MAXIMUM_LENGTH_IS' => 'Le nomnbre maximum de caractères d\'un nom d\'utilisateur est',
    // 'USER_NO_SPACES_IN_PASSWORD' => 'Les espaces ne sont pas autorisés dans un mot de passe',
    // 'USER_PASSWORD_MINIMUM_NUMBER_OF_CHARACTERS_IS' => 'Le nombre minimum de caractères d\'un mot de passe est',
    // 'USER_PASSWORDS_NOT_IDENTICAL' => 'Les deux mots de passe saisis doivent être identiques',
    // 'USER_PASSWORD_TOO_SHORT' => 'Mot de passe trop court',
    // 'USER_THIS_EMAIL_IS_ALLREADY_USED_ON_THIS_WIKI' => 'L\'email saisi est déjà utilisé sur ce wiki',
    // 'USER_THIS_IS_NOT_A_VALID_NAME' => 'Ceci n\'est pas un nom d\'utilisateur valide',
    // 'USER_THIS_IS_NOT_A_VALID_EMAIL' => 'Ceci n\'est pas un email valide',
    // 'USER_UPDATE_QUERY_FAILED' => 'La requête de mise à jour de l\'utilisateur dans la base de données a échoué',
    // 'USER_YOU_MUST_SPECIFY_A_NAME' => 'Veuillez saisir un nom pour l\'utilisateur',
    // 'USER_YOU_MUST_SPECIFY_AN_EMAIL' => 'Veuillez saisir un email pour l\'utilisateur',
    // 'USER_USERSTABLE_MISTAKEN_ARGUMENT' => 'l\'action usertable a reçu un argument non autorisé',
    // 'USER_WRONG_PASSWORD' => 'Mot de passe incorrect',
    // 'USER_INCORRECT_PASSWORD_KEY' => 'La clef de validation du mot de passe est incorrecte',
    // 'USER_PASSWORD_UPDATE_FAILED' => 'La modification du mot de passe a échoué',
    // 'USER_NOT_LOGGED_IN_CANT_LOG_OUT' => 'Déconnexion impossible car personne n\'est connecté',
    // 'USER_TRYING_TO_LOG_WRONG_USER_OUT' => 'Vous essayez de déconnecter quelqu\'un d\'autre',
    // 'USER_CREATION_FAILED' => 'La création de l\'utilisateur a échoué',
    // 'USER_LOAD_BY_NAME_QUERY_FAILED' => 'La requête de chargement de l\'utilisateur par son nom depuis la base de données a échoué',
    // 'USER_NO_USER_WITH_THAT_NAME' => 'Il n\'y a aucun utilisateur avec ce nom',
    // 'USER_LOAD_BY_EMAIL_QUERY_FAILED' => 'La requête de chargement de l\'utilisateur par son email depuis la base de données a échoué',
    // 'USER_NO_USER_WITH_THAT_EMAIL' => 'Il n\'y a aucun utilisateur avec cet email',
    // 'USER_UPDATE_MISSPELLED_PROPERTIES' => 'La liste des champs à modifier par updateIntoDB est certainement défectueuse',
    // 'USER_CANT_DELETE_ONESELF' => 'Vous ne pouvez supprimer votre compte',
    // 'USER_TRYING_TO_MODIFY_AN_INEXISTANT_USER' => 'L\'utilisateur en cours de modification n\'existe pas dans la base de données',
    // 'USER_YOU_ARE_NOW_DISCONNECTED' => 'Vous êtes à présent déconnecté',
    // 'USER_PARAMETERS_SAVED' => 'Paramètres sauvegardés',
    // 'USER_DELETED' => 'utilisateur supprimé',
    // 'USER_PASSWORD_CHANGED' => 'Mot de passe modifié',
    // 'USER_EMAIL_ADDRESS' => 'Adresse de messagerie électronique',
    // 'USER_DOUBLE_CLICK_TO_EDIT' => 'Éditer en double-cliquant',
    // 'USER_SHOW_COMMENTS_BY_DEFAULT' => 'Par défaut, montrer les commentaires',
    // 'USER_MAX_NUMBER_OF_LASTEST_COMMENTS' => 'Nombre maximum de derniers commentaires',
    // 'USER_MAX_NUMBER_OF_VERSIONS' => 'Nombre maximum de versions',
    // 'USER_MOTTO' => 'Votre devise',
    // 'USER_UPDATE' => 'Mise &agrave; jour',
    // 'USER_DISCONNECT' => 'Déconnexion',
    // 'USER_CHANGE_THE_PASSWORD' => 'Changement de mot de passe',
    // 'USER_OLD_PASSWORD' => 'Votre ancien mot de passe',
    // 'USER_NEW_PASSWORD' => 'Nouveau mot de passe',
    // 'USER_CHANGE' => 'Changer',
    // 'USER_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED' => 'Vous devez accepter les cookies pour pouvoir vous connecter',
    // 'USER_WIKINAME' => 'Votre NomWiki',
    // 'USER_USERNAME' => 'Votre nom d\'utilisateur, utilisatrice',
    // 'USER_PASSWORD_CONFIRMATION' => 'Confirmation du mot de passe',
    // 'USER_NEW_ACCOUNT' => 'Nouveau compte',
    // 'USER_PASSWORD' => 'Mot de passe',
    // 'USER_ERRORS_FOUND' => 'Erreur(s) trouvée(s)',
    // 'USER_YOU_MUST_SPECIFY_A_POSITIVE_INTEGER_FOR' => 'Il faut une valeur entier positif pour %{name}.',
    // 'USER_YOU_MUST_SPECIFY_YES_OR_NO' => 'Il faut une value \'Y\' ou  \'N\' pour %{name}.',
    // 'USER_YOU_MUST_SPECIFY_A_STRING' => 'Il faut une chaîne de caractères pour %{name}.',

    // YesWiki\Database class
    // 'DATABASE_QUERY_FAILED' => 'La requête a échoué {\YesWiki\Database}',
    // 'DATABASE_YOU_MUST_FIRST_SET_ARGUMENT' => 'Il manque des arguments pour un objet de la classe \YesWiki\Database',
    // 'DATABASE_MISSING_ARGUMENT' => ' manque(nt)',

    // YesWiki\Session class
    // 'SESSION_YOU_MUST_FIRST_SET_ARGUMENT' => 'Il manque l\'argument pour un objet de la classe \YesWiki\Session',

    // gererdroits
    // 'ACLS_RESERVED_FOR_ADMINS' => 'Cette action est r&eacute;serv&eacute;e aux admins',
    // 'ACLS_NO_SELECTED_PAGE' => 'Aucune page n\'a &eacute;t&eacute; s&eacute;lectionn&eacute;e.',
    // 'ACLS_NO_SELECTED_RIGHTS' => 'Vous n\'avez pas s&eacute;lectionn&eacute; de droits &agrave; modifier.',
    // 'ACLS_RIGHTS_WERE_SUCCESFULLY_CHANGED' => 'Droit modifi&eacute;s avec succ&egrave;s',
    // 'ACLS_SELECT_PAGES_TO_MODIFY' => 'Cochez les pages que vous souhaitez modifier et choisissez une action en bas de page',
    // 'ACLS_PAGE' => 'Page',
    // 'ACLS_FOR_SELECTED_PAGES' => 'Actions pour les pages cochées ci dessus',
    // 'ACLS_RESET_SELECTED_PAGES' => 'Réinitialiser (avec les valeurs par défaut définies dans',
    // 'ACLS_REPLACE_SELECTED_PAGES' => 'Remplacer (Les droits actuels seront supprim&eacute;s)',
    // 'ACLS_HELPER' => 'Séparez chaque entrée des virgules, par exemple</br>
    // <b>*</b> (tous les utilisateurs)</br>
    // <b>+</b> (utilisateurs enregistrés)</br>
    // <b>%</b> (créateur de la fiche/page)</br>
    // <b>@nom_du_groupe</b> (groupe d\'utilisateur, ex: @admins)</br>
    // <b>JamesBond</b> (nom YesWiki d\'un utilisateur)</br>
    // <b>!SuperCat</b> (négation, SuperCat n\'est pas autorisé)</br>',
    // 'ACLS_MODE_SIMPLE' => 'Mode simple',
    // 'ACLS_MODE_ADVANCED' => 'Mode avancé',
    // 'ACLS_NO_CHANGE' => 'Ne rien changer',
    // 'ACLS_EVERYBODY' => 'Tout le monde',
    // 'ACLS_AUTHENTIFICATED_USERS' => 'Utilisateurs connectés',
    // 'ACLS_OWNER' => 'Propriétaire de la page',
    // 'ACLS_ADMIN_GROUP' => 'Groupe admin',
    // 'ACLS_LIST_OF_ACLS' => 'Liste des droits séparés par des virgules',
    // 'ACLS_UPDATE' => 'Mettre &agrave; jour',
    // 'ACLS_COMMENTS_CLOSED' => 'Commentaires fermés',

    // include/services/ThemeManager.php
    // 'THEME_MANAGER_THEME_FOLDER' => 'Le dossier du thème ',
    // 'THEME_MANAGER_SQUELETTE_FILE' => 'Le fichier du squelette ',
    // 'THEME_MANAGER_NOT_FOUND' => ' n\'a pas été trouvé.',
    // 'THEME_MANAGER_ERROR_GETTING_FILE' => 'Une erreur s\'est produite en chargeant ce fichier : ',
    // 'THEME_MANAGER_CLICK_TO_INSTALL' => 'Cliquer pour installer le thème ',
    // 'THEME_MANAGER_AND_REPAIR' => ' et réparer le site',
    // 'THEME_MANAGER_LOGIN_AS_ADMIN' => 'Veuillez vous connecter en tant qu\'administrateur pour faire la mise à jour.',

    // actions/EditConfigAction.php
    // 'EDIT_CONFIG_TITLE' => 'Modification du fichier de configuration',
    // 'EDIT_CONFIG_CURRENT_VALUE' => 'Valeur actuelle ',
    // 'EDIT_CONFIG_SAVE' => 'Configuration sauvegardée',
    // 'EDIT_CONFIG_HINT_WAKKA_NAME' => 'Titre de votre wiki',
    // 'EDIT_CONFIG_HINT_ROOT_PAGE' => 'Nom de la page d\'accueil',
    // 'EDIT_CONFIG_HINT_DEFAULT_WRITE_ACL' => 'Droits d\'écriture par défaut des pages (* pour tous, + pour personnes identifiées, @admins pour groupe admin)',
    // 'EDIT_CONFIG_HINT_DEFAULT_READ_ACL' => 'Droits de lecture par défaut des pages (* pour tous, + pour personnes identifiées, @admins pour groupe admin)',
    // 'EDIT_CONFIG_HINT_DEFAULT_COMMENT_ACL' => 'Droits de commentaires par défaut des pages (comment-closed pour fermer, + pour personnes identifiées, @admins pour groupe admin)',
    // 'EDIT_CONFIG_HINT_COMMENTS_ACTIVATED' => 'Commentaires activés (true ou false)',
    // 'EDIT_CONFIG_HINT_DEBUG' => 'Activer le mode de debug (yes ou no)',
    // 'EDIT_CONFIG_HINT_DEFAULT_LANGUAGE' => 'Langue par défaut (fr ou en ou ... auto = langue du navigateur)',
    // 'EDIT_CONFIG_HINT_CONTACT_FROM' => 'Remplacer le mail utilisé comme expéditeur des messages',
    // 'EDIT_CONFIG_HINT_MAIL_CUSTOM_MESSAGE' => 'Message personnalisé des mails envoyés depuis l\'action contact',
    // 'EDIT_CONFIG_HINT_PASSWORD_FOR_EDITING' => 'Mot de passe demandé pour modifier les pages (voir doc gestion des spams)',
    // 'EDIT_CONFIG_HINT_PASSWORD_FOR_EDITING_MESSAGE' => 'Message informatif pour demander le mot de passe (voir doc gestion des spams)',
    // 'EDIT_CONFIG_HINT_ALLOW_DOUBLECLIC' => 'Autoriser le doubleclic pour éditer les menus et pages spéciales (true ou false)',
    // 'EDIT_CONFIG_HINT_TIMEZONE' => 'Fuseau horaire du site (ex. UCT, Europe/Paris, Europe/London, GMT = utiliser celui du serveur,)',
    // 'EDIT_CONFIG_HINT_ALLOWED_METHODS_IN_IFRAME' => 'Méthodes autorisées à être affichées dans les iframes (iframe,editiframe,bazariframe,render,all = autoriser tout)',
    // 'EDIT_CONFIG_HINT_REVISIONSCOUNT' => 'Nombre maximum de versions d\'une page affichées par le handler `/revisions`.',
    // 'EDIT_CONFIG_HINT_HTMLPURIFIERACTIVATED' => 'Activer le nettoyage HTML avant sauvegarde. Attention, modifie le contenu à la sauvegarde ! (true ou false)',
    // 'EDIT_CONFIG_HINT_FAVORITES_ACTIVATED' => 'Activer les favoris (true ou false)',
    // 'EDIT_CONFIG_GROUP_CORE' => 'Paramètres Principaux',
    // 'EDIT_CONFIG_GROUP_ACCESS' => 'Droit d\'accès',
    // 'EDIT_CONFIG_GROUP_EMAIL' => 'Emails',

    // actions/userstable.php
    'USERSTABLE_USER_DELETED' => 'O utilizador "{username}" foi eliminado.',
    'USERSTABLE_USER_NOT_DELETED' => 'O utilizador "{username}" não foi eliminado.',
    'USERSTABLE_NOT_EXISTING_USER' => 'O utilizador "{username}" não existe!',
    'GROUP_S' => 'Grupo(s)',

    // handlers/deletepage
    // 'DELETEPAGE_CANCEL' => 'Annuler',
    // 'DELETEPAGE_CONFIRM' => 'Voulez-vous vraiment supprimer d&eacute;finitivement la page {tag}&nbsp;?',
    // 'DELETEPAGE_CONFIRM_WHEN_BACKLINKS' => 'Voulez-vous vraiment supprimer d&eacute;finitivement la page {tag} malgr&eacute; la pr&eacute;sence de liens&nbsp;?',
    // 'DELETEPAGE_DELETE' => 'Supprimer',
    // 'DELETEPAGE_MESSAGE' => 'La page {tag} a d&eacute;finitivement &eacute;t&eacute; supprim&eacute;e',
    // 'DELETEPAGE_NOT_ORPHEANED' => 'Cette page n\'est pas orpheline.',
    // 'DELETEPAGE_NOT_OWNER' => 'Vous n\'&ecirc;tes pas le propri&eacute;taire de cette page.',
    // 'DELETEPAGE_PAGES_WITH_LINKS_TO' => 'Pages ayant un lien vers {tag} :',
    'DELETEPAGE_NOT_DELETED' => 'Página não apagada.',

    // handlers/edit
    // 'EDIT_ALERT_ALREADY_SAVED_BY_ANOTHER_USER' => 'ALERTE : '.
    //     'Cette page a &eacute;t&eacute; modifi&eacute;e par quelqu\'un d\'autre pendant que vous l\'&eacute;ditiez.'."\n".
    //     'Veuillez copier vos changements et r&eacute;&eacute;diter cette page.',
    // 'EDIT_NO_WRITE_ACCESS' => 'Vous n\'avez pas acc&egrave;s en &eacute;criture &agrave; cette page !',
    // 'EDIT_NO_CHANGE_MSG' => 'Cette page n\'a pas &eacute;t&eacute; enregistr&eacute;e car elle n\'a subi aucune modification.',
    // 'EDIT_PREVIEW' => 'Aper&ccedil;u',

    // handlers/update
    // 'UPDATE_ADMIN_PAGES' => 'Mettre à jour les pages de gestion',
    // 'UPDATE_ADMIN_PAGES_CONFIRM' => 'Confirmer la mise à jour des pages : ',
    // 'UPDATE_ADMIN_PAGES_HINT' => 'Mets à jour les pages de gestion avec les dernières fonctionnalités. Ceci est réversible.',
    // 'UPDATE_ADMIN_PAGES_ERROR' => 'Il n\'a pas été possible de mettre à jour toutes les pages de gestion !',
    // 'UPDATE_PAGE_NOT_FOUND_IN_DEFAULT_SQL' => 'la page "{{page}}" n\'a pas été trouvée dans default-content.sql',

    // handlers/referrers_sites.php
    // 'LINK_TO_REFERRERS_DOMAINS' => 'Domaines faisant r&eacute;f&eacute;rence &agrave; ce wiki ({beginLink}voir la liste des pages externes{endLink}):',
    // 'LINK_TO_REFERRERS_SITES' => 'Sites faisant r&eacute;f&eacute;rence &agrave; ce wiki ({beginLink}voir la liste des domaines{endLink}):',
    // 'LINK_TO_REFERRERS_SITES_ONLY_TAG' => 'Voir les domaines faisant r&eacute;f&eacute;rence &agrave; {tag} seulement',
    // 'LINK_TO_REFERRERS_SITES_PAGES_ONLY_TAG' => 'Voir les r&eacute;f&eacute;rences &agrave; {tag} seulement',
    // 'LINK_TO_REFERRERS_ALL_DOMAINS' => 'Voir tous les domaines faisant r&eacute;f&eacute;rence',
    // 'LINK_TO_REFERRERS_ALL_REFS' => 'Voir toutes les r&eacute;f&eacute;rences',
    // 'LINK_TO_REFERRERS_SITES_NO_GLOBAL' => 'Domaines faisant r&eacute;f&eacute;rence &agrave; {tag}{since} ({beginLink}voir la liste des pages externes{endLink}):',
    // 'LINK_TO_REFERRERS_NO_GLOBAL' => 'Pages externes faisant r&eacute;f&eacute;rence &agrave; {tag}{since} ({beginLink}voir la liste des domaines{endLink}):',
    // 'REFERRERS_SITES_SINCE' => 'depuis {time}',
    // 'REFERRERS_SITES_24_HOURS' => '24 heures',
    // 'REFERRERS_SITES_X_DAYS' => '{nb} jours',

    // handlers/revisions
    // 'SUCCESS_RESTORE_REVISION' => 'La version a bien été restaurée',
    // 'TITLE_PAGE_HISTORY' => 'Historique de la page',
    // 'TITLE_ENTRY_HISTORY' => 'Historique de la fiche',
    // 'REVISION_VERSION' => 'Version N°',
    // 'REVISION_ON' => 'du',
    // 'REVISION_BY' => 'par',
    // 'CURRENT_VERSION' => 'Version actuelle',
    // 'RESTORE_REVISION' => 'Restaurer cette version',
    // 'DISPLAY_WIKI_CODE' => 'Afficher le code Wiki',

    // handlers/show
    // 'COMMENT_INFO' => 'Ceci est un commentaire sur {tag} post&eacute; par {user} &agrave; {time}',
    // 'EDIT_ARCHIVED_REVISION' => 'R&eacute;&eacute;diter cette version archiv&eacute;e',
    // 'REVISION_IS_ARCHIVE_OF_TAG_ON_TIME' => 'Ceci est une version archivée de {link} à {time}',
    // 'REDIRECTED_FROM' => 'Redirig&eacute; depuis {linkFrom}',

    // handlers/page/show + handlers/page/iframe
    // 'NOT_FOUND_PAGE' => 'Cette page n\'existe pas encore, voulez-vous la {beginLink}créer{endLink} ?',

    // YesWiki
    // 'UNKNOWN_INTERWIKI' => 'interwiki inconnu',

    // templates/multidelete-macro.twig
    // 'NUMBER_OF_ELEMENTS' => 'Nombre d\'éléments sélectionnés',

    // reactions
    // 'REACTION_EMPTY_ID' => 'le paramètre "id" doit obligatoirement être renseigné',
    // 'REACTION_LIKE' => 'J\'approuve',
    // 'REACTION_DISLIKE' => 'Je n\'approuve pas',
    // 'REACTION_ANGRY' => 'Faché·e',
    // 'REACTION_SURPRISED' => 'Surpris·e',
    // 'REACTION_THINKING' => 'Dubitatif·ve',
    // 'REACTION_LOGIN_TO_REACT' => 'Pour réagir, identifiez-vous!',
    // 'REACTION_SHARE_YOUR_REACTION' => 'Partagez votre réaction à propos de ce contenu',
    // 'REACTION_TO_ALLOW_REACTION' => 'Pour vous permettre de réagir',
    // 'REACTION_PLEASE_LOGIN' => 's\'identifier',
    // 'REACTION_NB_REACTIONS_LEFT' => 'choix possible(s)',
    // 'REACTION_ADMINISTER_REACTIONS' => 'Administrer les réactions',
    // 'REACTION_CONNECT_AS_ADMIN' => 'Veuillez vous connecter en tant qu\'admin pour administrer les réactions.',
    // 'REACTION_USER' => 'Utilisateur·ice',
    // 'REACTION_YOUR_REACTIONS' => 'Vos réactions',
    // 'REACTION_VOTE' => 'Vote',
    // 'REACTION_DATE' => 'Date',
    // 'REACTION_DATE_UNKNOWN' => 'Date inconnue',
    // 'REACTION_DELETE' => 'Supprimer',
    // 'REACTION_DELETE_ALL' => 'Tout supprimer',
    // 'REACTION_LOGIN_TO_SEE_YOUR_REACTION' => 'Se connecter pour voir vos réactions.',
    // 'REACTION_YOU_VOTED' => 'Vous avez voté',
    // 'REACTION_FOR_POLL' => 'au sondage',
    // 'REACTION_FROM_PAGE' => 'de la page',
    // 'REACTION_ON_ENTRY' => 'Réaction sur une fiche',
    // 'REACTION_TITLE_PARAM_NEEDED' => 'Le paramètre \'titre\' est obligatoire',
];
