# Les actions dans les gabarits

Un gabarit Twig accepte la syntaxe des actions qu'on écrit dans une page. Un
squelette, un gabarit de fiche ou une surcharge dans `custom/templates/` peut
contenir `{{button text="Ok" link="PagePrincipale"}}` et affiche le même bouton
qu'une page.

## La syntaxe

On écrit l'action comme dans une page, sans espace après `{{`.

```twig
{{button text="Ok" link="PagePrincipale"}}
{{section bgcolor="#2b6d4f"}}
  {{ page_content|raw }}
{{end elem="section"}}
{{bazarliste id="1" template="card"}}
```

Les paramètres prennent des guillemets doubles. Un appel peut tenir sur
plusieurs lignes, et les numéros de ligne des erreurs Twig restent ceux du
fichier.

Le wiki convertit ces appels une seule fois, quand Twig compile le gabarit. Il
ne touche pas aux constructions Twig. Un appel écrit dans `{{ … }}`, dans
`{% … %}`, dans un commentaire `{# … #}` ou dans un bloc `{% verbatim %}` garde
son sens Twig. Ainsi `{{ format('{{include page="Menu"}}') }}` passe toujours
le texte de l'appel à `format()`.

## Une action sans paramètre

`{{yeswikiversion}}` est aussi une variable Twig valide. La variable passe en
premier.

- si le gabarit reçoit une variable `yeswikiversion`, il l'affiche ;
- sinon, il lance l'action `yeswikiversion` ;
- si aucune action ne porte ce nom, il n'affiche rien, comme Twig le fait pour
  une variable absente.

Pour forcer l'action, écrivez `{{yeswikiversion /}}`. Pour forcer la variable,
mettez des espaces, `{{ yeswikiversion }}`.

Attention à un nom de variable qui est aussi un nom d'action, comme `label`,
`button` ou `pagetitle`. Si la variable n'est pas définie, `{{label}}` affiche
l'action `label`. Écrivez `{{ label }}` pour l'éviter.

## Ouvrir et fermer un élément

Dans une page, `{{end elem="section"}}` ne ferme la section que si la page en
ouvre autant qu'elle en ferme. Dans un gabarit, `{{end}}` ferme toujours
l'élément ouvert par le gabarit. Une page qui laisse une section ouverte ne
casse donc plus le squelette qui l'entoure.

## Dans un gabarit de fiche

Un gabarit de fiche s'affiche une fois par fiche. Une action placée dedans
tourne donc une fois par fiche. Une liste de 200 fiches qui contient
`{{bazarliste}}` lance 200 listes.
