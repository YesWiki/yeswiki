# Development

## Customizations

Customizations can be made in the `custom` folder.
A [documentation file](docs/custom-folder.md) exists to describe the structure of this folder ([docs/custom-folder.md](docs/custom-folder.md)).

### Create a custom bazar field

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


    use YesWiki\Bazar\Field\DateField as CoreDateField;
    
    class DateField extends CoreDateField
    {
        // ....
 5. update the declaration of the associated field name in the header `@Field({"possiblename1", "possiblename2", "possiblename3"})`
 6. Once the code is ready, it is then possible to define a custom field in the graphical form builder.

### Add a new component to `actions-builder`

 1. Choose a component from the `tools/aceditor/presentation/javascripts/components` folder that resembles the custom component you are looking for (e.g. `InputGeo.js`)
 2. Copy it to the `custom/javascripts/components/actions-builder` folder
    - keep your name if you want to replace the original field
    - rename it if you do not want to replace the original field
 3. Be careful to update the relative paths for imports (example: `import InputMultiInput from '.. /.. /.. /.. /tools/aceditor/presentation/javascripts/components/InputMultiInput.js'`)
 4. To use it, define in the file `.yaml` associated with actions builder for the desired part the type that goes well.
    - if the file is called `InputNomInput.js`, then you have to type in the file `.yaml`: `type: name-input`
    - if the file is called `InputGeo.js`, you have to type `type: geo`

## Make your wiki semantic

_[Original documentation on the yeswiki.net site (fr)](https://yeswiki.net/?RendreYeswikiSemantique "Tutorial - Make your wiki semantic")_

## Create a bazar widget

_[Original documentation on the yeswiki.net site (fr)](https://yeswiki.net/?BazarWidget "Tutorial - Create widget bazaar")_

## Create a local work environment

_[Original documentation on the yeswiki.net site (fr)](https://yeswiki.net/?PageConfiglocal "Tutorial - Create a local dev environment")_