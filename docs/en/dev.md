# Development

## Customize with code

The `custom` folder will be used for all your specific changes in yeswiki views.

An example of how a custom folder look like can be found at https://github.com/YesWiki/yeswiki-custom-code-example

> (Advanced) You can clone this example in your wiki by manually deleting the custom folder, and then running the command `git clone https://github.com/YesWiki/yeswiki-custom-code-example.git custom`

### Custom templates

The folder `custom/templates` is for your own custom templates.

#### bazarliste templates

add the file `custom/templates/bazar/my-template.tpl.html`
Then you can use it from a bazar action `{{bazarliste id="1" template="my-template.tpl.html"}}`
You can also overide an existing template : `custom/templates/bazar/liste_accordeon.tpl.html`

Custom template will appear in the bazarliste component with default name : "Template custom : filename"

If you need to personnalize the name add a translation in file /custom/lang/custom_fr.inc.php
example : 'AB_filename_label' => 'Template custom annuaire',

#### bazar single entry template

uses the convention `fiche-FORM_ID.tpl.html`
If you have a bazar form with id 5, then you can create `custom/templates/bazar/fiche-5.tpl.html`
Available variables inside the template are

| Variable           | Description                                    | Example                                              |
| ------------------ | ---------------------------------------------- | ---------------------------------------------------- |
| `$values['fiche']` | Values of the current entry                    | `$values['fiche']['bf_titre'] => "My title"`         |
| `$values['html']`  | Pre-rendered fields                            | `$values['html']['bf_titre'] => "<h1>My title</h1>"` |
| `$values['form']`  | Informations about the form : id, fields etc.. |

#### **Dynamic** bazarlist templates

It is possible to customize for `{{bazarliste dynamic="true"}}`. But for that, the previous `my-template.tpl.html` can not be used even if using `my-template.twig`.

**Explanation**:

