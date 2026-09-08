# Extension Login

Pour gérer la connexion au yeswiki

## Configuration

Options disponibles à rajouter un fichier de configuration `wakka.config.php`.

## Interdire globalement la création de compte par chacun

```php
  'noSignupButton' => true/false,
```

## Avoir une page personnalisée de login

Par exemple pour interdire aux visiteurs de se créer un compte eux même mais rediriger vers un formulaire de contact :

```php
  'signupUrl' => 'ContactezNous',
```
