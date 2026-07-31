<?php

return [
    // relocated from tools/aceditor/lang/aceditorjs_pt.inc.php (ticket 11)
    'ACTION_BUILDER_COPY' => 'Entendido',
    'ACTION_BUILDER_PREVIEW' => 'Visão geral (não clicável)',
    'ACTION_BUILDER_ONLINEDOC' => 'Documentação online',
    'ACTION_BUILDER_UPDATE_CODE' => 'Atualizar o código',
    'ACTION_BUILDER_INSERT_CODE' => 'Inserir na página',
    'ACTION_BUILDER_OWNER' => 'Proprietário do arquivo',
    'ACTION_BUILDER_MODIFICATION_DATE' => 'Data alterada',
    'ACTION_BUILDER_CREATION_DATE' => 'Data de criação',
    'ACTION_BUILDER_FORM_ID' => 'Formulário',

    // yw-datatable.js runs in the browser, so its labels belong in the JS catalog
    'DATATABLE_SEARCH_PLACEHOLDER' => 'Pesquisar...',
    'DATATABLE_NO_RESULTS' => 'Nenhum resultado encontrado',
    'DATATABLE_PAGE_SIZE_LABEL' => 'Mostrar',

    // ticket 16: htmx navigation never fires beforeunload, so the edit guard asks
    // here instead -- this string is the only part of it a user reads
    'EDIT_LEAVE_WITHOUT_SAVING' => 'Tem alterações não guardadas. Sair desta página?',
];
