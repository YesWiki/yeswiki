<?php

return [
    // commons
    "APRIL" => "April",
    "AUGUST" => "August",
    "CANCEL" => "Cancel",
    "COPY" => "Copy",
    "DECEMBER" => "December",
    "DELETE_ALL_SELECTED_ITEMS_QUESTION" => "Do you confirm the deletion of all selected items?",
    "DUPLICATE" => "Duplicate",
    "EVERYONE" => "Everyone",
    "FEBRUARY" => "February",
    "FIRST" => "First",
    "FRIDAY" => "Friday",
    "IDENTIFIED_USERS" => "Identified users",
    "JANUARY" => "January",
    "JULY" => "July",
    "JUNE" => "June",
    "LAST" => "Last",
    "LEFT" => "Left",
    'MARCH' => 'March',
    'MAY' => 'May',
    'MODIFY' => 'Modify',
    'MONDAY' => 'Monday',
    "NEXT" => "Next",
    "NO" => "No",
    "NOVEMBER" => "November",
    "PREVIOUS" => "Previous",
    "PRINT" => "Print",
    "OCTOBER" => "October",
    "RIGHT" => "Right",
    'SATURDAY' => 'Saturday',
    'SEPTEMBER' => 'September',
    'SUNDAY' => 'Sunday',
    'THURSDAY' => 'Thursday',
    'TUESDAY' => 'Tuesday',
    'WEDNESDAY' => 'Wednesday',
    "YES" => "Yes",

    // /javascripts/actions/admin-backups.js
    "ADMIN_BACKUPS_LOADING_LIST" => "Loading list of backups",
    "ADMIN_BACKUPS_NOT_POSSIBLE_TO_LOAD_LIST" => "Not possible to update list of backups",
    "ADMIN_BACKUPS_DELETE_ARCHIVE" => "Delete {filename}",
    "ADMIN_BACKUPS_DELETE_ARCHIVE_POSSIBLE_ERROR" => "An error could occur when deleting {filename}",
    "ADMIN_BACKUPS_DELETE_ARCHIVE_SUCCESS" => "{filename} successfully deleted",
    "ADMIN_BACKUPS_DELETE_ARCHIVE_ERROR" => "Not possible to delete {filename}",
    "ADMIN_BACKUPS_NO_ARCHIVE_TO_DELETE" => "No backup to delete",
    "ADMIN_BACKUPS_DELETE_SELECTED_ARCHIVES" => "Deleting selected backups",
    "ADMIN_BACKUPS_RESTORE_ARCHIVE" => "Restore {filename}",
    "ADMIN_BACKUPS_RESTORE_ARCHIVE_POSSIBLE_ERROR" => "An error could occur when restoring {filename}",
    "ADMIN_BACKUPS_RESTORE_ARCHIVE_SUCCESS" => "{filename} successfully restored",
    "ADMIN_BACKUPS_RESTORE_ARCHIVE_ERROR" => "Not possible to restore {filename}",
    "ADMIN_BACKUPS_START_BACKUP" => "Start a backup",
    "ADMIN_BACKUPS_START_BACKUP_SYNC" => "Start directly a backup (less stable)\n".
        "It will not be possibile to update the status in direct.\n".
        "Do not close nor refresh the window !",
    "ADMIN_BACKUPS_STARTED" => "Backup started",
    "ADMIN_BACKUPS_START_BACKUP_ERROR" => "Not possible to start backup",
    "ADMIN_BACKUPS_UPDATE_UID_STATUS_ERROR" => "Not possible to update backup status",
    "ADMIN_BACKUPS_UID_STATUS_NOT_FOUND" => "Data about backup was not found",
    "ADMIN_BACKUPS_UID_STATUS_RUNNING" => "Running backup",
    "ADMIN_BACKUPS_UID_STATUS_FINISHED" => "Finished backup",
    "ADMIN_BACKUPS_UID_STATUS_NOT_FINISHED" => "Trouble backup is not running but not finished",
    "ADMIN_BACKUPS_UID_STATUS_STOP" => "Backup aborted",
    "ADMIN_BACKUPS_STOP_BACKUP_ERROR" => "Error : not possible to stop backup",
    "ADMIN_BACKUPS_STOPPING_ARCHIVE" => "Backup stopping",
    "ADMIN_BACKUPS_CONFIRMATION_TO_DELETE" => "Following files will be deleted by the backup.\n".
        "Could you confirm their deletion by checking the box below.\n<pre>{files}</pre>",
    "ADMIN_BACKUPS_START_BACKUP_ERROR_ARCHIVING" => "Not possible to start backup because a backup is currently in course.\n".
        "Follow <a href=\"{helpBaseUrl}#/docs/users/fr/admin?id=erreur-de-sauvegarde-sauvegarde-déjà-en-cours\" title=\"Help to restart a backup\" target=\"blank\">this help</a> to fix that.",
    "ADMIN_BACKUPS_START_BACKUP_ERROR_HIBERNATE" => "Not possible to start backup because the website is hibernated.\n".
        "Follow <a href=\"{helpBaseUrl}#/docs/users/fr/admin?id=erreur-de-sauvegarde-site-en-hibernation\" title=\"Help to go out hibernation\" target=\"blank\">this help</a> to go out.",
    "ADMIN_BACKUPS_START_BACKUP_PATH_NOT_WRITABLE" => "Not possible to start backup because the backups' folder is not writable.\n".
        "Follow <a href=\"{helpBaseUrl}#/docs/users/fr/admin?id=erreur-de-sauvegarde-dossier-de-sauvegarde-non-accessible-en-%c3%a9criture\" title=\"Help to configure backup folder\" target=\"blank\">this help</a> to configure backup folder.",
    "ADMIN_BACKUPS_UID_STATUS_FINISHED_THEN_UPDATING" => "Update started (please wait)",
    "ADMIN_BACKUPS_START_BACKUP_CANNOT_EXEC" => "Not possible to start backup \n" .
        "because it is not possible to launch console commands on this server ".
        "(<a href=\"{helpBaseUrl}#/docs/users/fr/admin?id=erreur-de-sauvegarde-lancement-impossible-des-commandes-console-sur-le-serveur\" title=\"See help on console\" target=\"blank\">see help</a>).",
    "ADMIN_BACKUPS_START_BACKUP_FOLDER_AVAILABLE" => "Not possible to start backup because backups folder is reachable on the internet.\n".
        "Follow <a href=\"{helpBaseUrl}#/docs/users/fr/admin?id=erreur-de-sauvegarde-dossier-de-sauvegarde-accessible-sur-internet\" title=\"Help to restrict access to backup folder\" target=\"blank\">this help</a> to restrict access to backup folder.",
    "ADMIN_BACKUPS_START_BACKUP_NOT_ENOUGH_SPACE" => "Not possible to start backup \n".
        "There is not enough free space available for a new backup.",
    "ADMIN_BACKUPS_START_BACKUP_NOT_DB" => "Not possible to start backup \n".
        "Database export program ('mysqldump') was not found (<a href=\"{helpBaseUrl}#/docs/users/fr/admin?id=erreur-de-sauvegarde-mysqldump-inaccessible\" title=\"Help on mysqldump\" target=\"blank\">see help</a>).",

    // /javascripts/handlers/revisions.js
    "REVISIONS_COMMIT_DIFF" => "Changes done by this revision",
    "REVISIONS_DIFF" => "Comparison to the current revision",
    "REVISIONS_PREVIEW" => "Preview of this revision",

    // javascripts/documentation.js
    "DOCUMENTATION_TITLE" => "YesWiki documentation",

    // javascripts/favorites.js
    'FAVORITES_ADD' => 'Add to favorites',
    'FAVORITES_ALL_DELETED' => 'Favorites deleted',
    'FAVORITES_ERROR' => 'An error occurred : {error}',
    'FAVORITES_REMOVE' => 'Remove from favorites',
    'FAVORITES_ADDED' => 'Favorite added',
    'FAVORITES_REMOVED' => 'Favorite deleted',

    // javascripts/multidelete.js
    "MULTIDELETE_END" => "Deletions finished",
    "MULTIDELETE_ERROR" => "Item {itemId} has not been deleted! {error}",

    // javascripts/users-table.js
    "USERSTABLE_USER_CREATED" => "User '{name}' created",
    "USERSTABLE_USER_NOT_CREATED" => "User '{name}' not created : {error}",
    'USERSTABLE_USER_DELETED' => 'The user "{username}" is deleted.',
    'USERSTABLE_USER_NOT_DELETED' => 'The user "{username}" is not deleted.',

    // /javascripts/yeswiki-base.js
    "DATATABLES_PROCESSING" => "Processing...",
    "DATATABLES_SEARCH" => "Search&nbsp;:",
    "DATATABLES_LENGTHMENU" => "Display _MENU_ elements",
    "DATATABLES_INFO" => "Display from element _START_ to _END_ on _TOTAL_ elements",
    // "DATATABLES_INFOEMPTY" => "Affichage de l'&eacute;l&eacute;ment 0 &agrave; 0 sur 0 &eacute;l&eacute;ment",
    // "DATATABLES_INFOFILTERED" => "(filtr&eacute; de _MAX_ &eacute;l&eacute;ments au total)",
    "DATATABLES_LOADINGRECORDS" => "Loading...",
    // "DATATABLES_ZERORECORD" => "Aucun &eacute;l&eacute;ment &agrave; afficher",
    // "DATATABLES_EMPTYTABLE" => "Aucune donn&eacute;e disponible dans le tableau",
    // "DATATABLES_SORTASCENDING" => ": activer pour trier la colonne par ordre croissant",
    // "DATATABLES_SORTDESCENDING" => ": activer pour trier la colonne par ordre d&eacute;croissant",
    "DATATABLES_COLS_TO_DISPLAY" => "Columns to display",

    "NAVBAR_EDIT_MESSAGE" => "Edit an area of the horizntal menu",

    "YESWIKIMODAL_EDIT_MSG" => "Edit the page",
    "EDIT_OUPS_MSG" => "INdeed, I would not double-click...",

    'COMMENT_NOT_DELETED' => 'Comment {comment} was not deleted.',
    'COMMENT_DELETED' => 'Comment deleted.',

    // Doc
    "DOC_EDIT_THIS_PAGE_ON_GITHUB" => "Edit this page on Github",
];
