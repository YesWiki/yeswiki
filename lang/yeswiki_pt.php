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
*@copyright     2014 Outils-Réseaux
*/

$GLOBALS['translations'] = array(

// wakka.php
'UNKNOWN_ACTION' => 'A&ccedil;&atilde;o desconhecida',
'INVALID_ACTION' => 'A&ccedil;&atilde;o inv&aacute;lida',
'ERROR_NO_ACCESS' => 'Erro: você não tem acesso a essa ação',
'INCORRECT_CLASS' => 'Classe incorreta',
'UNKNOWN_METHOD' => 'Método desconhecido',
'FORMATTER_NOT_FOUND' => 'Não foi possível encontrar o treinador',
'HANDLER_NO_ACCESS' => 'Você não pode acessar esta página, pelo handler especificado.',
'NO_REQUEST_FOUND' => '$ _REQUEST[] não foi encontrado. Wakka requer PHP 4.1.0 ou mais reciente!',
'SITE_BEING_UPDATED' => 'Este site está sendo atualizado. Por favor, tente novamente mais tarde.',
'INCORRECT_PAGENAME' => 'O nome da página é incorreto.',
'DB_CONNECT_FAIL' => 'Por razões além do nosso controle, o conteúdo deste YesWiki está temporariamente indisponível. Por favor, tente novamente mais tarde. Obrigado pela sua compreensão.',
'LOG_DB_CONNECT_FAIL' => 'YesWiki: a conexao com a base de dados falhou', // sans accents car commande systeme
'INCORRECT_PAGENAME' => 'O nome da página é incorreto.',
'HOMEPAGE_WIKINAME' => 'PaginaInicial',
'MY_YESWIKI_SITE' => 'Meu site YesWiki',

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

// actions/changestyle.php ignoree...

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
'ONLY_ALPHANUM_FOR_GROUP_NAME' => 'Os nomes dos grupos só pode conter caracteres alfanuméricos',

// actions/edithandlersacls.class.php
'HANDLER_RIGHTS' => 'Direitos do handler',
'ERROR_WHILE_SAVING_HANDLER_ACL' => 'Ocorreu um erro durante a gravaçao do ACL para o handler',
'NEW_ACL_FOR_HANDLER' => 'Nouvelle ACL pour le handler',
'NEW_ACL_SUCCESSFULLY_SAVED_FOR_HANDLER' => 'Nouvelle ACL enregistr&eacute;e avec succ&egrave;s pour le handler',
'EDIT_RIGHTS_FOR_HANDLER' => 'Editar direitos do handler',

// actions/erasespamedcomments.class.php ignoree...
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
'UNKNOWN' => 'desconhecido',
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
'UNKNOWN' => 'Desconhecido',
'BY' => 'por',

// actions/mychanges.php
'YOUR_MODIFIED_PAGES_ORDERED_BY_MODIFICATION_DATE' => 'Lista de páginas que você editou, ordenadas por data de modificação',
'YOUR_MODIFIED_PAGES_ORDERED_BY_NAME' => 'Lista de páginas que você editou, em ordem alfabética',
'YOU_DIDNT_MODIFY_ANY_PAGE' => 'Você não mudou nenhuma página',
'YOU_ARENT_LOGGED_IN' => 'Você ainda não se identificou',
'IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES' => 'impossível ver a lista de páginas que você editou',
'LIST_OF_PAGES_WHERE_YOU_ARE_THE_OWNER' => 'Lista de páginas que você possui',
'YOU_DONT_OWN_ANY_PAGE' => 'Você é o proprietário de nenhuma página',

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

// actions/trail.php
'ERROR_ACTION_TRAIL' => 'Erro Ação {{trail ...}}',
'INDICATE_THE_PARAMETER_TOC' => 'Especifique o nome da página de resumo, parâmetro "toc"',

// actions/usersettings.php
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
'UPDATE' => 'Atualização',
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
'YOUR_WIKINAME' => 'Seu NomeWiki',
'PASSWORD_5_CHARS_MINIMUM' => 'Senha (pelo menos 5 carácteres)',
'REMEMBER_ME' => 'Lembre-se de mim',
'IDENTIFICATION' => 'Identificação',
'FILL_THE_NEXT_FIELDS_IF_YOU_LOGGIN_FOR_THE_FIRST_TIME_AND_REGISTER' => 'Os seguintes campos devem ser preenchidos se você entrar pela primeira vez (para que você crie uma conta)',
'PASSWORD_CONFIRMATION' => 'Confirme a senha',
'NEW_ACCOUNT' => 'Nova conta',


// actions/wantedpages.php 
'NO_PAGE_TO_CREATE' => 'Nenhuma página para criar',

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
'DEFAULT_LANGUAGE_INFOS' => 'Idioma padrão para o interface YesWiki. E sempre possível alterar o idioma para cada página criada',
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
'ACTIVATE_REDIRECTION_MODE' => 'Ativando o método "Redirecionamento automático"',
'OTHER_OPTIONS' => 'Outras opções',
'OBLIGE_TO_PREVIEW_BEFORE_SAVING_PAGE' => 'Para impor uma pré-visualização antes de salvar uma página',
'AUTHORIZE_HTML_INSERTION' => 'Permitir a inserção de HTML puro',
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
'ADMIN_PASSWORD_ARE_DIFFERENT' => 'As senhas administradores são diferentes',
'DATABASE_INSTALLATION' => 'Instalando o banco de dados',
'CREATION_OF_TABLE' => 'Criando tabela',
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

);