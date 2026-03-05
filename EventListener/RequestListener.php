<?php
declare(strict_types=1);

namespace MauticPlugin\MauticMultidomainBundle\EventListener;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestListener implements EventSubscriberInterface
{
    private CoreParametersHelper $coreParametersHelper;

    public function __construct(CoreParametersHelper $coreParametersHelper)
    {
        $this->coreParametersHelper = $coreParametersHelper;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 255], // High priority to catch early
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $host = $request->getHost();

        // Fetch allowed domains from configuration
        $allowedDomainsString = (string) $this->coreParametersHelper->get('allowed_domains', '');
        if (!empty($allowedDomainsString)) {
            $allowedDomains = array_map('trim', explode(',', $allowedDomainsString));
            if (!in_array($host, $allowedDomains, true)) {
                // If not allowed, fallback to default Mautic site_url
                return;
            }
        }

        // CoreParametersHelper is read-only in Mautic 7, so runtime mutation of site_url
        // is not supported through this service. Keeping the request validation only avoids
        // fatal errors while preserving normal host-based URL generation from Symfony request context.
    }
}
