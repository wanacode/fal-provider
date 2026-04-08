<?php

/**
 * Uninstall handler for AI Provider for fal.ai.
 *
 * Removes plugin options and cleans up. Does not remove
 * the FAL_KEY constant or environment variable, as those
 * are managed outside the plugin.
 *
 * @since 1.0.0
 *
 * @package WordPress\FalAiProvider
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

