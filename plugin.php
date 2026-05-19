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
use WordPress\FalAiProvider\Metadata\FalModelMetadataDirectory;
use WordPress\FalAiProvider\Provider\FalProvider;

if (!defined('ABSPATH')) {
    return;
}

require_once __DIR__ . '/src/autoload.php';

const PREFERRED_MODEL_OPTION = 'fal_preferred_image_model';
const DEFAULT_PREFERRED_MODEL = 'flux-2-klein-9b';

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

/**
 * Returns the fal.ai model ID to prefer for image generation.
 *
 * Reads the site option and validates against the model directory; falls back
 * to DEFAULT_PREFERRED_MODEL when unset or invalid.
 *
 * @since 1.0.0
 *
 * @return string The model ID.
 */
function get_preferred_image_model(): string
{
    $value = get_option(PREFERRED_MODEL_OPTION, DEFAULT_PREFERRED_MODEL);
    if (!is_string($value) || $value === '') {
        return DEFAULT_PREFERRED_MODEL;
    }

    $directory = new FalModelMetadataDirectory();
    if (!$directory->hasModelMetadata($value)) {
        return DEFAULT_PREFERRED_MODEL;
    }

    return $value;
}

/**
 * Prepends the preferred fal.ai image model to the AI plugin's image model list.
 *
 * @since 1.0.0
 *
 * @param array<int, array{string, string}> $models The preferred image models.
 * @return array<int, array{string, string}> The filtered list with fal prepended.
 */
function prepend_preferred_image_model(array $models): array
{
    array_unshift($models, ['fal', get_preferred_image_model()]);
    return $models;
}

add_filter('wpai_preferred_image_models', __NAMESPACE__ . '\\prepend_preferred_image_model');

/**
 * Renders the model picker above the Generate Image admin page.
 *
 * @since 1.0.0
 *
 * @return void
 */
function render_model_picker(): void
{
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'media_page_generate-image') {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_GET['fal_model_updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>'
            . esc_html__('fal.ai model preference saved.', 'ai-provider-for-fal')
            . '</p></div>';
    }

    $current = get_preferred_image_model();
    $directory = new FalModelMetadataDirectory();
    $models = $directory->listModelMetadata();
    ?>
    <div class="notice notice-info" style="display:flex;align-items:center;gap:10px;padding:10px;">
        <form id="fal-model-picker-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;align-items:center;gap:10px;margin:0;">
            <?php wp_nonce_field('fal_save_preferred_model'); ?>
            <input type="hidden" name="action" value="fal_save_preferred_model">
            <label for="fal-preferred-model"><strong><?php esc_html_e('fal.ai model:', 'ai-provider-for-fal'); ?></strong></label>
            <select name="fal_model" id="fal-preferred-model" onchange="document.getElementById('fal-model-picker-form').submit();">
                <?php foreach ($models as $model) : ?>
                    <option value="<?php echo esc_attr($model->getId()); ?>" <?php selected($model->getId(), $current); ?>>
                        <?php echo esc_html($model->getName()); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript>
                <button type="submit" class="button button-primary"><?php esc_html_e('Save', 'ai-provider-for-fal'); ?></button>
            </noscript>
        </form>
    </div>
    <?php
}

add_action('admin_notices', __NAMESPACE__ . '\\render_model_picker');

/**
 * Handles saving the preferred fal.ai model from the Generate Image page.
 *
 * @since 1.0.0
 *
 * @return void
 */
function handle_save_preferred_model(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to do this.', 'ai-provider-for-fal'));
    }

    check_admin_referer('fal_save_preferred_model');

    $model = isset($_POST['fal_model']) ? sanitize_key(wp_unslash($_POST['fal_model'])) : '';

    $directory = new FalModelMetadataDirectory();
    if (!$directory->hasModelMetadata($model)) {
        $model = DEFAULT_PREFERRED_MODEL;
    }

    update_option(PREFERRED_MODEL_OPTION, $model);

    wp_safe_redirect(
        add_query_arg('fal_model_updated', '1', admin_url('upload.php?page=generate-image'))
    );
    exit;
}

add_action('admin_post_fal_save_preferred_model', __NAMESPACE__ . '\\handle_save_preferred_model');
