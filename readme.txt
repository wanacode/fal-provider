=== AI Provider for fal.ai ===
Contributors: falai
Tags: ai, fal, image-generation, connector, flux
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://spdx.org/licenses/GPL-2.0-or-later.html

AI Provider for fal.ai for the WordPress AI Client.

== Description ==

This plugin provides fal.ai integration for the WordPress AI Client. It enables WordPress sites to use fal.ai's image generation models including Flux and Nano Banana.

**Features:**

* Image generation with Flux 2 Flex, Flux 2 Pro, Flux Schnell, Flux Dev, Nano Banana 2, and Nano Banana Pro
* Queue-based inference with automatic polling (fal.ai best practices)
* Automatic provider registration with the WordPress AI Client
* Configurable via the FAL_KEY environment variable or the Connectors settings screen

**Requirements:**

* PHP 7.4 or higher
* WordPress 7.0 or higher
* fal.ai API key

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-provider-for-fal/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your fal.ai API key via the `FAL_KEY` environment variable or the **Settings > Connectors** screen

== Frequently Asked Questions ==

= How do I get a fal.ai API key? =

Visit the [fal.ai dashboard](https://fal.ai/dashboard/keys) to create an account and generate an API key.

= Does this plugin work without the WordPress AI Client? =

No, this plugin requires the WordPress AI Client which is built into WordPress 7.0 and above.

= Which models are supported? =

This plugin supports image generation with the following fal.ai models:
* Flux 2 Flex
* Flux 2 Pro
* Flux Schnell
* Flux Dev
* Nano Banana 2
* Nano Banana Pro

== Changelog ==

= 1.0.0 =
* Initial release
* Image generation support with Flux and Nano Banana models
* Queue-based inference with automatic polling
