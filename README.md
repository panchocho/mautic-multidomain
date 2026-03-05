# Mautic Multidomain Bundle

This plugin enables a single Mautic installation (version 7.01+) to handle tracking, landing pages, and emails across multiple client domains without exposing a single centralized domain to all clients.

## Features

* **Dynamic Domain Resolving**: Automatically identifies the incoming HTTP request domain and overrides Mautic's internal URL generation, ensuring landing pages and tracking scripts use the client's specific domain.
* **Email Tracking Rewriting**: Intercepts outgoing emails and rewrites tracking pixels, unsubscribe links, and webviews to match the sender's domain (e.g., if the sender is `info@client.com`, links are rewritten to `https://client.com`).
* **Security & Allowed Domains**: Includes a configuration panel in Mautic Global Settings where administrators can define a whitelist of allowed domains. Requests from unauthorized domains fall back to the central Mautic URL.

## Requirements

* Mautic 7.01 or later
* PHP 8.1+

## Installation

1. Download or clone this repository.
2. Move the `MauticMultidomainBundle` directory into the `plugins/` folder of your Mautic installation.
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

## Limitations and Important Notes

**Mautic is not inherently multi-tenant.** This plugin allows you to serve content across multiple domains and rewrite tracking URLs, but **all contacts, segments, and tracking data remain in a single shared database.** 

* A contact email address can only exist once across the entire system.
* You must enforce data separation manually via Mautic roles, permissions, and segments if you are hosting multiple independent clients on the same instance.
* This plugin is intended for agencies or businesses that manage multiple internal brands, rather than serving fully isolated clients. For strict data separation, separate Mautic instances are required.
