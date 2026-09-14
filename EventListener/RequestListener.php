<?php
declare(strict_types=1);

namespace MauticPlugin\MauticMultidomainBundle\EventListener;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class RequestListener implements EventSubscriberInterface
{
    public function __construct(
        private CoreParametersHelper $coreParametersHelper,
        private RouterInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run after Mautic's core router subscriber so allowed domains can override site_url.
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $host    = $this->normalizeDomain($request->getHost());
        if (null === $host) {
            return;
        }

        if (!$this->isAllowedDomain($host)) {
            return;
        }

        $context = $this->router->getContext();
        $context->setScheme($request->getScheme());
        $context->setHost($host);
        $context->setBaseUrl($this->normalizeBaseUrl($request->getBaseUrl()));

        $port = $request->getPort();
        if ('https' === $request->getScheme()) {
            $context->setHttpsPort($port);
        } else {
            $context->setHttpPort($port);
        }
    }

    private function isAllowedDomain(string $host): bool
    {
        $allowedDomains = $this->getAllowedDomains();
        if ([] === $allowedDomains) {
            return false;
        }

        foreach ($allowedDomains as $allowedDomain) {
            if ($host === $allowedDomain || str_ends_with($host, '.'.$allowedDomain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function getAllowedDomains(): array
    {
        $allowedDomainsString = (string) $this->coreParametersHelper->get('allowed_domains', '');
        if ('' === trim($allowedDomainsString)) {
            return [];
        }

        $domains = preg_split('/[\s,]+/', $allowedDomainsString) ?: [];
        $normalizedDomains = [];

        foreach ($domains as $domain) {
            $normalizedDomain = $this->normalizeDomain($domain);
            if (null === $normalizedDomain) {
                continue;
            }

            $normalizedDomains[$normalizedDomain] = $normalizedDomain;
        }

        return array_values($normalizedDomains);
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $normalizedBaseUrl = str_replace('index.php', '', trim($baseUrl));
        if ('/' === $normalizedBaseUrl) {
            return '';
        }

        return rtrim($normalizedBaseUrl, '/');
    }

    private function normalizeDomain(string $domain): ?string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = preg_replace('#/.*$#', '', $domain) ?? $domain;
        $domain = preg_replace('#:\d+$#', '', $domain) ?? $domain;
        $domain = trim($domain, '.');

        return '' === $domain ? null : $domain;
    }
}
