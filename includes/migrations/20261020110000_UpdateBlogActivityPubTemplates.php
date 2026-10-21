<?php

use YesWiki\Core\YesWikiMigration;

/**
 * Gives forms still on a shipped Blog-actu ActivityPub template the one that keeps the title out of content warnings, with a content warning field.
 */
class UpdateBlogActivityPubTemplates extends YesWikiMigration
{
    private const RELEASED_TEMPLATE = <<<'TWIG'
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Note",
  "name": {{ bf_titre | json_encode }},
  "preview": {{ bf_chapeau | default("") | json_encode }},
  "content": {{ bf_description | default("") | json_encode }},
  "published": {{ date_creation_fiche | date("c") | json_encode }},
  "updated": {{ date_maj_fiche | default(date_creation_fiche) | date("c") | json_encode }},
  "attachment": [
    {% if imagebf_image %}
    {
      "type": "Image",
      "url": {{ fileUrl(imagebf_image) | json_encode }}
    }
    {% endif %}
  ]
}
TWIG;

    private const LEAD_TEMPLATE = <<<'TWIG'
{% set lead = "<p><strong>" ~ (bf_titre | e) ~ "</strong></p>" ~ (bf_chapeau ? "<p><em>" ~ (bf_chapeau | e) ~ "</em></p>" : "") %}
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Note",
  "name": {{ bf_titre | json_encode }},
  "preview": {{ bf_chapeau | default("") | json_encode }},
  "content": {{ (lead ~ format(bf_description | default(""))) | json_encode }},
  "published": {{ date_creation_fiche | date("c") | json_encode }},
  "updated": {{ date_maj_fiche | default(date_creation_fiche) | date("c") | json_encode }},
  "attachment": [
    {% if imagebf_image %}
    {
      "type": "Image",
      "url": {{ fileUrl(imagebf_image) | json_encode }}
    }
    {% endif %}
  ]
}
TWIG;

    private const RELEASED_REVERSE_TEMPLATE = <<<'TWIG'
{
  "bf_titre": {{ name | json_encode }},
  "bf_chapeau": {{ preview | default("") | json_encode }},
  "bf_description": {{ content | default("") | json_encode }},
  "imagebf_image": {{ (attachment | first).url | default("") | json_encode }}
}
TWIG;

    private const LEAD_REVERSE_TEMPLATE = <<<'TWIG'
{% set lead = "<p><strong>" ~ (name | e) ~ "</strong></p>" ~ (preview ? "<p><em>" ~ (preview | e) ~ "</em></p>" : "") %}
{
  "bf_titre": {{ name | json_encode }},
  "bf_chapeau": {{ preview | default("") | json_encode }},
  "bf_description": {{ markdown(content | default("") | replace({(lead): ""})) | json_encode }},
  "imagebf_image": {{ (attachment | first).url | default("") | json_encode }}
}
TWIG;

    private const DATED_REVERSE_TEMPLATE = <<<'TWIG'
{% set lead = "<p><strong>" ~ (name | e) ~ "</strong></p>" ~ (preview ? "<p><em>" ~ (preview | e) ~ "</em></p>" : "") %}
{
  "bf_titre": {{ name | json_encode }},
  "bf_chapeau": {{ preview | default("") | json_encode }},
  "bf_description": {{ markdown(content | default("") | replace({(lead): ""})) | json_encode }},
  "imagebf_image": {{ (attachment | first).url | default("") | json_encode }},
  "date_creation_fiche": {{ (published ? published | date("Y-m-d H:i:s") : null) | json_encode }},
  "date_maj_fiche": {{ ((updated ?: published) ? (updated ?: published) | date("Y-m-d H:i:s") : null) | json_encode }}
}
TWIG;

    private const FIELD = 'texte***bf_avertissement***Avertissement de contenu***80***255*** *** ***text*** *** ***Si rempli, Mastodon et GoToSocial masquent le billet derrière ce texte*** * *** * *** *** *** ***';

    private const DIR = __DIR__ . '/../../tools/bazar/templates/activitypub/';

    public function run()
    {
        $table = $this->dbService->prefixTable('nature');
        $template = trim(file_get_contents(self::DIR . 'blog-note.json.twig'));
        $reverseTemplate = trim(file_get_contents(self::DIR . 'blog-note.reverse.json.twig'));

        foreach ($this->dbService->loadAll("SELECT `bn_id_nature`, `bn_template`, `bn_sem_template`, `bn_sem_reverse_template` FROM {$table}") as $form) {
            $updates = [];
            if (in_array($this->normalized($form['bn_sem_template']), [self::RELEASED_TEMPLATE, self::LEAD_TEMPLATE], true)) {
                $updates[] = "`bn_sem_template` = '" . $this->dbService->escape($template) . "'";
                $updates[] = "`bn_template` = '" . $this->dbService->escape($this->withWarningField((string)$form['bn_template'])) . "'";
            }
            if (in_array($this->normalized($form['bn_sem_reverse_template']), [self::RELEASED_REVERSE_TEMPLATE, self::LEAD_REVERSE_TEMPLATE, self::DATED_REVERSE_TEMPLATE], true)) {
                $updates[] = "`bn_sem_reverse_template` = '" . $this->dbService->escape($reverseTemplate) . "'";
            }
            if (!empty($updates)) {
                $this->dbService->query("UPDATE {$table} SET " . implode(', ', $updates) . " WHERE `bn_id_nature` = '" . $this->dbService->escape($form['bn_id_nature']) . "'");
            }
        }
    }

    /**
     * The form's fields with the content warning field after the summary, or last when there is no summary.
     */
    private function withWarningField(string $fields): string
    {
        if (str_contains($fields, '***bf_avertissement***')) {
            return $fields;
        }
        $lines = preg_split('/\r\n|\n/', rtrim($fields, "\r\n"));
        $position = count($lines);
        foreach ($lines as $index => $line) {
            if (str_contains($line, '***bf_chapeau***')) {
                $position = $index + 1;
            }
        }
        array_splice($lines, $position, 0, [self::FIELD]);

        return implode("\r\n", $lines) . "\r\n";
    }

    private function normalized(?string $template): string
    {
        return trim(str_replace("\r\n", "\n", (string)$template));
    }
}
