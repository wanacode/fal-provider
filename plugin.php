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

const PREFERRED_IMAGE_SIZE_OPTION = 'fal_preferred_image_size';
const DEFAULT_PREFERRED_IMAGE_SIZE = '';

const IDEOGRAM_RENDERING_SPEED_OPTION = 'fal_ideogram_rendering_speed';
const DEFAULT_IDEOGRAM_RENDERING_SPEED = 'BALANCED';

/**
 * Returns the supported Ideogram V3 rendering speeds.
 *
 * @since 1.1.0
 *
 * @return array<string, string> Map of value => human-readable label.
 */
function get_ideogram_rendering_speeds(): array
{
    return [
        'TURBO'    => __('Turbo (fastest, $0.03)', 'ai-provider-for-fal'),
        'BALANCED' => __('Balanced ($0.06)', 'ai-provider-for-fal'),
        'QUALITY'  => __('Quality (best, $0.09)', 'ai-provider-for-fal'),
    ];
}

/**
 * Returns the preferred Ideogram V3 rendering speed.
 *
 * @since 1.1.0
 *
 * @return string The rendering speed value.
 */
function get_preferred_ideogram_rendering_speed(): string
{
    $value = get_option(IDEOGRAM_RENDERING_SPEED_OPTION, DEFAULT_IDEOGRAM_RENDERING_SPEED);
    if (!is_string($value) || !array_key_exists($value, get_ideogram_rendering_speeds())) {
        return DEFAULT_IDEOGRAM_RENDERING_SPEED;
    }

    return $value;
}

/**
 * Returns the supported fal.ai image_size enum values.
 *
 * Empty string means "auto" — fall back to the orientation/aspect ratio
 * supplied by the AI plugin config.
 *
 * @since 1.0.0
 *
 * @return array<string, string> Map of value => human-readable label.
 */
function get_supported_image_sizes(): array
{
    return [
        ''               => __('Auto', 'ai-provider-for-fal'),
        'square_hd'      => __('Square HD', 'ai-provider-for-fal'),
        'square'         => __('Square', 'ai-provider-for-fal'),
        'portrait_4_3'   => __('Portrait 4:3', 'ai-provider-for-fal'),
        'portrait_16_9'  => __('Portrait 16:9', 'ai-provider-for-fal'),
        'landscape_4_3'  => __('Landscape 4:3', 'ai-provider-for-fal'),
        'landscape_16_9' => __('Landscape 16:9', 'ai-provider-for-fal'),
    ];
}

/**
 * Returns the preferred fal.ai image_size value.
 *
 * Empty string means no preference (use orientation/aspect logic).
 *
 * @since 1.0.0
 *
 * @return string The image size enum value, or '' for auto.
 */
