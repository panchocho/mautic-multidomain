<?php
declare(strict_types=1);

namespace MauticPlugin\MauticMultidomainBundle\EventListener;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\PageBundle\Event\TrackingEvent;
use Mautic\PageBundle\PageEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TrackingSubscriber implements EventSubscriberInterface
{
    private CoreParametersHelper $coreParametersHelper;

    public function __construct(CoreParametersHelper $coreParametersHelper)
    {
        $this->coreParametersHelper = $coreParametersHelper;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PageEvents::PAGE_ON_TRACKING => ['onPageTracking', 0],
        ];
    }

    public function onPageTracking(TrackingEvent $event): void
    {
        // Tracking logic is natively handled same-origin via the RequestListener overrides.
        // This subscriber is maintained for future extensibility if complex CORS or cookie rewriting
        // becomes necessary across cross-domain tracking boundaries.
    }
}
