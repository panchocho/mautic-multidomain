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
        $scheme = $request->getScheme();
        
        // Construct the expected site URL from the current request
        // E.g. https://client-domain.com
        $currentSiteUrl = $scheme . '://' . $host;
        
        // Append port if it's not standard
        $port = $request->getPort();
        if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
            $currentSiteUrl .= ':' . $port;
        }

        // Fetch allowed domains from configuration
        $allowedDomainsString = (string) $this->coreParametersHelper->get('allowed_domains', '');
        if (!empty($allowedDomainsString)) {
            $allowedDomains = array_map('trim', explode(',', $allowedDomainsString));
            if (!in_array($host, $allowedDomains, true)) {
                // If not allowed, fallback to default Mautic site_url
                return;
            }
        }

        // Dynamically override the site_url parameter for this request.
        // This ensures generated URLs, assets, etc. use the requested domain.
        $this->coreParametersHelper->set('site_url', $currentSiteUrl);
    }
}
