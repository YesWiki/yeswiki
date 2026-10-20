<?php

use YesWiki\Core\YesWikiMigration;

/**
 * Gives every form still using the original Agenda ActivityPub templates the ones Mobilizon understands.
 */
class UpdateAgendaActivityPubTemplates extends YesWikiMigration
{
    private const ORIGINAL_TEMPLATE = <<<'TWIG'
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Event",
  "name": {{ bf_titre | json_encode }},
  "content": {{ bf_description | default("") | json_encode }},
  "startTime": {{ bf_date_debut_evenement | json_encode }},
  "endTime": {{ bf_date_fin_evenement | default("") | json_encode }},
  "url": {{ bf_site_internet | json_encode }},
  "published": {{ date_creation_fiche | replace({" ": "T"}) | json_encode }},
  "updated": {{ date_maj_fiche | replace({" ": "T"}) | json_encode }},
  {% if bf_adresse and bf_ville and bf_code_postal %}
  "location": {
    "type": "Place",
    "name": {{ [bf_adresse, bf_ville] | join(", ") | trim(", ") | json_encode }},
    "latitude": {{ geolocation.bf_latitude | json_encode }},
    "longitude": {{ geolocation.bf_longitude | json_encode }},
    "address": {
      "type": "PostalAddress",
      "streetAddress": {{ bf_adresse | default("") | json_encode }},
      "postalCode": {{ bf_code_postal | default("") | json_encode }},
      "addressLocality": {{ bf_ville | default("") | json_encode }}
    }
  },
  {% endif %}
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

    private const ORIGINAL_REVERSE_TEMPLATE = <<<'TWIG'
{
  "bf_titre": {{ name | json_encode }},
  "bf_description": {{ content | default("") | json_encode }},
  "bf_date_debut_evenement": {{ startTime | json_encode }},
  "bf_date_fin_evenement": {{ endTime | default("") | json_encode }},
  "bf_adresse": {{ location.address.streetAddress | default("") | json_encode }},
  "bf_code_postal": {{ location.address.postalCode | default("") | json_encode }},
  "bf_ville": {{ location.address.addressLocality | default("") | json_encode }},
  "bf_site_internet": {{ url | default("") | json_encode }},
  "imagebf_image": {{ (attachment | first).url | default("") | json_encode }},
  "geolocation": {
    "bf_latitude": {{ location.latitude | default("") | json_encode }},
    "bf_longitude": {{ location.longitude | default("") | json_encode }}
  }
}
TWIG;

    private const DIR = __DIR__ . '/../../tools/bazar/templates/activitypub/';

    public function run()
    {
        $table = $this->dbService->prefixTable('nature');
        $template = trim(file_get_contents(self::DIR . 'agenda-event.json.twig'));
        $reverseTemplate = trim(file_get_contents(self::DIR . 'agenda-event.reverse.json.twig'));

        foreach ($this->dbService->loadAll("SELECT `bn_id_nature`, `bn_sem_template`, `bn_sem_reverse_template` FROM {$table}") as $form) {
            $updates = [];
            if ($this->normalized($form['bn_sem_template']) === self::ORIGINAL_TEMPLATE) {
                $updates[] = "`bn_sem_template` = '" . $this->dbService->escape($template) . "'";
            }
            if ($this->normalized($form['bn_sem_reverse_template']) === self::ORIGINAL_REVERSE_TEMPLATE) {
                $updates[] = "`bn_sem_reverse_template` = '" . $this->dbService->escape($reverseTemplate) . "'";
            }
            if (!empty($updates)) {
                $this->dbService->query("UPDATE {$table} SET " . implode(', ', $updates) . " WHERE `bn_id_nature` = '" . $this->dbService->escape($form['bn_id_nature']) . "'");
            }
        }
    }

    private function normalized(?string $template): string
    {
        return trim(str_replace("\r\n", "\n", (string)$template));
    }
}