function get_preferred_image_size(): string
{
    $value = get_option(PREFERRED_IMAGE_SIZE_OPTION, DEFAULT_PREFERRED_IMAGE_SIZE);
    if (!is_string($value)) {
        return DEFAULT_PREFERRED_IMAGE_SIZE;
    }

    $supported = get_supported_image_sizes();
    if (!array_key_exists($value, $supported)) {
        return DEFAULT_PREFERRED_IMAGE_SIZE;
    }

    return $value;
}

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
    $currentSize = get_preferred_image_size();
    $currentRenderingSpeed = get_preferred_ideogram_rendering_speed();
    $directory = new FalModelMetadataDirectory();
    $models = $directory->listModelMetadata();
    $imageSizes = get_supported_image_sizes();
    $renderingSpeeds = get_ideogram_rendering_speeds();
    ?>
    <div class="notice notice-info" style="display:flex;align-items:center;gap:10px;padding:10px;">
        <form id="fal-model-picker-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;align-items:center;gap:10px;margin:0;">
            <?php wp_nonce_field('fal_save_preferred_model'); ?>
            <input type="hidden" name="action" value="fal_save_preferred_model">
            <label for="fal-preferred-model"><strong><?php esc_html_e('fal.ai model:', 'ai-provider-for-fal'); ?></strong></label>
            <select name="fal_model" id="fal-preferred-model" onchange="window.falSavePicker && window.falSavePicker();">
                <?php foreach ($models as $model) : ?>
                    <option value="<?php echo esc_attr($model->getId()); ?>" <?php selected($model->getId(), $current); ?>>
                        <?php echo esc_html($model->getName()); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label for="fal-preferred-image-size"><strong><?php esc_html_e('Image size:', 'ai-provider-for-fal'); ?></strong></label>
            <select name="fal_image_size" id="fal-preferred-image-size" onchange="window.falSavePicker && window.falSavePicker();">
                <?php foreach ($imageSizes as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $currentSize); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span id="fal-ideogram-rendering-speed-wrap" style="display:<?php echo $current === 'ideogram-v3' ? 'inline-flex' : 'none'; ?>;align-items:center;gap:10px;">
                <label for="fal-ideogram-rendering-speed"><strong><?php esc_html_e('Rendering speed:', 'ai-provider-for-fal'); ?></strong></label>
                <select name="fal_ideogram_rendering_speed" id="fal-ideogram-rendering-speed" onchange="window.falSavePicker && window.falSavePicker();">
                    <?php foreach ($renderingSpeeds as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $currentRenderingSpeed); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </span>
            <span id="fal-model-picker-status" aria-live="polite" style="color:#646970;font-style:italic;"></span>
            <noscript>
                <button type="submit" class="button button-primary"><?php esc_html_e('Save', 'ai-provider-for-fal'); ?></button>
            </noscript>
        </form>
    </div>
    <script>
    (function () {
        var form = document.getElementById('fal-model-picker-form');
        if (!form) {
            return;
        }
        var status = document.getElementById('fal-model-picker-status');
        var modelSelect = document.getElementById('fal-preferred-model');
        var renderingWrap = document.getElementById('fal-ideogram-rendering-speed-wrap');
        var timer = null;
        function toggleModelOptions() {
            if (renderingWrap && modelSelect) {
                renderingWrap.style.display = (modelSelect.value === 'ideogram-v3') ? 'inline-flex' : 'none';
            }
        }
        if (modelSelect) {
            modelSelect.addEventListener('change', toggleModelOptions);
        }
        window.falSavePicker = function () {
            if (status) {
                status.textContent = <?php echo wp_json_encode(__('Saving…', 'ai-provider-for-fal')); ?>;
            }
            var data = new FormData(form);
            fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            }).then(function (r) {
                return r.json().catch(function () { return { success: r.ok }; });
            }).then(function (json) {
                if (status) {
                    status.textContent = (json && json.success)
                        ? <?php echo wp_json_encode(__('Saved.', 'ai-provider-for-fal')); ?>
                        : <?php echo wp_json_encode(__('Save failed.', 'ai-provider-for-fal')); ?>;
                    if (timer) {
                        clearTimeout(timer);
                    }
                    timer = setTimeout(function () { status.textContent = ''; }, 2000);
                }
            }).catch(function () {
                if (status) {
                    status.textContent = <?php echo wp_json_encode(__('Save failed.', 'ai-provider-for-fal')); ?>;
                }
            });
        };
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            window.falSavePicker();
        });
    })();
    </script>
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
    $isAjax = wp_doing_ajax();

    if (!current_user_can('manage_options')) {
        if ($isAjax) {
            wp_send_json_error(['message' => __('Forbidden.', 'ai-provider-for-fal')], 403);
        }
        wp_die(esc_html__('You do not have permission to do this.', 'ai-provider-for-fal'));
    }

    check_admin_referer('fal_save_preferred_model');

    $model = isset($_POST['fal_model']) ? sanitize_key(wp_unslash($_POST['fal_model'])) : '';

    $directory = new FalModelMetadataDirectory();
    if (!$directory->hasModelMetadata($model)) {
        $model = DEFAULT_PREFERRED_MODEL;
    }

    update_option(PREFERRED_MODEL_OPTION, $model);

    $imageSize = isset($_POST['fal_image_size']) ? sanitize_key(wp_unslash($_POST['fal_image_size'])) : '';
    $supportedSizes = get_supported_image_sizes();
    if (!array_key_exists($imageSize, $supportedSizes)) {
        $imageSize = DEFAULT_PREFERRED_IMAGE_SIZE;
    }

    update_option(PREFERRED_IMAGE_SIZE_OPTION, $imageSize);

    $renderingSpeed = isset($_POST['fal_ideogram_rendering_speed'])
        ? strtoupper(sanitize_key(wp_unslash($_POST['fal_ideogram_rendering_speed'])))
        : '';
    if (!array_key_exists($renderingSpeed, get_ideogram_rendering_speeds())) {
        $renderingSpeed = DEFAULT_IDEOGRAM_RENDERING_SPEED;
    }

    update_option(IDEOGRAM_RENDERING_SPEED_OPTION, $renderingSpeed);

    if ($isAjax) {
        wp_send_json_success([
            'model'            => $model,
            'image_size'       => $imageSize,
            'rendering_speed'  => $renderingSpeed,
        ]);
    }

    wp_safe_redirect(
        add_query_arg('fal_model_updated', '1', admin_url('upload.php?page=generate-image'))
    );
    exit;
}

add_action('admin_post_fal_save_preferred_model', __NAMESPACE__ . '\\handle_save_preferred_model');
add_action('wp_ajax_fal_save_preferred_model', __NAMESPACE__ . '\\handle_save_preferred_model');
