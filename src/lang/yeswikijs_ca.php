<?php

return [
    // relocated from tools/aceditor/lang/aceditorjs_ca.inc.php (ticket 11)
    'ACTION_BUILDER_COPY' => 'Còpia',
    'ACTION_BUILDER_PREVIEW' => 'Vista prèvia (no es pot fer clic)',
    'ACTION_BUILDER_ONLINEDOC' => 'Documentació en línia',
    'ACTION_BUILDER_UPDATE_CODE' => 'Actualitza el codi',
    'ACTION_BUILDER_INSERT_CODE' => 'Insereix a la pàgina',
    'ACTION_BUILDER_OWNER' => 'Propietari del llistat',
    'ACTION_BUILDER_MODIFICATION_DATE' => 'Data modificada',
    'ACTION_BUILDER_CREATION_DATE' => 'Data de creació',
    'ACTION_BUILDER_FORM_ID' => 'Formulari',

    // yw-datatable.js runs in the browser, so its labels belong in the JS catalog
    'DATATABLE_SEARCH_PLACEHOLDER' => 'Cerca...',
    'DATATABLE_NO_RESULTS' => 'Cap resultat',
    'DATATABLE_PAGE_SIZE_LABEL' => 'Mostra',

    // ticket 16: htmx navigation never fires beforeunload, so the edit guard asks
    // here instead -- this string is the only part of it a user reads
    'EDIT_LEAVE_WITHOUT_SAVING' => 'Teniu canvis sense desar. Voleu sortir d\'aquesta pàgina?',
];