1.  a bazarlist dynamic template is run into `javascript` via [VueJs](https://v2.vuejs.org/v2/guide) library
2.  a not dynamic bazarlist template is run into `php`

```
------------
|   twig   |      extract
| template |      template
------------     from html
    | USE        ========>
    v           /         \
--------    --------    ---------
|  PHP | => | html | => | VueJs |
--------    --------    ---------
                            |
                            v
-------------------     -------------
| manipulate      | <=  | javascript |
| DOM dynamically |     | functions  |
-------------------     --------------
```

_Diagram of process for dynamic templates_

**Process**:

- create a `twig` template that extends [`@bazar/entries/index-dynamic.twig`](tools/bazar/templates/entries/index-dynamic.twig ':ignore') into `custom/templates/bazar/entries/index-dynamic-templates/` with this code for example:

  ```twig
  {% extends "@bazar/entries/index-dynamic.twig" %}

  {% block display_entries %}
  {% endblock %}
  ```

- it is possible to use this template with `yeswiki` code (do not add `.twig` example) :
  ```yeswiki
  {{bazarliste id=".." template="my-file-name" dynamic="true"}}
  ```
- modify the block `display_entries` to add a `VueJs` template
- it is possible to inspire from [`@bazar/entries/index-dynamic-templates/list.twig`](tools/bazar/templates/entries/index-dynamic-templates/list.twig ':ignore') template
- first example:

  ```twig
  {% extends "@bazar/entries/index-dynamic.twig" %}

  {% block display_entries %}
    <div class="my-class-name no-dblclick" v-if="ready">
       My first message
    </div>
  {% endblock %}
  ```

- **BE CAREFUL**, a `vuejs` template **IS NOT** a twig template. The syntax is different.
- if you need to add `css` or `javascripts` into the template, you can rewrite the block `assets`

  ```twig
  {% extends "@bazar/entries/index-dynamic.twig" %}

  {% block assets %}
     {{ include_css('custom/path/to/file.css') }}
     {{ include_javascript('custom/path/to/file.js') }}
  {% endblock %}

  {% block display_entries %}
    {# ... #}
  {% endblock %}
  ```

- dynamic `bazarlist` does not import all data from the entry. To define needed fields for the template, you need to add this line :

  ```twig
  {% extends "@bazar/entries/index-dynamic.twig" %}

  {% set necessary_fields = ['bf_field1', 'bf_field2','url'] %}

  {% block assets %}
    {# ... #}
  {% endblock %}

  {% block display_entries %}
    {# ... #}
  {% endblock %}
  ```

**Example with only titles**:

```twig
   {% extends "@bazar/entries/index-dynamic.twig" %}

   {% set necessary_fields = ['bf_titre', 'url'] %}

   {% block display_entries %}
     <div class="my-class-name no-dblclick" v-if="ready">
        <div v-if="entriesToDisplay.length == 0" class="alert alert-info">
          {{ _t('BAZ_NO_RESULT') }}
        </div>
        <ul v-else>
            <li v-for="entry in entriesToDisplay"><span v-html="entry.bf_titre || entry.id_fiche"></span></li>
        </ul>
     </div>
   {% endblock %}
```

**Particularity for the rendering shortcut `{{ }}`**:

The shortcut `{{ }}` is used by `twig` and `vuejs`.
So the first time that shortcut `{{ }}` is used this is for `twig`.

In `VueJs`, the shortcut `{{ }}` means `<span v-html=""></span>`.

It is advised to use `<span v-html=""></span>` but you can find in existing templates `{{ "{{ entry.bf_titre }}" }}`.

**Advise to render a dynamic string**:

Use

```
<span v-html="`${entry.bf_titre} (${entry.id_fiche})`"></span>
```

using the back-quoted string into `javascript` with `${...}` to generate interpolation from `javascript`.

the previous example is similar to (the `+` is used to concatenate strings):

```
<span v-html="''+entry.bf_titre+' ('+entry.id_fiche+')'"></span>
```

**Example of link to entry**:

```vuejs
<a :href="entry.url" :title="entry.title || entry.bf_titre">Lire plus (<span v-html="entry.id_fiche"></span>)</a>
```

**If you need a component**:

Sometimes, it is not possible to do all things only with html to define the [`VueJs`](https://v2.vuejs.org/v2/guide/components.html) template : a component is needed.

An example is the [`@bazar/entries/index-dynamic-templates/map.twig`](tools/bazar/templates/entries/index-dynamic-templates/map.twig ':ignore') template.

1.  define a component into a `.js` file inspiring from [`tools/bazar/presentation/javascripts/components/BazarMap.js`](tools/bazar/presentation/javascripts/components/BazarMap.js ':ignore') respecting [`VueJs v2`](https://v2.vuejs.org/v2/guide/components.html) syntaxe
2.  use this component into the `html`

### Custom Javascript

All the javascript files in the `custom/javascripts/` directory are included.

But files in a subfolder are not automatically included.

### Extra-components for actions-builder

1.  Choose a component from the `tools/aceditor/presentation/javascripts/components` folder that resembles the custom component you are looking for (e.g. `InputGeo.js`)
2.  Copy it to the `custom/javascripts/components/actions-builder` folder
    - keep your name if you want to replace the original field
    - rename it if you do not want to replace the original field
3.  Be careful to update the relative paths for imports (example: `import InputMultiInput from '.. /.. /.. /.. /tools/aceditor/presentation/javascripts/components/InputMultiInput.js'`)
4.  To use it, define in the file `.yaml` associated with actions builder for the desired part the type that goes well.
    - if the file is called `InputNomInput.js`, then you have to type in the file `.yaml`: `type: name-input`
    - if the file is called `InputGeo.js`, you have to type `type: geo`

### Custom Css

All the css files in the `custom/styles/` directory are included.

### Custom Css-Presets

For themes using presets, you can have custom presets.

- They should be in folder `custom/css-presets/`.
- Their extension should be `.css`
- Their content should be like this:

```css
:root {
  --primary-color: #1a89a0;
  --secondary-color-1: #d8604c;
  --secondary-color-2: #d78958;
  --neutral-color: #4e5056;
  --neutral-soft-color: #b0b1b3;
  --neutral-light-color: #ffffff;
  --main-text-fontsize: 17px;
  --main-text-fontfamily: 'Nunito', sans-serif;
  --main-title-fontfamily: 'Nunito', sans-serif;
}
```

### Custom Squelette

Override the theme squelette following the path `custom/themes/THEME_TO_OVERRIDE/squelettes/SQUELETTE_TO_OVERRIDE.tpl.html`

For example with the theme margot ->  
`custom/themes/margot/squelettes/1col.tpl.html`  
`custom/themes/margot/squelettes/2cols-right.tpl.html`

### Custom Actions

Puts your custom actions in `custom/actions`
You can access the action params with `$this->GetParameter('myparam');`
Names of the param can only be lower case character

### Custom Handlers

Puts your custom handlers in `custom/handlers/page` or `custom/handlers` as class

### Custom Langs

Puts your custom translations in `custom/lang/custom_LOCALE.inc.php`, where LOCALE is `fr` `en` `es`...
you can then use your translation with following code `<?php echo _t('MY_TRANSLATION_KEY'); ?>`

### Custom Bazar Field

_[Original documentation on the yeswiki.net site (fr)](https://yeswiki.net/?TutorielCreerUnChampBazarCustom 'Tutorial - Create a custom bazar field (fr)')_

1. choose from the folder `tools/bazaar/fields`, a template field that resembles the custom field (e.g. `DateField.php`)
2. Copy it to the `custom/fields` folder
   - keep your name if you want to replace the original field
   - rename it if you do not want to replace the original field (please rename the name of the class in the file by replacing `class DateField` with `class FileName`)
3. Replace `YesWiki\Bazar\Field;` namespace in the file with `YesWiki\Custom\Field namespace;`
4. Set up inheritance:
   - if we keep `extends BazarField`, make sure that there is `use YesWiki\Bazar\Field\BazarField;` in the file
   - if you want to inherit from another class write `extends OtherField` and make sure that the file has this: `use YesWiki\Bazar\Field\OtherField;`
   - finally, when replacing a field of the heart, it is advisable to make an inheritance from the original field. In our example it would give:

```php
use YesWiki\Bazar\Field\DateField as CoreDateField;

class DateField extends CoreDateField
{
    // ....
```

5. update the declaration of the associated field name in the header `@Field({"possiblename1", "possiblename2", "possiblename3"})`
6. Once the code is ready, it is then possible to define a custom field in the graphical form builder.

### Custom Service

Puts your custom fields in `custom/services`
use `namespace YesWiki\Custom\Service;`
and create the file`custom/config.yaml` with

```yaml
services:
  _defaults:
    autowire: true
    public: true

  YesWiki\Custom\Service\:
    resource: 'services/*'
```

### Custom Controller

Puts your custom fields in `custom/controller`
use `namespace YesWiki\Custom\Controller;`
and create the file`custom/config.yaml` with

```yaml
services:
  _defaults:
    autowire: true
    public: true

  # Allows to use controllers as services
  YesWiki\Custom\Controller\:
    resource: 'controllers/*'
```

### Custom api route

YesWiki allows to define a new api route (url like [`?api/forms`](?api/forms ':ignore')).

#### Documentation about coding rules

A document describes `api` coding rules and examples : [docs/code/api.md](/docs/code/api.md)

#### Creation of a new route

1.  create a new controller `custom/controllers/ApiController.php` with some code like

```php
<?php

namespace YesWiki\Custom\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api/my/route/{name}",methods={"GET"}, options={"acl":{"public"}},priority=2)
     */
    public function myRoute($name)
        // do something
        return new ApiResponse(['result'=>$name],Response::HTTP_OK);
    }
}
```

2.  be careful to register the `controllers` folder in `config.yaml` like described above.

### Custom Command

Puts your custom fields in `custom/commands`
use `namespace YesWiki\Custom\Commands;`

## Make your wiki semantic

_[Original documentation on the yeswiki.net site (fr)](https://yeswiki.net/?RendreYeswikiSemantique 'Tutorial - Make your wiki semantic')_

## Create a bazar widget

_[Original documentation on the yeswiki.net site (fr)](https://yeswiki.net/?BazarWidget 'Tutorial - Create widget bazaar')_

## Create a local work environment

_[Original documentation on the yeswiki.net site (fr)](https://yeswiki.net/?PageConfiglocal 'Tutorial - Create a local dev environment')_

## Create a YesWiki extension

Code created for new features can be proposed to the community via two way.

1. by making a Pull-Request (PR) in the core project YesWiki : https://github.com/YesWiki/yeswiki/pulls
   - this is reserved to new features validated b ythe community for their utility for the core.
2. or creating a new dedicated extension which can be easily added or remove. Add an extension is more flexible and can be made by a main developper of YesWiki, with the possibility to the community to ask a posteriori to rename or remove the extension.

### Create a YesWiki extension

1.  having rights to create a folder into the organization https://github.com/YesWiki, create a `repository` whose name must be like `yeswiki-extension-extensionname`, where `extensionname` is replaced by the name of the extension, without special characters.
2.  pull files on the new repository
3.  add a file `LICENSE` (shoudl be AGPL 3.0 at minimum knowing that `YesWiki` follow this license) and a file `README.md`
4.  modify the repository configuraiton file : https://github.com/YesWiki/yeswiki-build-repo/blob/master/repo.config.json
    - add the new extension for each revision of `YesWiki` which supports this extension yb inspiring from other extensions
    - update the concerned documentation link to `README.md` from extension or to a documantation page on the internet
5.  go to the server `repository-api` of `YesWiki` with your `SSH` key (key only given to authorized developpers)
6.  update the local file `repo.config.json`, normally with command `git pull` (warning the command is here only for indication)
7.  start the update of the repository
8.  warn developpers community of the creation of this extension on Framateam channel : https://framateam.org/yeswiki/channels/developpement
    - the community `YesWiki` can ask to rename the extension, for that :
      - create a new extension with the new name
      - copy files
      - delete the current extension

#### Add a `webhook` pfor repositories not in `https://github.com/YesWiki`

_Indeed, repositories in https://github.com/YesWiki have automatically a `webhook` because they are repositories of this organization._

1.  go back to the folder `https://github.com/YesWiki/yeswiki-extension-extensioname` (real url to update)
2.  click on `Settings`
3.  in left sidebar, click on `Webhooks`, then `Add webhook` (the link looks like `https://github.com/YesWiki/yeswiki-extension-extensionname/settings/hooks/new`)
4.  fill the form like
    - **Payload URL** : url of `repository-api`
    - **Content type** : application/json
    - **Secret** : secret GitHub password from config file on server `repository-api`
    - choose **just the `push` event**

### Delete an extension

1.  Delete the concerned repository on `GitHub` (**this action can not be undone**)
2.  go on https://github.com/YesWiki/yeswiki-build-repo/blob/master/repo.config.json and remove references to the extension
3.  go with your `SSH` key on `repository-api` to update the file `repo.config.json` with `git pull`
4.  go to the subfolder of `repository.yeswiki.net` to delete extension's `.zip` files and remove them to be available on the internet
5.  for each revision of `YesWiki`, go to the concerned subfolder to remove references to the extension in file `package.json`
6.  warn the community on the channel Framateam : https://framateam.org/yeswiki/channels/developpement

## YesWiki's api

Available routes will be shown on url <https://mywiki.url/?api>
The api access is closed by default (unless your route says otherwise), you need to define a parameter as specified below to open it.

### Public API scenario

**⚠️ Be careful, in a public api, everybody can access to all data !**

In `wakka.config.php`, add the parameter :

```php
'api_allowed_keys' => ['public' => true],
```

### Private API scenario

In `wakka.config.php`, set the parameter :

```php
 'api_allowed_keys' =>
  [
    'custom-client-id' => 'NoHloX4xVNZQNyhpvwwbHczS4DPTSbqZbm6rIJ9VFRc=',
  ],
```

_The keyname `custom-client-id` is not important and only useful to identificate the user of the token._

### Access with curl

Define the http header with the token alike the config file (be careful to the spaces) :point_down:

```
Authorization: Bearer NoHloX4xVNZQNyhpvwwbHczS4DPTSbqZbm6rIJ9VFRc=
```

Example using curl

```bash
curl https://mywiki.url/?api/hello/IT%20WORKS!!
	-H "Accept: application/json"
    -H "Authorization: Bearer NoHloX4xVNZQNyhpvwwbHczS4DPTSbqZbm6rIJ9VFRc="
```

### Principles (coding standards)

In yeswiki's core code, the main declaration is in file :
`includes/controllers/ApiController.php`.
This `controllers/ApiController.php` can be defined in any extension (in the tools folder, example `tools/helloworld/controllers/ApiController.php`)

We are using Symfony annotations to **declare new routes** for the API.

```php
/**
 * @Route("/api/users/{userId}")
 */
public function getUser($userId)
{ ...}
```

To show your routes in the main `https://mywiki.url/?api` page, you need to declare a getDocumentation() method in your custom ApiController class that will return the html to display on the page.
Be careful to the namespace used in your file `YesWiki\ExtensionName\controller`

```php
    /**
     * Display helloworld api documentation
     *
     * @return string
     */
    public function getDocumentation()
    {
        $output = '<h2>Extension Hello World</h2>';

        $urlHello = $this->wiki->Href('', 'api/hello/test');
        $urlHelloTest = $this->wiki->Href('', 'api/hello/{test}');
        $output .= 'The following code :<br />';
        $output .= 'GET <code>'.$urlHelloTest.'</code><br />';
        $output .= 'gives :<br />';
        $output .= '<code>test</code><br />Example : <br />';
        $output .= 'GET <code><a href="'.$urlHello.'">'.$urlHello.'</a></code><br />';

        return $output;
    }
```

### Open a public route in private API scenario

It is possible to open a **public route** even in private API scenario.

In the file of the route, change the method's annotation from :

```php
/**
 * @Route("/api/myroute")
 */
```

to

```php
/**
 * @Route("/api/myroute",options={"acl":{"public"}})
 */
```

This route will be accessible to everyone without authorization bearer.

**To open a route to specific group _@mygroup_ without bearer**, change the method's annotation from :

```php
/**
 * @Route("/api/myroute")
 */
```

to

```php
/**
 * @Route("/api/myroute",options={"acl":{"public","@mygroup"}})
 */
```

This route will be accessible only to members of this group without authorization bearer.
_The cookies or $\_POST for authentification the concerned user should be sent in the request._

## YesWiki's anti CSRF Documentation

CSRF (Cross-site request forgery) is a method of web attack in one click described for example in [Wikipedia](https://en.wikipedia.org/wiki/Cross-site_request_forgery).

### Usage

`symfony/security-csrf` is used to manage tokens needed to prevent csrf. It is installed bt default with composer and `YesWiki::loadExtensions()`.

1.  Get a token and use it into the concerned form or link for any action that may alter data (create/update/delete) and that needs acl validation (like `deletepage`, delete a user, change password)
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
2.  when processing a request with the token, check if it is right inspiring of this example :

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

### Refreshing a token

You can refresh a token to delete its previous value and replace it by a new one.

- in PHP

```
$token = $this->wiki->services->get(CsrfTokenManager::class)->refreshToken('tokenId');
```

- in `twig` template, use `{{ csrfToken({id:'tokenId',refresh:true}) }}`;

Previous token will be considered as invalid after calling `refreshToken`.

### Rules to name 'tokenId'

In most case, we use `main` as tokenId and does not `GET` request with `token`.

#### Previous rules

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
