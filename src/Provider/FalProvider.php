<?php

declare(strict_types=1);

namespace WordPress\FalAiProvider\Provider;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\FalAiProvider\Metadata\FalModelMetadataDirectory;
use WordPress\FalAiProvider\Models\FalImageGenerationModel;

/**
 * Class for the AI Provider for fal.ai.
 *
 * @since 1.0.0
 */
class FalProvider extends AbstractApiProvider
{
    private const BASE_URL = 'https://queue.fal.run';
    private const PLATFORM_BASE_URL = 'https://api.fal.ai/v1';

    /**
     * Returns the fal.ai queue base URL.
     *
     * @since 1.0.0
     *
     * @return string The base URL for fal.ai queue API.
     */
    protected static function baseUrl(): string
    {
        return self::BASE_URL;
    }

    /**
     * Returns the fal.ai platform base URL.
     *
     * @since 1.0.0
     *
     * @return string The base URL for fal.ai platform API.
     */
    protected static function platformBaseUrl(): string
    {
        return self::PLATFORM_BASE_URL;
    }

    /**
     * Creates a model instance based on the given model metadata.
     *
     * @since 1.0.0
     *
     * @param ModelMetadata   $modelMetadata   The model metadata.
     * @param ProviderMetadata $providerMetadata The provider metadata.
     * @return ModelInterface The model instance.
     * @throws RuntimeException If the model capabilities are unsupported.
     */
    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        $capabilities = $modelMetadata->getSupportedCapabilities();
        foreach ($capabilities as $capability) {
            if ($capability->isImageGeneration()) {
                return new FalImageGenerationModel($modelMetadata, $providerMetadata);
            }
        }

        throw new RuntimeException(
            'Unsupported model capabilities: ' . implode(', ', $capabilities)
        );
    }

    /**
     * Creates the provider metadata for fal.ai.
     *
     * @since 1.0.0
     *
     * @return ProviderMetadata The provider metadata.
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        $providerMetadataArgs = [
            'fal',
            'fal.ai',
            ProviderTypeEnum::cloud(),
            'https://fal.ai/dashboard/keys',
            RequestAuthenticationMethod::apiKey(),
        ];

        if (version_compare(AiClient::VERSION, '1.2.0', '>=')) {
            if (function_exists('__')) {
                $providerMetadataArgs[] = __('Image generation with Flux, Kling, and more.', 'ai-provider-for-fal');
            } else {
                $providerMetadataArgs[] = 'Image generation with Flux, Kling, and more.';
            }
        }

        return new ProviderMetadata(...$providerMetadataArgs);
    }

    /**
     * Creates the provider availability checker.
     *
     * Tests API key validity by calling the fal.ai platform API.
     *
     * @since 1.0.0
     *
     * @return ProviderAvailabilityInterface The provider availability checker.
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        return new FalProviderAvailability();
    }

    /**
     * Creates the model metadata directory with a curated list of models.
     *
     * @since 1.0.0
     *
     * @return ModelMetadataDirectoryInterface The model metadata directory.
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new FalModelMetadataDirectory();
    }

    /**
     * Builds a full URL for the fal.ai queue API.
     *
     * @since 1.0.0
     *
     * @param string $path The API path (typically a model endpoint ID).
     * @return string The full URL.
     */
    public static function url(string $path): string
    {
        return self::BASE_URL . '/' . $path;
    }

    /**
     * Builds a full URL for the fal.ai platform API.
     *
     * @since 1.0.0
     *
     * @param string $path The API path.
     * @return string The full URL.
     */
    public static function platformUrl(string $path): string
    {
        return self::PLATFORM_BASE_URL . '/' . $path;
    }
}
