<?php
declare(strict_types=1);

namespace MauticPlugin\MauticMultidomainBundle\EventListener;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailBuilderEvent;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\EmailBundle\Helper\MailHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;

class EmailSubscriber implements EventSubscriberInterface
{
    private CoreParametersHelper $coreParametersHelper;

    public function __construct(CoreParametersHelper $coreParametersHelper)
    {
        $this->coreParametersHelper = $coreParametersHelper;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::EMAIL_ON_SEND   => ['onEmailSend', 0],
            EmailEvents::EMAIL_ON_DISPLAY => ['onEmailDisplay', 0],
        ];
    }

    public function onEmailSend(EmailSendEvent $event): void
    {
        $senderDomain = $this->resolveSenderDomain($event);
        if (null === $senderDomain) {
            return;
        }

        $domainMailerConfig = $this->resolveDomainMailerConfig($senderDomain);
        if ([] !== $domainMailerConfig) {
            $this->applyDomainMailerOverrides($event, $domainMailerConfig);

            $configuredFromAddress = $this->readConfigString($domainMailerConfig, 'from_email');
            if (null !== $configuredFromAddress) {
                $configuredFromDomain = $this->extractDomainFromAddress($configuredFromAddress);
                if (null !== $configuredFromDomain) {
                    $senderDomain = $configuredFromDomain;
                }
            }
        }

        $this->rewriteDomains($event, $senderDomain);
    }

    public function onEmailDisplay(EmailBuilderEvent $event): void
    {
        // When viewing the email in the browser
        // Since we already have RequestListener mutating site_url, we might not need this,
        // but we still do it to ensure consistency.
    }

    private function resolveSenderDomain(EmailSendEvent $event): ?string
    {
        $email = $event->getEmail();
        if (!$email) {
            return null;
        }

        // Sender precedence: Email-level FROM, then global Mautic mailer_from_email.
        $fromAddress = $email->getFromAddress() ?: $this->coreParametersHelper->get('mailer_from_email');
        if (empty($fromAddress)) {
            return null;
        }

        return $this->extractDomainFromAddress((string) $fromAddress);
    }

    private function rewriteDomains(EmailSendEvent $event, string $senderDomain): void
    {
        $defaultSiteUrl = (string) $this->coreParametersHelper->get('site_url');
        if ('' === $defaultSiteUrl) {
            return;
        }

        $parsedUrl = parse_url($defaultSiteUrl);
        if (!is_array($parsedUrl) || !isset($parsedUrl['host'])) {
            return;
        }

        $scheme      = (string) ($parsedUrl['scheme'] ?? 'https');
        $defaultHost = (string) $parsedUrl['host'];
        $port        = isset($parsedUrl['port']) ? (int) $parsedUrl['port'] : null;

        if ($senderDomain === $defaultHost) {
            return;
        }

        $content = $event->getContent();
        $plainText = $event->getPlainText();

        $baseToReplace = $scheme . '://' . $defaultHost;
        $newBaseUrl    = $scheme . '://' . $senderDomain;

        if (null !== $port) {
            $baseToReplace .= ':' . $port;
            $newBaseUrl .= ':' . $port;
        }

        if ($content) {
            $content = str_replace($baseToReplace, $newBaseUrl, $content);
            $event->setContent($content);
        }

        if ($plainText) {
            $plainText = str_replace($baseToReplace, $newBaseUrl, $plainText);
            $event->setPlainText($plainText);
        }
    }

    /**
     * Resolve config for exact domain first, then parent domain fallback.
     * Example: sender `mail.news.client.com` can match `news.client.com` or `client.com`.
     *
     * @return array<string, mixed>
     */
    private function resolveDomainMailerConfig(string $senderDomain): array
    {
        $map = $this->getDomainMailerMap();
        if ([] === $map) {
            return [];
        }

        $candidate = strtolower(trim($senderDomain));
        if (isset($map[$candidate])) {
            return $map[$candidate];
        }

        while (false !== ($dotPos = strpos($candidate, '.'))) {
            $candidate = substr($candidate, $dotPos + 1);
            if (isset($map[$candidate])) {
                return $map[$candidate];
            }
        }

        return [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getDomainMailerMap(): array
    {
        $rawConfig = (string) $this->coreParametersHelper->get('domain_mailer_map', '');
        if ('' === trim($rawConfig)) {
            return [];
        }

        try {
            $decoded = json_decode($rawConfig, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $domain => $config) {
            if (!is_string($domain) || !is_array($config)) {
                continue;
            }

            $domain = strtolower(trim($domain));
            if ('' === $domain) {
                continue;
            }

            $normalized[$domain] = $config;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $domainMailerConfig
     */
    private function applyDomainMailerOverrides(EmailSendEvent $event, array $domainMailerConfig): void
    {
        $helper = $event->getHelper();
        if (!$helper instanceof MailHelper) {
            return;
        }

        $mailerDsn = $this->readConfigString($domainMailerConfig, 'mailer_dsn');
        if (null !== $mailerDsn) {
            $this->applyDomainTransport($helper, $mailerDsn);
        }

        $fromEmail = $this->readConfigString($domainMailerConfig, 'from_email');
        if (null !== $fromEmail) {
            $helper->setFrom($fromEmail, $this->readConfigString($domainMailerConfig, 'from_name'));
        }

        $replyTo = $this->readConfigString($domainMailerConfig, 'reply_to');
        if (null !== $replyTo) {
            $helper->setReplyTo($replyTo);
        }

        $returnPath = $this->readConfigString($domainMailerConfig, 'return_path');
        if (null !== $returnPath) {
            $helper->setReturnPath($returnPath);
        }

        if (!isset($domainMailerConfig['headers']) || !is_array($domainMailerConfig['headers'])) {
            return;
        }

        foreach ($domainMailerConfig['headers'] as $headerName => $headerValue) {
            if (!is_string($headerName) || !is_string($headerValue)) {
                continue;
            }

            $headerName = trim($headerName);
            if ('' === $headerName) {
                continue;
            }

            $event->addTextHeader($headerName, $headerValue);
        }
    }

    private function applyDomainTransport(MailHelper $helper, string $dsn): void
    {
        try {
            $newTransport = Transport::fromDsn($dsn);
        } catch (\Throwable) {
            return;
        }

        $currentTransport = $helper->getTransport();
        if (is_object($currentTransport) && method_exists($currentTransport, 'stop')) {
            try {
                $currentTransport->stop();
            } catch (\Throwable) {
                // Ignored intentionally; we'll still attempt to swap transport.
            }
        }

        $this->replaceMainMailerTransport($helper, $newTransport);
    }

    private function replaceMainMailerTransport(MailHelper $helper, TransportInterface $newTransport): void
    {
        try {
            $helperReflection = new \ReflectionClass($helper);

            $mailerProperty = $helperReflection->getProperty('mailer');
            $mailerProperty->setAccessible(true);
            $mailer = $mailerProperty->getValue($helper);

            $mailerReflection = new \ReflectionClass($mailer);
            $transportProperty = $mailerReflection->getProperty('transport');
            $transportProperty->setAccessible(true);
            $transportCollection = $transportProperty->getValue($mailer);

            $collectionReflection = new \ReflectionClass($transportCollection);
            $transportsProperty = $collectionReflection->getProperty('transports');
            $transportsProperty->setAccessible(true);
            $transports = $transportsProperty->getValue($transportCollection);

            if (is_array($transports)) {
                $transports['main'] = $newTransport;
                $transportsProperty->setValue($transportCollection, $transports);
            }

            // Keep MailHelper's cached transport in sync with the swapped transport.
            $helperTransportProperty = $helperReflection->getProperty('transport');
            $helperTransportProperty->setAccessible(true);
            $helperTransportProperty->setValue($helper, $newTransport);
        } catch (\ReflectionException) {
            // Ignore silently to preserve default behavior if internals change.
        }
    }

    /**
     * @param array<string, mixed> $domainMailerConfig
     */
    private function readConfigString(array $domainMailerConfig, string $key): ?string
    {
        if (!isset($domainMailerConfig[$key]) || !is_string($domainMailerConfig[$key])) {
            return null;
        }

        $value = trim($domainMailerConfig[$key]);

        return '' === $value ? null : $value;
    }

    private function extractDomainFromAddress(string $address): ?string
    {
        $address = trim($address);
        if ('' === $address) {
            return null;
        }

        if (preg_match('/<([^>]+)>/', $address, $matches)) {
            $address = trim($matches[1]);
        }

        $parts = explode('@', $address);
        if (count($parts) < 2) {
            return null;
        }

        $domain = strtolower(trim((string) end($parts)));

        return '' === $domain ? null : $domain;
    }
}
