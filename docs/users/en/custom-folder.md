The `custom` folder will be used for all your specific changes in yeswiki views.

An example of how a custom folder look like can be found at https://github.com/YesWiki/yeswiki-custom-code-example

> (Advanced) You can clone this example in your wiki by manually deleting the custom folder, and then running the command `git clone https://github.com/YesWiki/yeswiki-custom-code-example.git custom`

### Custom templates

The folder `custom/templates` is for your own custom templates.

Example1: For bazarliste templates, add the file `custom/templates/bazar/my-template.tpl.html`
Then you can use it from a bazar action `{{bazarliste id="1" template="my-template.tpl.html"}}`
You can also overide an existing template : `custom/templates/bazar/liste_accordeon.tpl.html`
Custom template will appear in the bazarliste component with default name : "Template custom : filename"
If you need to personnalize the name add a translation in file /custom/lang/custom_fr.inc.php
example : 'AB_filename_label' => 'Template custom annuaire',

Example2: To customize bazar single entry template, uses the convention `fiche-FORM_ID.tpl.html`
If you have a bazar form with id 5, then you can create `custom/templates/bazar/fiche-5.tpl.html`
Available variables inside the template are

| Variable | Description | Example |
| ----------- | ----------- | -------- |
| `$values['fiche']` | Values of the current entry | `$values['fiche']['bf_titre'] => "My title"` |
| `$values['html']` | Pre-rendered fields | `$values['html']['bf_titre'] => "<h1>My title</h1>"` |
| `$values['form']` | Informations about the form : id, fields etc.. |

### Custom Javascript

All the javascript files in the `custom/javascripts/` directory are included.

But files in a subfolder are not automatically included.

### Extra-components for actions-builder

 1. Choose a component from the `tools/aceditor/presentation/javascripts/components` folder that resembles the custom component you are looking for (e.g. `InputGeo.js`)
 2. Copy it to the `custom/javascripts/components/actions-builder` folder
    - keep your name if you want to replace the original field
    - rename it if you do not want to replace the original field
 3. Be careful to update the relative paths for imports (example: `import InputMultiInput from '.. /.. /.. /.. /tools/aceditor/presentation/javascripts/components/InputMultiInput.js'`)
 4. To use it, define in the file `.yaml` associated with actions builder for the desired part the type that goes well.
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
  --main-title-fontfamily:'Nunito', sans-serif;
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

_[Original documentation on the yeswiki.net site (fr)](https://yeswiki.net/?TutorielCreerUnChampBazarCustom "Tutorial - Create a custom bazar field (fr)")_

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

### Custom Command

Puts your custom fields in `custom/commands`
use `namespace YesWiki\Custom\Commands;`
