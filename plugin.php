<?php

/**
 * Plugin Name: AI Provider for fal.ai
 * Plugin URI: https://github.com/fal-ai/ai-provider-for-fal
 * Description: AI Provider for fal.ai for the WordPress AI Client.
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Version: 1.0.0
 * Author: fal.ai
 * Author URI: https://fal.ai/
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: ai-provider-for-fal
 *
 * @package WordPress\FalAiProvider
 */

declare(strict_types=1);

namespace WordPress\FalAiProvider;

use WordPress\AiClient\AiClient;
use WordPress\FalAiProvider\Provider\FalProvider;

if (!defined('ABSPATH')) {
    return;
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Registers the AI Provider for fal.ai with the AI Client.
 *
 * @since 1.0.0
 *
 * @return void
 */
function register_provider(): void
{
    if (!class_exists(AiClient::class)) {
        return;
    }

    $registry = AiClient::defaultRegistry();

    if ($registry->hasProvider(FalProvider::class)) {
        return;
    }

    $registry->registerProvider(FalProvider::class);
}

add_action('init', __NAMESPACE__ . '\\register_provider', 5);
