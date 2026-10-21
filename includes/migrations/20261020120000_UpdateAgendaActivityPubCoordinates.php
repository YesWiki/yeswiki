<?php

use YesWiki\Core\YesWikiMigration;

/**
 * Gives forms still on the previous Agenda ActivityPub templates the ones that read and write the map field's coordinates.
 */
class UpdateAgendaActivityPubCoordinates extends YesWikiMigration
{
    private const PREVIOUS_TEMPLATE = <<<'TWIG'
{% set end = bf_date_fin_evenement | default(bf_date_debut_evenement) %}
{% set end = end | length == 10 ? end ~ " 23:59:59" : end %}
{% set place = [bf_adresse | default(""), bf_code_postal | default(""), bf_ville | default("")] | filter(part => part) %}
{% set extension = imagebf_image | default("") | split(".") | last | lower %}
{% set mediaTypes = {"jpg": "image/jpeg", "jpeg": "image/jpeg", "png": "image/png", "gif": "image/gif", "webp": "image/webp", "avif": "image/avif"} %}
{% set attachment = [] %}
{% if imagebf_image %}
  {% set attachment = attachment | merge([{"type": "Document", "name": "Banner", "mediaType": mediaTypes[extension] ?? "image/jpeg", "url": fileUrl(imagebf_image)}]) %}
{% endif %}
{% if bf_site_internet %}
  {% set attachment = attachment | merge([{"type": "Link", "name": "Website", "mediaType": "text/html", "href": bf_site_internet}]) %}
{% endif %}
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Event",
  "name": {{ bf_titre | json_encode }},
  "content": {{ format(bf_description | default("")) | json_encode }},
  "mediaType": "text/html",
  "startTime": {{ bf_date_debut_evenement | date("c") | json_encode }},
  "endTime": {{ end | date("c") | json_encode }},
  "timezone": {{ "now" | date("e") | json_encode }},
  {% if bf_site_internet %}
  "url": {{ bf_site_internet | json_encode }},
  {% endif %}
  {% if place %}
  "location": {
    "type": "Place",
    "name": {{ place | join(", ") | json_encode }},
    {% if bf_latitude | default("") and bf_longitude | default("") %}
    "latitude": {{ (bf_latitude * 1.0) | json_encode }},
    "longitude": {{ (bf_longitude * 1.0) | json_encode }},
    {% endif %}
    "address": {
      "type": "PostalAddress",
      "streetAddress": {{ bf_adresse | default("") | json_encode }},
      "postalCode": {{ bf_code_postal | default("") | json_encode }},
      "addressLocality": {{ bf_ville | default("") | json_encode }}
    }
  },
  {% endif %}
  "published": {{ date_creation_fiche | date("c") | json_encode }},
  "updated": {{ date_maj_fiche | default(date_creation_fiche) | date("c") | json_encode }},
  "attachment": {{ attachment | json_encode }}
}
TWIG;

    private const PREVIOUS_REVERSE_TEMPLATE = <<<'TWIG'
{% if type == "Event" %}
{% set attachments = attachment is iterable ? attachment : [] %}
{% set media = attachments | filter(item => item.type | default("") in ["Document", "Image"]) %}
{% set banner = media | filter(item => item.name | default("") == "Banner") | first ?: media | first %}
{% set website = attachments | filter(item => item.type | default("") == "Link" and item.name | default("") == "Website") | first %}
{% set place = location is iterable ? location : {"name": location | default("")} %}
{% set address = place.address | default({}) %}
{% set address = address is iterable ? address : {"streetAddress": address} %}
{
  "bf_titre": {{ name | json_encode }},
  "bf_description": {{ markdown(content | default("")) | json_encode }},
  "bf_date_debut_evenement": {{ startTime | date("c") | json_encode }},
  "bf_date_fin_evenement": {{ (endTime ?: startTime) | date("c") | json_encode }},
  "bf_site_internet": {{ (website ? website.href : url | default("")) | json_encode }},
  "bf_adresse": {{ (address.streetAddress | default("") ?: place.name | default("")) | json_encode }},
  "bf_code_postal": {{ address.postalCode | default("") | json_encode }},
  "bf_ville": {{ address.addressLocality | default("") | json_encode }},
  "imagebf_image": {{ (banner ? banner.url | default(banner.href | default("")) : "") | json_encode }},
  "geolocation": {
    "bf_latitude": {{ place.latitude | default("") | json_encode }},
    "bf_longitude": {{ place.longitude | default("") | json_encode }}
  },
  "date_creation_fiche": {{ (published ? published | date("Y-m-d H:i:s") : null) | json_encode }},
  "date_maj_fiche": {{ ((updated ?: published) ? (updated ?: published) | date("Y-m-d H:i:s") : null) | json_encode }}
}
{% else %}
null
{% endif %}
TWIG;

    private const DIR = __DIR__ . '/../../tools/bazar/templates/activitypub/';

    public function run()
    {
        $table = $this->dbService->prefixTable('nature');
        $template = trim(file_get_contents(self::DIR . 'agenda-event.json.twig'));
        $reverseTemplate = trim(file_get_contents(self::DIR . 'agenda-event.reverse.json.twig'));

        foreach ($this->dbService->loadAll("SELECT `bn_id_nature`, `bn_sem_template`, `bn_sem_reverse_template` FROM {$table}") as $form) {
            $updates = [];
            if ($this->normalized($form['bn_sem_template']) === self::PREVIOUS_TEMPLATE) {
                $updates[] = "`bn_sem_template` = '" . $this->dbService->escape($template) . "'";
            }
            if ($this->normalized($form['bn_sem_reverse_template']) === self::PREVIOUS_REVERSE_TEMPLATE) {
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
