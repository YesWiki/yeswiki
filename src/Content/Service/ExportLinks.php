<?php

namespace YesWiki\Content\Service;

use YesWiki\Kernel\Service\UrlFormatter;

/** The download formats a list offers, each carrying the search it was made from. */
class ExportLinks
{
    public function __construct(
        private readonly UrlFormatter $urlFormatter,
        private readonly FormOverview $formOverview,
    ) {
    }

    /**
     * @param array<string, mixed>  $form
     * @param array<string, string> $params the query to carry: keywords, order, field
     *
     * @return list<array{label: string, href: string, icon: string}>
     */
    public function forForm(array $form, array $params = []): array
    {
        $key = (string)$form['id'];
        $facts = $this->formOverview->one($form);
        $params = array_filter($params, static fn (string $value): bool => $value !== '');

        $links = [
            $this->link('BAZ_RSS', $this->urlFormatter->href('', 'api/entries/rss', ['id' => $key] + $params, false), 'rss'),
            $this->link('CSV', $this->urlFormatter->href('forms/' . $key . '/entries/csv', 'api', $params, false)),
            $this->link('JSON', $this->urlFormatter->href('forms/' . $key . '/entries', 'api', $params, false)),
        ];
        if ($facts['isSemantic']) {
            $links[] = $this->link('JSON-LD', $this->urlFormatter->href('forms/' . $key . '/entries/json-ld', 'api', $params, false));
        }
        if ($facts['isGeo']) {
            $links[] = $this->link('GeoJSON', $this->urlFormatter->href('forms/' . $key . '/entries/geojson', 'api', $params, false));
        }
        if ($facts['isDate']) {
            $links[] = $this->link('iCal', $this->urlFormatter->href('forms/' . $key . '/entries/ical', 'api', $params, false));
        }
        if ($facts['isActivityPubEnabled']) {
            $links[] = $this->link('ActivityPub', '/actors/' . $key);
        }

        return $links;
    }

    /**
     * Every form's entries at once, which is what a search across the wiki can offer.
     *
     * @param array<string, string> $params
     *
     * @return list<array{label: string, href: string, icon: string}>
     */
    public function forEntries(array $params = []): array
    {
        $params = array_filter($params, static fn (string $value): bool => $value !== '');

        return [
            $this->link('BAZ_RSS', $this->urlFormatter->href('', 'api/entries/rss', $params, false), 'rss'),
            $this->link('CSV', $this->urlFormatter->href('entries/csv', 'api', $params, false)),
            $this->link('JSON', $this->urlFormatter->href('entries', 'api', $params, false)),
        ];
    }

    /** @return array{label: string, href: string, icon: string} */
    private function link(string $label, string $href, string $icon = ''): array
    {
        return ['label' => str_starts_with($label, 'BAZ_') ? _t($label) : $label, 'href' => $href, 'icon' => $icon];
    }
}
