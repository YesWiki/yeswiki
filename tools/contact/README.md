# Extension contact

Pour envoyer des mails avec YesWiki.

## Configuration

Options disponibles à rajouter un fichier de configuration `wakka.config.php`.

### fonction d'envoi de mail

Par défaut la fonction mail de php est utilisée, mais elle est souvent désactivée, ou considérée comme du spam par beaucoup de fournisseurs de mail...

Pour utiliser smtp (recommandé)

```php
  'contact_mail_func' => 'smtp', //Valeurs possibles "mail" (par défaut), "sendmail" ou "smtp"
  'contact_smtp_host' => 'ssl://mail.gandi.net:465', //pour gmail (mais pas bio..) : 'ssl://smtp.gmail.com:465'
  'contact_smtp_user' => 'votre.email@domaine.ext',
  'contact_smtp_pass' => 'xxxxxxx',

```

Pour les développeurs, exemple de configuration avec [Mailcatcher](https://github.com/sj26/mailcatcher)

```php
  'contact_mail_func' => 'smtp', //Valeurs possibles "mail" (par défaut), "sendmail" ou "smtp"
  'contact_smtp_host' => '127.0.0.1',
  'contact_smtp_port' => '1025',
  'contact_smtp_user' => '',
  'contact_smtp_pass' => '',

```

### autres parametres de configuration

```php
  'contact_debug' => '0', // affiche un log pour l'envoi de mail (0 pour rien (par défaut), 1 pour normal, 2 pour détaillé)
  'contact_reply_to' => 'recoit.les.reponses@domaine.ext', // email auquel on répond quand on recoit un mail et qu'on clique répondre
  'contact_passphrase' => 'CeciEstUnePhraseAChanger!!Pour2vrai<3', //phrase passée en url pour envoyer des emails périodiques (voir plus bas)

```

## Pour faire un envoi mail régulier

On peut recevoir des infos sur les derniere modifications (comme la page tableau de bord) en bidouillant un peu :
Sur une page comme [TableauDeBord](https://yeswiki.net/?TableauDeBord) l'action {{mailperiod}} permet aux personnes authentifiées de choisir une période d'envoi de mails et de s'y abonner.

Pour que les mails soient envoyés il faut :

- mettre un mot de passe dans `wakka.config.php` par exemple: `'contact_passphrase' => 'CeciEstUnePhraseAChanger!!Pour2vrai<3',`
- paramétrer le cron de votre serveur pour envoyer les mails par url a la période souhaitée (changer TableauDeBord par la page wiki de votre choix, et mettre votre mot de passe) :
  - pour le mail quotidien : `https://urldemonwiki.ext/?TableauDeBord/sendmail&period=day&key=CeciEstUnePhraseAChanger!!Pour2vrai<3`
  - pour le mail hedbomadaire : `https://urldemonwiki.ext/?TableauDeBord/sendmail&period=week&key=CeciEstUnePhraseAChanger!!Pour2vrai<3`
  - pour le mail mensuel : `https://urldemonwiki.ext/?TableauDeBord/sendmail&period=mensuel&key=CeciEstUnePhraseAChanger!!Pour2vrai<3`

Si vous n'avez pas accès au cron de votre serveur, on peut passer par un service webcron comme [cron-job.org](https://cron-job.org)
