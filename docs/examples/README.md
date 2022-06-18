# Examples to customize YesWiki

Customization is available by putting files into folder `custom/`.

Here are example of files and folders to put into `custom/` with the same hierarchy from this folder.
Example :
 - Copy `docs/examples/templates/custom/custom-entry-helper.twig` into `custom/templates/custom/custom-entry-helper.twig` then modify its content.
 - Copy `docs/examples/styles/bazar/macro-custom.css` into `custom/styles/bazar/macro-custom.css` then modify its content.

**Particularity**:
For `fiche-x.twig` files, you must rename it with the corresponding form'id.

Example for form number `10` :
 - Copy `docs/examples/templates/bazar/fiche-x.twig` into `custom/templates/bazar/fiche-10.twig` then modify its content.

## Forms and lists for examples

The example will work with following forms and lists.

**Create a list "Projects" with these values**:
 - `project1` => "Project 1"
 - `project2` => "Project 2"

**Create a list "Params" with these values**:
 - `full` => "fullscreen"
 - `extract` => "extract"
 - `summary` => "summary"

**Create a form with this code**:
```
texte***bf_titre***Titre*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***
image***bf_image***Image***140***140***400***400***right***0*** *** *** * *** * *** *** *** ***
textelong***bf_description***Description*** *** *** *** ***wiki***0*** *** *** * *** * *** *** *** ***
lien_internet***bf_site_internet***Site internet*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***
champs_mail***bf_mail***Email*** *** *** ***form*** ***0***0*** *** * *** * *** *** *** ***
listedatedeb***bf_date_debut_evenement***Début*** *** ***today*** *** ***1*** *** *** * *** * *** *** *** ***
listedatedeb***bf_date_fin_evenement***Fin*** *** ***today*** *** ***1*** *** *** * *** * *** *** *** ***
texte***bf_lieu***Nom du lieu*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
texte***bf_adresse***Adresse*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
texte***bf_adresse1***Complément d'adresse*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
texte***bf_codepostal***Code postal*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***
texte***bf_ville***Ville*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***
texte***bf_telephone***Téléphone*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
map***bf_latitude***bf_longitude*** *** *** *** *** ***0***
liste***ListeProjects***Type de projet*** *** *** *** *** ***0*** *** *** % *** % *** *** *** ***
checkbox***ListeParams***Paramètre d'affichage*** *** *** ***bf_display_params*** ***0*** *** *** % *** % *** *** *** ***
```