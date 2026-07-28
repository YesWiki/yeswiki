<?php

namespace YesWiki\Core\Service;

/**
 * Renders the banner shown while the wiki is hibernated.
 *
 * Split out of HibernationService by wave-two ticket 04. The status check itself sits at
 * the bottom of the dependency graph -- most core services ask it -- so it must not depend
 * on the template engine. This class may: nothing in the rendering chain depends on it,
 * only actions and handlers at the edge of the graph do.
 */
class HibernationNotice
{
    protected HibernationService $hibernationService;
    protected TemplateEngine $templateEngine;

    public function __construct(HibernationService $hibernationService, TemplateEngine $templateEngine)
    {
        $this->hibernationService = $hibernationService;
        $this->templateEngine = $templateEngine;
    }

    /**
     * get alert message when hibernated.
     */
    public function getMessageWhenHibernated(): string
    {
        return $this->templateEngine->render('@core/alert-message-with-back.twig', [
            'type' => 'info',
            'message' => $this->hibernationService->getHibernationMessageText(),
        ]);
    }
}
