<?php

return [
    // commons
    'APRIL' => 'Avril',
    'AUGUST' => 'Août',
    'CANCEL' => 'Annuler',
    'COPY' => 'Copier',
    'DECEMBER' => 'Décembre',
    'DELETE_ALL_SELECTED_ITEMS_QUESTION' => 'Êtes-vous sûr de vouloir supprimer tous les éléments sélectionnées ?',
    'DUPLICATE' => 'Dupliquer',
    'EVERYONE' => 'Tout le monde',
    'FEBRUARY' => 'Février',
    'FIRST' => 'Premier',
    'FRIDAY' => 'Vendredi',
    'IDENTIFIED_USERS' => 'Utilisateurs identifiés',
    'JANUARY' => 'Janvier',
    'JULY' => 'Juillet',
    'JUNE' => 'Juin',
    'LAST' => 'Dernier',
    'LEFT' => 'Gauche',
    'MARCH' => 'Mars',
    'MAY' => 'Mai',
    'MODIFY' => 'Modifier',
    'MONDAY' => 'Lundi',
    'NEXT' => 'Suivant',
    'NO' => 'Non',
    'NOVEMBER' => 'Novembre',
    'PREVIOUS' => 'Précédent',
    'PRINT' => 'Imprimer',
    'OCTOBER' => 'Octobre',
    'RIGHT' => 'Droite',
    'SATURDAY' => 'Samedi',
    'SAVE' => 'Enregistrer',
    'SEPTEMBER' => 'Septembre',
    'SUNDAY' => 'Dimanche',
    'THURSDAY' => 'Jeudi',
    'TUESDAY' => 'Mardi',
    'WEDNESDAY' => 'Mercredi',
    'YES' => 'Oui',

    // /javascripts/actions/admin-backups.js
    'ADMIN_BACKUPS_LOADING_LIST' => 'Chargement de la liste des sauvegardes',
    'ADMIN_BACKUPS_NOT_POSSIBLE_TO_LOAD_LIST' => 'Impossible de mettre à jour la liste des sauvegardes',
    'ADMIN_BACKUPS_DELETE_ARCHIVE' => 'Suppression de {filename}',
    'ADMIN_BACKUPS_DELETE_ARCHIVE_POSSIBLE_ERROR' => 'Une erreur pourrait avoir eu lieu en supprimant {filename}',
    'ADMIN_BACKUPS_DELETE_ARCHIVE_SUCCESS' => 'Suppression réussie de {filename}',
    'ADMIN_BACKUPS_DELETE_ARCHIVE_ERROR' => 'Suppression impossible de {filename}',
    'ADMIN_BACKUPS_NO_ARCHIVE_TO_DELETE' => 'Aucune sauvegarde à supprimer',
    'ADMIN_BACKUPS_DELETE_SELECTED_ARCHIVES' => 'Suppression des sauvegardes sélectionnées',
    'ADMIN_BACKUPS_RESTORE_ARCHIVE' => 'Restauration de {filename}',
    'ADMIN_BACKUPS_RESTORE_ARCHIVE_POSSIBLE_ERROR' => 'Une erreur pourrait avoir eu lieu en restraurant {filename}',
    'ADMIN_BACKUPS_RESTORE_ARCHIVE_SUCCESS' => 'Restauration réussie de {filename}',
    'ADMIN_BACKUPS_RESTORE_ARCHIVE_ERROR' => 'Restauration impossible de {filename}',
    'ADMIN_BACKUPS_START_BACKUP' => "Lancement d'une sauvegarde",
    'ADMIN_BACKUPS_START_BACKUP_SYNC' => "Lancement d'une sauvegarde en direct (moins stable)\n" .
        "Il ne sera pas possible de mettre à jour le statut en direct\n" .
        'Ne pas fermer, ni rafraîchir cette fenêtre !',
    'ADMIN_BACKUPS_STARTED' => 'Sauvegarde lancée',
    'ADMIN_BACKUPS_START_BACKUP_ERROR' => 'Lancement de la sauvegarde impossible',
    'ADMIN_BACKUPS_UPDATE_UID_STATUS_ERROR' => 'Impossible de mettre à jour le statut de la sauvegarde',
    'ADMIN_BACKUPS_UID_STATUS_NOT_FOUND' => "Les informations de suivi n'ont pas été trouvées",
    'ADMIN_BACKUPS_UID_STATUS_RUNNING' => 'Sauvegarde en cours',
    'ADMIN_BACKUPS_UID_STATUS_FINISHED' => 'Sauvegarde terminée',
    'ADMIN_BACKUPS_UID_STATUS_NOT_FINISHED' => "Il y a un problème car la sauvegarde n'est plus en cours et elle n'est pas terminée !",
    'ADMIN_BACKUPS_UID_STATUS_STOP' => 'Sauvegarde arrêtée',
    'ADMIN_BACKUPS_STOP_BACKUP_ERROR' => "Erreur : impossible d'arrêter la sauvegarde",
    'ADMIN_BACKUPS_STOPPING_ARCHIVE' => 'Arrêt en cours de la sauvegarde',
    'ADMIN_BACKUPS_CONFIRMATION_TO_DELETE' => "Les fichiers suivants seront supprimés par la sauvegarde.\n" .
        "Veuillez confirmer leur suppression en cochant la case ci-dessous.\n<pre>{files}</pre>",
    'ADMIN_BACKUPS_START_BACKUP_ERROR_ARCHIVING' => "Lancement de la sauvegarde impossible car une sauvegarde semble être déjà en cours.\n" .
        'Suivez <a href="{helpBaseUrl}#/docs/fr/admin?id=résoudre-les-problèmes-de-sauvegarde" title="Aide pour relancer une sauvegarde" target="blank">cette aide</a> pour corriger ceci.',
    'ADMIN_BACKUPS_START_BACKUP_ERROR_HIBERNATE' => "Lancement de la sauvegarde impossible car le site est en hibernation.\n" .
        "Suivez <a href=\"{helpBaseUrl}#/docs/fr/admin?id=résoudre-les-problèmes-de-sauvegarde\" title=\"Aide pour sortir d'hibernation\" target=\"blank\">cette aide</a> pour en sortir.",
    'ADMIN_BACKUPS_START_BACKUP_PATH_NOT_WRITABLE' => "Lancement de la sauvegarde impossible car le dossier de sauvegarde n'est pas accessible en écriture.\n" .
        'Suivez <a href="{helpBaseUrl}#/docs/fr/admin?id=résoudre-les-problèmes-de-sauvegarde" title="Aide pour configurer le dossier de sauvegarde" target="blank">cette aide</a> pour configurer le dossier de sauvegarde.',
    'ADMIN_BACKUPS_FORCED_UPDATE_NOT_POSSIBLE' => 'Mise à jour forcée impossible',
    'ADMIN_BACKUPS_UID_STATUS_FINISHED_THEN_UPDATING' => 'Mise à jour lancée (veuillez patienter)',
    'ADMIN_BACKUPS_START_BACKUP_CANNOT_EXEC' => "Lancement de la sauvegarde impossible \n" .
        "Car il n'est pas possible de lancer des commandes console sur le serveur " .
        '(<a href="{helpBaseUrl}#/docs/fr/admin?id=résoudre-les-problèmes-de-sauvegarde" title="Aide sur la commande console" target="blank">voir aide</a>).',
    'ADMIN_BACKUPS_START_BACKUP_FOLDER_AVAILABLE' => "Lancement de la sauvegarde impossible car le dossier de sauvegarde est accessible sur internet.\n" .
        "Suivez <a href=\"{helpBaseUrl}?#/docs/fr/admin?id=résoudre-les-problèmes-de-sauvegarde\" title=\"Aide pour restreindre l'accès au dossier de sauvegarde\" target=\"blank\">cette aide</a> pour restreindre l'accès au dossier de sauvegarde.",
    'ADMIN_BACKUPS_START_BACKUP_NOT_ENOUGH_SPACE' => "Lancement de la sauvegarde impossible \n" .
        "Il n'y a plus assez d'espace disque disponible pour une nouvelle sauvegarde.",
    'ADMIN_BACKUPS_START_BACKUP_NOT_DB' => "Lancement de la sauvegarde non optimal \n" .
        "L'utilitaire d'export de base de données ('mysqldump') n'est pas accessible (<a href=\"{helpBaseUrl}#/docs/fr/admin?id=résoudre-les-problèmes-de-sauvegarde\" title=\"Aide sur mysqldump\" target=\"blank\">voir aide</a>).",

    // /javascripts/handlers/revisions.js
    'REVISIONS_COMMIT_DIFF' => 'Modifs apportées par cette version',
    'REVISIONS_DIFF' => 'Comparaison avec version actuelle',
    'REVISIONS_PREVIEW' => 'Aperçu de cette version',

    // javascripts/documentation.js
    'DOCUMENTATION_TITLE' => 'Documentation YesWiki',

    // javascripts/favorites.js
    'FAVORITES_ADD' => 'Ajouter aux favoris',
    'FAVORITES_ALL_DELETED' => 'Favoris supprimés',
    'FAVORITES_ERROR' => 'Une erreur est survenue : {error}',
    'FAVORITES_REMOVE' => 'Retirer des favoris',
    'FAVORITES_ADDED' => 'Favori ajouté',
    'FAVORITES_REMOVED' => 'Favori supprimé',

    // javascripts/list-form-id-attribute.js
    'LIST_CREATE_TITLE' => 'Nouvelle Liste',
    'LIST_UPDATE_TITLE' => 'Modifier la Liste',
    'LIST_CREATED' => 'La liste a bien été crée',
    'LIST_UPDATED' => 'La liste a été mise à jour avec succès',

    // javascripts/multidelete.js
    'MULTIDELETE_END' => 'Suppressions réalisées',
    'MULTIDELETE_ERROR' => "L'élément {itemId} n'a pas été supprimé ! {error}",

    // javascripts/users-table.js
    'LINK_TO_CHANGE_PASSWORD' => "Lien pour changer le mot de passe",
    'USERSTABLE_USER_CREATED' => "Utilisateur '{name}' créé",
    'USERSTABLE_USER_NOT_CREATED' => "Utilisateur '{name}' non créé : {error}",
    'USERSTABLE_USER_DELETED' => 'L\'utilisateur "{username}" a été supprimé.',
    'USERSTABLE_USER_NOT_DELETED' => 'L\'utilisateur "{username}" n\'a pas été supprimé.',

    // /javascripts/yeswiki-base.js
    'DATATABLES_PROCESSING' => 'Traitement en cours...',
    'DATATABLES_SEARCH' => 'Rechercher&nbsp;:',
    'DATATABLES_LENGTHMENU' => 'Afficher _MENU_ &eacute;l&eacute;ments',
    'DATATABLES_INFO' => "Affichage de l'&eacute;l&eacute;ment _START_ &agrave; _END_ sur _TOTAL_ &eacute;l&eacute;ments",
    'DATATABLES_INFOEMPTY' => "Affichage de l'&eacute;l&eacute;ment 0 &agrave; 0 sur 0 &eacute;l&eacute;ment",
    'DATATABLES_INFOFILTERED' => '(filtr&eacute; de _MAX_ &eacute;l&eacute;ments au total)',
    'DATATABLES_LOADINGRECORDS' => 'Chargement en cours...',
    'DATATABLES_ZERORECORD' => 'Aucun &eacute;l&eacute;ment &agrave; afficher',
    'DATATABLES_EMPTYTABLE' => 'Aucune donn&eacute;e disponible dans le tableau',
    'DATATABLES_SORTASCENDING' => ': activer pour trier la colonne par ordre croissant',
    'DATATABLES_SORTDESCENDING' => ': activer pour trier la colonne par ordre d&eacute;croissant',
    'DATATABLES_COLS_TO_DISPLAY' => 'Colonnes à afficher',
    'DELETE_COMMENT_AND_ANSWERS' => 'Supprimer ce commentaire et les réponses associées ?',

    'NAVBAR_EDIT_MESSAGE' => 'Editer une zone du menu horizontal',

    'YESWIKIMODAL_EDIT_MSG' => 'Éditer la page',
    'EDIT_OUPS_MSG' => 'En fait, je ne voulais pas double-cliquer...',

    'COMMENT_NOT_DELETED' => 'Le commentaire {comment} n\'a pas été supprimé.',
    'COMMENT_DELETED' => 'Commentaire supprimé.',

    // reactions
    'REACTION_NOT_POSSIBLE_TO_ADD_REACTION' => "Impossible d'ajouter la réaction en raison de l'erreur suivante : {error}!",
    'REACTION_NOT_POSSIBLE_TO_DELETE_REACTION' => "Impossible de supprimer la réaction en raison de l'erreur suivante : {error}!",
    'REACTION_CONFIRM_DELETE' => 'Etes-vous sur de vouloir supprimer cette réaction ?',
    'REACTION_CONFIRM_DELETE_ALL' => 'Etes-vous sur de vouloir supprimer toutes les réactions de ce vote ?',

    // Doc
    'DOC_EDIT_THIS_PAGE_ON_GITHUB' => 'Modifier cette page sur Github',
];
