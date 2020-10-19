# Custom templates directory

The folder `custom/templates` is for your own custom templates.

Example1: For bazarliste templates, add the file `custom/templates/bazar/my-template.tpl.html`
Then you can use it from a bazar action `{{bazarliste id="1" template="my-template.tpl.html"}}`
You can also overide an existing template : `custom/templates/bazar/liste_accordeon.tpl.html`

Example2: To customize bazar single entry template, uses the convention `fiche-FORM_ID.tpl.html`
If you have a bazar form with id 5, then you can create `custom/templates/bazar/fiche-5.tpl.html`
Available variables inside the template are
$values['fiche'] -> contains the values of the current fiche. Example $values['html']['bf_titre'] will contains "My title"
$values['html'] -> the pre rendered fields. Example $values['html']['bf_titre'] will contains "<h1>My title</h1>"
$values['form'] -> information about the form : id, fields etc..

