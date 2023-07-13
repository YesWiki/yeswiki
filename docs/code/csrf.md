# YesWiki's anti CSRF Documentation 

## What is it ?

CSRF (Cross-site request forgery) is a method of web attack in one click described for example in [Wikipedia](https://en.wikipedia.org/wiki/Cross-site_request_forgery).

## Usage

`symfony/security-csrf` is used to manage tokens needed to prevent csrf. It is installed bt default with composer and `YesWiki::loadExtensions()`.

 1. Get a token and use it into the concerned form or link for any action that may alter data (create/update/delete) and that needs acl validation (like `deletepage`, delete a user, change password)
    - in php code, get a token with this code for example :
    ```
    use Symfony\Component\Security\Csrf\CsrfTokenManager;
    ...
    $token = $this->wiki->services->get(CsrfTokenManager::class)->getToken('tokenId');
    ```
    - in `twig` template, use `{{ csrfToken('tokenId') }}`. Example for a form (twig):
    ```
    <input type="hidden" name="tokenNameInForm" value="{{ csrfToken('tokenId')|e('html_attr') }}">
    ```
 2. when processing a request with the token, check if it is right inspiring of this example :
   ```
   use Symfony\Component\Security\Csrf\CsrfToken;
   use Symfony\Component\Security\Csrf\CsrfTokenManager;
   ...
   $csrfTokenManager = $this->wiki->services->get(CsrfTokenManager::class);
   // replace 'tokenNameInForm' by the used name in form's input in following line
   $token = new CsrfToken('tokenId', htmlspecialchars(filter_input(INPUT_POST,'tokenNameInForm', FILTER_UNSAFE_RAW)));

   if ($csrfTokenManager->isTokenValid($token)) {
       ...
       $csrfTokenManager->removeToken('tokenId'); // remove it if you want only one usage
   }
   ```

   or with a controller throwing `TokenNotFoundException` :
   ```
   use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
   use YesWiki\Core\Controller\CsrfTokenController;
   $csrfTokenController = $this->wiki->services->get(CsrfTokenController::class);
   try {
      $csrfTokenController->checkToken('tokenId', 'POST', 'tokenNameInForm');
      ... code if OK
   } catch (TokenNotFoundException $th) {
      $errorMessage = $th->getMessage();
      ... // code if not OK
   }
   ```

## Refreshing a token

You can refresh a token to delete its previous value and replace it by a new one.
 - in PHP
```
$token = $this->wiki->services->get(CsrfTokenManager::class)->refreshToken('tokenId');
```
 -  in `twig` template, use `{{ csrfToken({id:'tokenId',refresh:true}) }}`;

Previous token will be considered as invalid after calling `refreshToken`.

## Rules to name 'tokenId'

In most case, we use `main` as tokenId and does not `GET` request with `token`.

### Previous rules

We propose the following rule to name token and avoid trouble between methods.
`<type-of-class>\<name-of-class/action>\<concerned-page>`.
For api we can have
`METHOD api/route/id`.

Examples:
 - delete a page `handler\deletepage\MyPageTag`
 - delete a user `action\usertable\deleteuser\UserName`
 - password update `action\usersettings\changepass`
 - password update `DELETE api/pages/MyPageTag`

It is possible to add the tool's name like `login\action\usersettings\changepass`.

Be careful, it is better to write `"handler\\update\\MyPageTag"` than `"handler\update\MyPageTag"`, or prefer using `'handler\update\MyPageTag'`

