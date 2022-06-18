<?php

return [

    // fields/CalcField.php
    'BAZ_FORM_EDIT_CALC_LABEL' => 'Calculs',
    'BAZ_FORM_EDIT_DISPLAYTEXT_LABEL' => 'Texte d\'affichage',
    'BAZ_FORM_EDIT_DISPLAYTEXT_HELP' => 'Ajouter si besoin une unité après {value}, (ex: `{value} €`)',
    'BAZ_FORM_EDIT_FORMULA_LABEL' => 'Formule',
    'BAZ_FORM_CALC_HINT' => "CHAMP EXPERIMENTAL{\\n}".
       "La formule doit être une formule mathématique.{\\n}".
       "Il est possible de faire référence à la valeur d'un champ en tapant son nom (ex: `+ sin(bf_number)*2` ),{\\n}".
       "ou de tester la valeur d'un champ (ex: `test(checkboxListeTypebf_type,premiere_cle)`{\\n}".
       "qui rend 1 si checkboxListeTypebf_type == premiere_cle sinon 0).",

    // fields/FileField.php
    'BAZ_FILEFIELD_FILE' => 'Fichier : {filename}',
    'BAZ_FORM_EDIT_FILE_READLABEL_LABEL' => 'Label à l\'affichage',

    // fields/MapField.php
    'IMAGEFIELD_TOO_LARGE_IMAGE' => 'L\'image est trop grosse, maximum {imageMaxSize} octets',

    // fields/MapField.php
    'BAZ_POSTAL_CODE_NOT_FOUND' => 'Pas de ville trouvée pour le code postal : {input}',
    'BAZ_POSTAL_CODE_HINT' => 'Veuillez entrer 5 chiffres pour voir les villes associées au code postal',
    'BAZ_TOWN_NOT_FOUND' => 'Pas de ville trouvée pour la recherche : {input}',
    'BAZ_TOWN_HINT' => 'Veuillez entrer les 3 premieres lettres pour voir les villes associées',
    'BAZ_GEOLOC_NOT_FOUND' => 'Adresse non trouvée, veuillez déplacer le point vous meme ou indiquer les coordonnées',
    'BAZ_MAP_ERROR' => 'Une erreur est survenue: {msg}',
    'BAZ_NOT_VALID_GEOLOC_FORMAT' => 'Format de coordonnées GPS non valide (que des chiffres et un point . pour les décimales)',

    // libs/bazar.edit_lists.js
    'BAZ_EDIT_LISTS_CONFIRM_DELETE' => 'Confirmez-vous la suppression de cette valeur dans la liste ?',
    'BAZ_EDIT_LISTS_DELETE_ERROR' => 'Le dernier élément ne peut être supprimé.',

    // libs/bazar.js
    'BAZ_FORM_REQUIRED_FIELD' => 'Veuillez saisir tous les champs obligatoires (avec une asterisque rouge)',
    'BAZ_FORM_INVALID_EMAIL' => 'L\'email saisi n\'est pas valide',
    'BAZ_FORM_INVALID_URL' => 'L\'url saisie n\'est pas valide, elle doit commencer par http:// '.
        'et ne pas contenir d\'espaces ou caracteres speciaux',
    'BAZ_FORM_EMPTY_RADIO' => 'Il faut choisir une valeur de bouton radio',
    'BAZ_FORM_EMPTY_CHECKBOX' => 'Il faut cocher au moins une case a cocher',
    'BAZ_FORM_EMPTY_AUTOCOMPLETE' => 'Il faut saisir au moins une entrée pour le champs en autocomplétion',
    'BAZ_FORM_EMPTY_GEOLOC' => 'Vous devez géolocaliser l\'adresse',
    'BAZ_DATESHORT_MONDAY' => 'Lun',
    'BAZ_DATESHORT_TUESDAY' => 'Mar',
    'BAZ_DATESHORT_WEDNESDAY' => 'Mer',
    'BAZ_DATESHORT_THURSDAY' => 'Jeu',
    'BAZ_DATESHORT_FRIDAY' => 'Ven',
    'BAZ_DATESHORT_SATURDAY' => 'Sam',
    'BAZ_DATESHORT_SUNDAY' => 'Dim',
    'BAZ_DATEMIN_MONDAY' => 'L',
    'BAZ_DATEMIN_TUESDAY' => 'Ma',
    'BAZ_DATEMIN_WEDNESDAY' => 'Me',
    'BAZ_DATEMIN_THURSDAY' => 'J',
    'BAZ_DATEMIN_FRIDAY' => 'V',
    'BAZ_DATEMIN_SATURDAY' => 'S',
    'BAZ_DATEMIN_SUNDAY' => 'D',
    'BAZ_DATESHORT_JANUARY' => 'Jan',
    'BAZ_DATESHORT_FEBRUARY' => 'Féb',
    'BAZ_DATESHORT_MARCH' => 'Mar',
    'BAZ_DATESHORT_APRIL' => 'Avr',
    'BAZ_DATESHORT_MAY' => 'Mai',
    'BAZ_DATESHORT_JUNE' => 'Jui',
    'BAZ_DATESHORT_JULY' => 'Jul',
    'BAZ_DATESHORT_AUGUST' => 'Aoû',
    'BAZ_DATESHORT_SEPTEMBER' => 'Sep',
    'BAZ_DATESHORT_OCTOBER' => 'Oct',
    'BAZ_DATESHORT_NOVEMBER' => 'Nov',
    'BAZ_DATESHORT_DECEMBER' => 'Déc',
    'BAZ_SAVING' => 'En cours d\'enregistrement',

    // presentation/javascripts/components/BazarMap.js
    'BAZ_FULLSCREEN' => 'Mode plein écran',
    'BAZ_BACK_TO_NORMAL_VIEW' => 'Retour à la vue normale',

    // form-edit-template.js
    'MEMBER_OF_GROUP' => 'Membre du groupe {groupName}',
    'BAZ_FORM_EDIT_HELP' => 'Texte d\'aide',
    'BAZ_FORM_EDIT_HIDE' => 'Editer/Masquer',
    'BAZ_FORM_EDIT_MAX_LENGTH' => 'Longueur Max.',
    'BAZ_FORM_EDIT_NB_CHARS' => 'Nbre Caractères Visibles',
    'BAZ_FORM_EDIT_MIN_VAL' => 'Valeur min',
    'BAZ_FORM_EDIT_MAX_VAL' => 'Valeur max',
    'BAZ_FORM_EDIT_OWNER_AND_ADMINS' => 'Propriétaire de la fiche et admins',
    'BAZ_FORM_EDIT_USER' => 'Utilisateur (lorsqu\'on créé un utilisateur en même temps que la fiche)',
    'BAZ_FORM_EDIT_CAN_BE_READ_BY' => 'Peut être lu par',
    'BAZ_FORM_EDIT_CAN_BE_WRITTEN_BY' => 'Peut être saisi par',
    'BAZ_FORM_EDIT_QUERIES_LABEL' => 'Critères de filtre',
    'BAZ_FORM_EDIT_SEARCH_LABEL' => 'Présence dans le moteur de recherche',
    'BAZ_FORM_EDIT_SEMANTIC_LABEL' => 'Type sémantique du champ',
    'BAZ_FORM_EDIT_SELECT_SUBTYPE2_LABEL' => 'Origine des données',
    'BAZ_FORM_EDIT_SELECT_SUBTYPE2_LIST' => 'Une liste',
    'BAZ_FORM_EDIT_SELECT_SUBTYPE2_FORM' => 'Un Formulaire Bazar',
    'BAZ_FORM_EDIT_SELECT_LIST_FORM_ID' => 'Choix de la liste/du formulaire',
    'BAZ_FORM_EDIT_SELECT_DEFAULT' => 'Valeur par défaut',
    'BAZ_FORM_EDIT_TEXT_LABEL' => 'Texte court',
    'BAZ_FORM_EDIT_TEXTAREA_LABEL' => 'Zone de texte',
    'BAZ_FORM_EDIT_URL_LABEL' => 'Url',
    'BAZ_FORM_EDIT_GEO_LABEL' => 'Géolocalisation de l\'adresse',
    'BAZ_FORM_EDIT_IMAGE_LABEL' => 'Image',
    'BAZ_FORM_EDIT_EMAIL_LABEL' => 'Email',
    'BAZ_FORM_EDIT_EMAIL_REPLACE_BY_BUTTON_LABEL' => 'Remplacer l\'email par un bouton contact',
    'BAZ_FORM_EDIT_EMAIL_SEND_FORM_CONTENT_LABEL' => 'Envoyer le contenu de la fiche à cet email',
    'BAZ_FORM_EDIT_TAGS_LABEL' => 'Mots clés',
    'BAZ_FORM_EDIT_SUBSCRIBE_LIST_LABEL' => 'Inscription Liste Diffusion',
    'BAZ_FORM_EDIT_CUSTOM_HTML_LABEL' => 'Custom HTML',
    'BAZ_FORM_EDIT_ACL_LABEL' => 'Config Droits d\'accès',
    'BAZ_FORM_EDIT_METADATA_LABEL' => 'Config Thème de la fiche',
    'BAZ_FORM_EDIT_LINKEDENTRIES_LABEL' => 'Liste des fiches liées',
    'BAZ_FORM_EDIT_USERS_WIKINI_LABEL' => 'Créer un utilisateur lorsque la fiche est validée',
    'BAZ_FORM_EDIT_USERS_WIKINI_NAME_FIELD_LABEL' => 'Champ pour le nom d\'utilisateur',
    'BAZ_FORM_EDIT_USERS_WIKINI_EMAIL_FIELD_LABEL' => 'Champ pour l\'email de l\'utilisateur',
    'BAZ_FORM_EDIT_USERS_WIKINI_AUTOUPDATE_MAIL' => 'Auto. Synchro. e-mail',
    'BAZ_FORM_EDIT_ACL_READ_LABEL' => 'Peut voir la fiche',
    'BAZ_FORM_EDIT_ACL_WRITE_LABEL' => 'Peut éditer la fiche',
    'BAZ_FORM_EDIT_ACL_COMMENT_LABEL' => 'Peut commenter la fiche',
    'BAZ_FORM_EDIT_DATE_TODAY_BUTTON' => 'Initialiser à Aujourd\'hui',
    'BAZ_FORM_EDIT_EMAIL_BUTTON' => 'Remplacer l\'email par un bouton contact',
    'BAZ_FORM_EDIT_EMAIL_SEND_CONTENT' => 'Envoyer le contenu de la fiche à cet email',
    'BAZ_FORM_EDIT_IMAGE_ALIGN_LABEL' => 'Alignement',
    'BAZ_FORM_EDIT_IMAGE_WIDTH' => 'Hauteur Vignette',
    'BAZ_FORM_EDIT_IMAGE_WIDTH' => 'Largeur Vignette',
    'BAZ_FORM_EDIT_IMAGE_WIDTH_RESIZE' => 'Largeur redimension',
    'BAZ_FORM_EDIT_IMAGE_HEIGHT_RESIZE' => 'Hauteur redimension',
    'BAZ_FORM_EDIT_MAP_LATITUDE' => 'Nom champ latitude',
    'BAZ_FORM_EDIT_MAP_LONGITUDE' => 'Nom champ longitude',
    'BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_POSTALCODE' => 'Champ code postal pour l\'autocomplétion',
    'BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_POSTALCODE_PLACEHOLDER' => 'Laisser vide pour ne pas activer l\'autocomplétion',
    'BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_TOWN' => 'Champ ville pour l\'autocomplétion',
    'BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_TOWNE_PLACEHOLDER' => 'Laisser vide pour ne pas activer l\'autocomplétion',
    'BAZ_FORM_EDIT_METADATA_THEME_LABEL' => 'Nom du thème',
    'BAZ_FORM_EDIT_METADATA_SQUELETON_LABEL' => 'Squelette',
    'BAZ_FORM_EDIT_METADATA_STYLE_LABEL' => 'Style',
    'BAZ_FORM_EDIT_METADATA_PRESET_LABEL' => 'Preset',
    'BAZ_FORM_EDIT_METADATA_PRESET_PLACEHOLDER' => 'thème margot uniquement',
    'BAZ_FORM_EDIT_METADATA_BACKGROUND_IMAGE_LABEL' => 'Image de fond',
    'BAZ_FORM_EDIT_TEXT_MAX_LENGTH' => 'Longueur max',
    'BAZ_FORM_EDIT_TEXT_SIZE' => 'Nb caractères visibles',
    'BAZ_FORM_EDIT_TEXT_PATTERN' => 'Motif',
    'BAZ_FORM_EDIT_TEXT_TYPE_LABEL' => 'Type',
    'BAZ_FORM_EDIT_TEXT_TYPE_TEXT' => 'Texte',
    'BAZ_FORM_EDIT_TEXT_TYPE_NUMBER' => 'Nombre',
    'BAZ_FORM_EDIT_TEXT_TYPE_RANGE' => 'Slider',
    'BAZ_FORM_EDIT_TEXT_TYPE_URL' => 'Adresse url',
    'BAZ_FORM_EDIT_TEXT_TYPE_PASSWORD' => 'Mot de passe',
    'BAZ_FORM_EDIT_TEXT_TYPE_COLOR' => 'Couleur',
    'BAZ_FORM_EDIT_TITLE_LABEL' => 'Titre Automatique',
    'BAZ_FORM_EDIT_CUSTOM_LABEL' => 'Custom',
    'BAZ_FORM_EDIT_MAP_FIELD' => 'Geolocation à partir d\'un champ bf_adresse et/ou bf_ville et/ou bf_code_postal et/ou bf_pays',
    'BAZ_FORM_EDIT_COLLABORATIVE_DOC_FIELD' => 'Document collaboratif',
    'BAZ_FORM_EDIT_TABS' => 'Navigation par onglets',
    'BAZ_FORM_EDIT_TABCHANGE' => 'Passage à l\'onglet suivant',
    'BAZ_FORM_EDIT_TABS_TITLES_LABEL' => 'Titres des onglets',
    'BAZ_FORM_EDIT_TABS_FOR_FORM' => 'pour le formulaire',
    'BAZ_FORM_EDIT_TABS_FOR_ENTRY' => 'pour la fiche',
    'BAZ_FORM_EDIT_TABS_FORMTITLES_VALUE' => 'Onglet 1,Onglet 2,Onglet 3',
    'BAZ_FORM_EDIT_TABS_FORMTITLES_DESCRIPTION' => 'Séparer chaque titre par \',\'. Laisser vide pour ne pas avoir d\'onglets dans le formulaire',
    'BAZ_FORM_EDIT_TABS_VIEWTITLES_DESCRIPTION' => 'Séparer chaque titre par \',\'. Laisser vide pour ne pas avoir d\'onglets dans la fiche',
    'BAZ_FORM_EDIT_TABS_MOVESUBMITBUTTONTOLASTTAB_LABEL' => 'Déplacer le bouton \'Valider\'',
    'BAZ_FORM_EDIT_TABS_MOVESUBMITBUTTONTOLASTTAB_DESCRIPTION' => 'Déplacer le bouton \'Valider\' dans le dernier onglet',
    'BAZ_FORM_EDIT_TABS_BTNCOLOR_LABEL' => 'Couleur des boutons',
    'BAZ_FORM_EDIT_TABS_BTNSIZE_LABEL' => 'Taille des boutons',
    'BAZ_FORM_EDIT_TABCHANGE_CHANGE_LABEL' => 'Changement d\'onglet',
    'NORMAL_F' => 'Normale',
    'SMALL_F' => 'Petite',
    'PRIMARY' => 'Primaire',
    'SECONDARY' => 'Secondaire',
    'BAZ_FORM_TABS_HINT' => 'Pour utiliser les onglets, il vous faut deux champs : {\\n}'.
        ' - le champ "{tabs-field-label}" dans lequel spécifier le nom des onglets séparés par des "," {\\n}'.
        ' - le champ "{tabchange-field-label}" à intégrer à la fin de chaque onglet ainsi qu\'à la fin de votre formulaire',
    'BAZ_FORM_EDIT_ADD_TO_GROUP_LABEL' => 'Groupes où ajouter l\'utilisateur',
    'BAZ_FORM_EDIT_ADD_TO_GROUP_DESCRIPTION' => 'Groupes où ajouter l\'utilisateur, séparés par \',\'',
    'BAZ_FORM_EDIT_ADD_TO_GROUP_HELP' => 'Groupes où ajouter l\'utilisateur, séparés par \',\', peut être le nom d\'un champ. Ex: @groupName,bf_name,@groupName2',
    'BAZ_FORM_EDIT_ADVANCED_MODE' => 'Mode avancé.',
    'BAZ_FORM_EDIT_FILLING_MODE_LABEL' => 'Mode de saisie',
    'BAZ_FORM_EDIT_FILLING_MODE_NORMAL' => 'Normal',
    'BAZ_FORM_EDIT_FILLING_MODE_TAGS' => 'En Tags',
    'BAZ_FORM_EDIT_FILLING_MODE_DRAG_AND_DROP' => 'Drag & drop',
    'BAZ_FORM_EDIT_TEXTAREA_SYNTAX_LABEL' => 'Format d\'écriture',
    'BAZ_FORM_EDIT_TEXTAREA_SYNTAX_HTML' => 'Editeur Wysiwyg',
    'BAZ_FORM_EDIT_TEXTAREA_SYNTAX_NOHTML' => 'Texte non interprété',
    'BAZ_FORM_EDIT_TEXTAREA_SIZE_LABEL' => 'Largeur champ de saisie',
    'BAZ_FORM_EDIT_TEXTAREA_ROWS_LABEL' => 'Nombre de lignes',
    'BAZ_FORM_EDIT_TEXTAREA_ROWS_PLACEHOLDER' => 'Défaut vide = 3 lignes',
    'BAZ_FORM_EDIT_FILE_MAXSIZE_LABEL' => 'Taille max',
    'BAZ_FORM_EDIT_INSCRIPTIONLISTE_EMAIL_LABEL' => 'Email pour s\'inscrire',
    'BAZ_FORM_EDIT_INSCRIPTIONLISTE_EMAIL_FIELDID' => 'Champ du formulaire fournissant l\'email à inscire',
    'BAZ_FORM_EDIT_INSCRIPTIONLISTE_MAILINGLIST' => 'Type de service de diffusion',
    'BAZ_FORM_EDIT_CUSTOM_HTML_LABEL' => 'Custom HTML',
    'BAZ_FORM_EDIT_EDIT_CONTENT_LABEL' => 'Contenu lors de la saisie',
    'BAZ_FORM_EDIT_VIEW_CONTENT_LABEL' => 'Contenu lors de l\'affichage d\'une fiche',
    'BAZ_FORM_EDIT_LISTEFICHES_FORMID_LABEL' => 'id du formulaire lié',
    'BAZ_FORM_EDIT_LISTEFICHES_QUERY_LABEL' => 'Query',
    'BAZ_FORM_EDIT_LISTEFICHES_QUERY_PLACEHOLDER' => 'Voir doc sur {url}',
    'BAZ_FORM_EDIT_LISTEFICHES_PARAMS_LABEL' => 'Params de l\'action',
    'BAZ_FORM_EDIT_LISTEFICHES_NUMBER_LABEL' => 'Nombre de fiches à afficher',
    'BAZ_FORM_EDIT_LISTEFICHES_TEMPLATE_LABEL' => 'Template de restitution',
    'BAZ_FORM_EDIT_LISTEFICHES_TEMPLATE_PLACEHOLDER' => 'Exple: template="liste_liens.tpl.html (par défault = accordéon)"',
    'BAZ_FORM_EDIT_LISTEFICHES_LISTTYPE_LABEL' => 'Type de fiche liée (ou label du champ)',
    'BAZ_FORM_EDIT_LISTEFICHES_LISTTYPE_PLACEHOLDER' => 'mettre \'checkbox\' ici si vos fiches liées le sont via un checkbox',
    'BAZ_FORM_EDIT_ADDRESS' => 'Adresse',

    'BAZ_FORM_EDIT_UNIQUE_ID' => 'Identifiant unique',
    'BAZ_FORM_EDIT_NAME' => 'Intitulé',
    'BAZ_FORM_EDIT_CONFIRM_DISPLAY_FORMBUILDER' => 'En affichant le constructeur graphique, vous perdrez vos modifications faites dans le code ici-même. Continuer sans sauvegarder les changements ? (Cliquez sur le bouton "Valider" en bas de page pour conserver vos modifications !)',

    'BAZ_FORM_EDIT_COMMENTS_CLOSED' => 'Commentaires fermés',
    'BAZ_FORM_EDIT_BOOKMARKLET_URLFIELD_LABEL' => "Champ url associé",
    'BAZ_FORM_EDIT_BOOKMARKLET_DESCRIPTIONFIELD_LABEL' => "Champ texte long associé",
    'BAZ_BOOKMARKLET_HINT' => "Ce champ nécessite deux autres champs pour fonctionner :{\\n}".
        "  - un champ url (par défaut 'bf_url'){\\n}".
        "  - un champ texte long (par défaut 'bf_description')",

    // condition checking field
    "BAZ_FORM_CONDITIONSCHEKING_HINT" => "La condition doit respecter le format suivant (sans les `):{\\n}".
        " - ` and ` : donne ET{\\n}".
        " - ` or ` : donne OU{\\n}".
        " - `==` : donne EST ÉGALE À{\\n}".
        " - `!=` : donne EST DIFFÉRENT DE {\\n}".
        " - ` in [value1,value2]` : FAIT PARTIE DE liste d'éléments séparés par des virgules et entouré de crochets {\\n}".
        " - `|length > 6` : vérifie si le nombre d'éléments cochés est supérieur à 6 (fonctionne avec '>=','<','<=') {\\n}".
        " - ` == [value1,value2]` : VAUT EXACTEMENT LA liste d'éléments séparés par des virgules et entouré de crohets (uniquement pour checkbox){\\n}".
        " - `(  )` permet de grouper des conditions sinon priorité de gauche à droite{\\n}".
        " - `!(  )` ou `not (  )` négation de la condition{\\n}".
        " - indiquer à gauche d'un `==` ou `!=` le label du champ ex:`bf_thematique` ou le nom long `listeListeOuiNonbf_choix`{\\n}".
        " - ` is empty ` : permet de vérifier si la valeur est vide{\\n}".
        " - ` is not empty ` : permet de vérifier si la valeur n'est pas vide{\\n}".
        " - les espaces en trop sont retirés automatiquement{\\n}".
        " - les opérations sont normalement insensibles à la casse",
    "BAZ_FORM_EDIT_CONDITIONCHECKING_LABEL" => "Affichage conditionnel",
    "BAZ_FORM_EDIT_CONDITIONS_CHECKING_LABEL" => "Condition",
    "BAZ_FORM_EDIT_CONDITIONS_CHECKING_END" => "Fin de condition",
    "BAZ_FORM_EDIT_CONDITIONS_CHECKING_CLEAN_LABEL" => "Effacer au masquage",
    "BAZ_FORM_EDIT_CONDITIONS_CHECKING_CLEAN_OPTION" => "Effacer",
    "BAZ_FORM_EDIT_CONDITIONS_CHECKING_NOCLEAN_OPTION" => "Ne pas effacer",
    "BAZ_FORM_CONDITIONSCHEKING_NOCLEAN_HINT" => "Pour effacer ou non le contenu de ce qui est masqué",

    // templates/entries/index-dynamic-temapltes/BazarCalendar_ButtonICS.js
    'BAZ_CALENDAR_EXPORT_BUTTON_TITLE' => "Ajouter à votre calendrier",
];
