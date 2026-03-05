<?php
declare(strict_types=1);

namespace MauticPlugin\MauticMultidomainBundle\EventListener;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailBuilderEvent;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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
        $this->rewriteDomains($event);
    }

    public function onEmailDisplay(EmailBuilderEvent $event): void
    {
        // When viewing the email in the browser
        // Since we already have RequestListener mutating site_url, we might not need this,
        // but we still do it to ensure consistency.
    }

    private function rewriteDomains(EmailSendEvent $event): void
    {
        $email = $event->getEmail();
        if (!$email) {
            return;
        }

        // Get sender address
        $fromAddress = $email->getFromAddress() ?: $this->coreParametersHelper->get('mailer_from_email');
        if (empty($fromAddress)) {
            return;
        }

        // Extract domain from sender email
        $parts = explode('@', $fromAddress);
        if (count($parts) !== 2) {
            return;
        }
        $senderDomain = $parts[1];

        // Ensure we're replacing the default site_url with the sender's domain (assuming same scheme)
        $defaultSiteUrl = $this->coreParametersHelper->get('site_url');
        $parsedUrl = parse_url($defaultSiteUrl);
        if (!isset($parsedUrl['host'])) {
            return;
        }

        $defaultHost = $parsedUrl['host'];

        // If sender domain is already the default host, no need to rewrite
        if ($senderDomain === $defaultHost) {
            return;
        }

        // Replace occurrences of default host with sender domain in content
        // Note: This is a basic string replacement and might catch other things,
        // but for tracking domains it is typically sufficient.
        // A more robust solution might use regex to only replace within specific URLs.

        $content = $event->getContent();
        $plainText = $event->getPlainText();

        if ($content) {
            // Replace default base URL (e.g. https://default-mautic.com) with the sender's domain
            // maintaining the same schema.
            // Be careful to not replace email addresses or other random occurrences.
            $newBaseUrl = escapeshellcmd((string) $parsedUrl['scheme']) . '://' . $senderDomain;
            if (isset($parsedUrl['port'])) {
                $newBaseUrl .= ':' . $parsedUrl['port'];
            }
            
            $baseToReplace = $parsedUrl['scheme'] . '://' . $defaultHost;
            if (isset($parsedUrl['port'])) {
                $baseToReplace .= ':' . $parsedUrl['port'];
            }

            $content = str_replace($baseToReplace, $newBaseUrl, $content);
            $event->setContent($content);
        }

        if ($plainText) {
            $baseToReplace = $parsedUrl['scheme'] . '://' . $defaultHost;
            $newBaseUrl = $parsedUrl['scheme'] . '://' . $senderDomain;
            if (isset($parsedUrl['port'])) {
                $baseToReplace .= ':' . $parsedUrl['port'];
                $newBaseUrl .= ':' . $parsedUrl['port'];
            }
            $plainText = str_replace($baseToReplace, $newBaseUrl, $plainText);
            $event->setPlainText($plainText);
        }
    }
}
