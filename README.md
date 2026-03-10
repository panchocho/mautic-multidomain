# Mautic Multidomain Bundle

This plugin enables a single Mautic installation (version 7.01+) to handle tracking, landing pages, and emails across multiple client domains without exposing a single centralized domain to all clients.

## Features

* **Dynamic Domain Resolving**: Automatically identifies the incoming HTTP request domain and overrides Mautic's internal URL generation, ensuring landing pages and tracking scripts use the client's specific domain.
* **Email Tracking Rewriting**: Intercepts outgoing emails and rewrites tracking pixels, unsubscribe links, and webviews to match the sender's domain (e.g., if the sender is `info@client.com`, links are rewritten to `https://client.com`).
* **Per-Domain Mailer Overrides**: Optionally applies per-domain `mailer_dsn`, `from_email`, `from_name`, `reply_to`, `return_path`, and custom headers when an email is sent.
* **Security & Allowed Domains**: Includes a configuration panel in Mautic Global Settings where administrators can define a whitelist of allowed domains. Requests from unauthorized domains fall back to the central Mautic URL.

## Requirements

* Mautic 7.01 or later
* PHP 8.1+

## Installation

1. Download or clone this repository.
2. Copy this plugin folder into `plugins/MauticMultidomainBundle/` in your Mautic installation (the folder should contain `Config/`, `EventListener/`, `Form/`, `Translations/`, and `MauticMultidomainBundle.php` at its root).
3. Clear the Mautic cache:
   ```bash
   php bin/console cache:clear
   ```
4. Log into your Mautic admin interface.
5. Navigate to **Settings > Plugins** and click the **Install/Upgrade Plugins** button. The Mautic Multidomain Bundle should appear in the list.

## Configuration

1. Navigate to **Settings > Configuration** in Mautic.
2. Locate the new **Allowed Domains** setting provided by this bundle.
3. Enter the domains you wish to support, separated by commas (e.g., `client1.com, client2.com`).
   * *Note: Only domains listed here will be dynamically resolved. All other domains will fall back to your default site URL.*
4. (Optional) Configure **Domain Mailer Map (JSON)** to define SMTP and sender overrides per sender domain.
   Example:
   ```json
   {
     "client1.com": {
       "mailer_dsn": "smtp://user:pass@smtp.client1.com:587",
       "from_email": "info@client1.com",
       "from_name": "Client 1",
       "reply_to": "reply@client1.com",
       "return_path": "bounce@client1.com",
       "headers": {
         "X-Tenant": "client1"
       }
     },
     "client2.com": {
       "mailer_dsn": "smtps://apikey:secret@smtp.provider.com:465",
       "from_email": "marketing@client2.com"
     }
   }
   ```
   Matching is exact first, then parent-domain fallback (e.g., `mail.news.client.com` can use `news.client.com` or `client.com`).

## Limitations and Important Notes

**Mautic is not inherently multi-tenant.** This plugin allows you to serve content across multiple domains and rewrite tracking URLs, but **all contacts, segments, and tracking data remain in a single shared database.** 

* A contact email address can only exist once across the entire system.
* You must enforce data separation manually via Mautic roles, permissions, and segments if you are hosting multiple independent clients on the same instance.
* This plugin is intended for agencies or businesses that manage multiple internal brands, rather than serving fully isolated clients. For strict data separation, separate Mautic instances are required.
