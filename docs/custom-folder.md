# Custom directory

The folder `custom` will be used for all your specific changes in yeswiki views.

An example of how a custom folder look like can be found at https://github.com/YesWiki/yeswiki-custom-code-example

(Advanced) You can clone this example in your wiki by manually deleting the custom folder, and then running the command `git clone https://github.com/YesWiki/yeswiki-custom-code-example.git custom`

## Custom templates

The folder `custom/templates` is for your own custom templates.

Example1: For bazarliste templates, add the file `custom/templates/bazar/my-template.tpl.html`
Then you can use it from a bazar action `{{bazarliste id="1" template="my-template.tpl.html"}}`
You can also overide an existing template : `custom/templates/bazar/liste_accordeon.tpl.html`

Example2: To customize bazar single entry template, uses the convention `fiche-FORM_ID.tpl.html`
If you have a bazar form with id 5, then you can create `custom/templates/bazar/fiche-5.tpl.html`
Available variables inside the template are
 - `$values['fiche']` -> contains the values of the current fiche. Example `$values['html']['bf_titre']` will contains `My title`
 - `$values['html']` -> the pre rendered fields. Example `$values['html']['bf_titre']` will contains `<h1>My title</h1>`
 - `$values['form']` -> information about the form : id, fields etc..

## Custom Javascript

All the javascript files in the `custom/javascripts/` directory are included.

## Custom Css

All the css files in the `custom/styles/` directory are included.

## Custom Css-Presets

For themes using presets, you can have custom presets.

 - They should be in folder `custom/css-presets/`.
 - Their extension should be `.css`
 - Their content should be like this:

```
:root {
  --primary-color: #1a89a0;
  --secondary-color-1: #d8604c;
  --secondary-color-2: #d78958;
  --neutral-color: #4e5056;
  --neutral-soft-color: #b0b1b3;
  --neutral-light-color: #ffffff;
  --main-text-fontsize: 17px;
  --main-text-fontfamily: 'Nunito', sans-serif;
  --main-title-fontfamily:'Nunito', sans-serif;
}
```

## Custom Squelette

Override the theme squelette following the path `custom/themes/THEME_TO_OVERRIDE/squelettes/SQUELETTE_TO_OVERRIDE.tpl.html`

For example with the theme margot ->  
`custom/themes/margot/squelettes/1col.tpl.html`  
`custom/themes/margot/squelettes/2cols-right.tpl.html`

## Custom Actions

Puts your custom actions in `custom/actions`
You can access the action params with `$this->GetParameter('myparam');`
Names of the param can only be lower case character

## Custom Handlers

Puts your custom handlers in `custom/handlers/page` or `custom/handlers` as class

## Custom Langs

Puts your custom translations in `custom/lang/custom_LOCALE.inc.php`, where LOCALE is `fr` `en` `es`...
you can then use your translation with following code `<?php echo _t('MY_TRANSLATION_KEY'); ?>`

## Custom Field

Puts your custom fields in `custom/fields`
use `namespace YesWiki\Custom\Field;`

## Custom Service

Puts your custom fields in `custom/services`
use `namespace YesWiki\Custom\Service;`
and create the file`custom/config.yaml` with

```
services:
  _defaults:
    autowire: true
    public: true

  YesWiki\Custom\Service\:
    resource: 'services/*'
```

## Custom Controller

Puts your custom fields in `custom/controller`
use `namespace YesWiki\Custom\Controller;`
and create the file`custom/config.yaml` with

```
services:
  _defaults:
    autowire: true
    public: true

  # Allows to use controllers as services
  YesWiki\Custom\Controller\:
    resource: 'controllers/*'
```

## Custom Command

Puts your custom fields in `custom/commands`
use `namespace YesWiki\Custom\Commands;`