<?php

namespace YesWiki\Render\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\YesWikiController;
use YesWiki\Render\Component\ComponentRegistry;
use YesWiki\Render\Component\ComponentSettings;

/**
 * The editor's component rail as HTML for htmx: the palette, and the settings of one Component, drawn again from the
 * whole state the panel posts on every change, with the tag those settings write.
 */
class ActionsBuilderApiController extends YesWikiController
{
    #[Route('/api/actions-builder/palette', methods: ['GET'], options: ['acl' => ['public']])]
    public function palette(): Response
    {
        $filter = trim((string)$this->getRequest()->query->get('q', ''));
        $needle = mb_strtolower($filter);
        $palette = [];
        foreach ($this->getService(ComponentRegistry::class)->palette() as $group) {
            $groupMatches = $needle === '' || str_contains(mb_strtolower((string)$group['label']), $needle);
            $group['components'] = array_values(array_filter(
                $group['components'],
                static fn (array $component): bool => $groupMatches || str_contains(mb_strtolower(strip_tags((string)$component['label'])), $needle)
            ));
            if ($group['components'] !== []) {
                $palette[] = $group;
            }
        }

        return $this->html($this->render('@core/actions-builder/palette.twig', ['palette' => $palette, 'filter' => $filter]));
    }

    #[Route('/api/actions-builder/settings', methods: ['GET'], options: ['acl' => ['public']])]
    public function open(): Response
    {
        $query = $this->getRequest()->query;
        $settings = $this->getService(ComponentSettings::class);
        $action = (string)$query->get('action', '');
        if ($action !== '') {
            $found = $settings->fromTag($action);
            if ($found === null) {
                return $this->html($this->render('@core/alert-message.twig', ['type' => 'info', 'message' => _t('ACTION_BUILDER_UNKNOWN_COMPONENT')]), Response::HTTP_NOT_FOUND);
            }

            return $this->settings($found['component'], $found['state'], true);
        }
        $component = $settings->component((string)$query->get('component', ''));
        $state = $component === null ? null : $settings->fresh($component['id']);
        if ($component === null || $state === null) {
            return $this->html('', Response::HTTP_NOT_FOUND);
        }

        return $this->settings($component, $state, false);
    }

    #[Route('/api/actions-builder/settings', methods: ['POST'], options: ['acl' => ['public']])]
    public function change(): Response
    {
        $post = $this->getRequest()->request->all();
        $settings = $this->getService(ComponentSettings::class);
        $component = $settings->component((string)($post['component'] ?? ''));
        if ($component === null) {
            return $this->html('', Response::HTTP_NOT_FOUND);
        }

        return $this->settings($component, $settings->fromPost($component, $post), ($post['editing'] ?? '') === '1');
    }

    /**
     * @param array<string, mixed> $component
     * @param array<string, mixed> $state
     */
    private function settings(array $component, array $state, bool $editing): Response
    {
        $source = $this->getRequest()->isMethod('POST') ? $this->getRequest()->request : $this->getRequest()->query;
        $session = preg_replace('/[^a-z0-9]/i', '', (string)$source->get('session', '')) ?: bin2hex(random_bytes(4));

        return $this->html($this->render('@core/actions-builder/settings.twig', $this->getService(ComponentSettings::class)->view($component, $state, $editing) + [
            'editing' => $editing,
            'session' => $session,
            'preview' => (string)$source->get('preview', '1') !== '0',
            'rev' => (int)$source->get('rev', 0) + ((string)$source->get('op', '') === '' ? 0 : 1),
        ]));
    }

    private function html(string $html, int $status = Response::HTTP_OK): Response
    {
        return new Response($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
