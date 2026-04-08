<?php

declare(strict_types=1);

namespace WordPress\FalAiProvider\Provider;

use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;

/**
 * Checks whether the fal.ai provider is configured with an API key.
 *
 * @since 1.0.0
 */
class FalProviderAvailability implements ProviderAvailabilityInterface
{
    /**
     * Returns whether the fal.ai provider is configured with an API key.
     *
     * @since 1.0.0
     *
     * @return bool True if the provider is configured, false otherwise.
     */
    public function isConfigured(): bool
    {
        return $this->getApiKey() !== '';
    }

    /**
     * Returns the API key from environment or constant.
     *
     * @since 1.0.0
     *
     * @return string The API key, or empty string if not set.
     */
    protected function getApiKey(): string
    {
        if (defined('FAL_KEY')) {
            $key = constant('FAL_KEY');
            if (is_string($key) && $key !== '') {
                return $key;
            }
        }

        $envKey = getenv('FAL_KEY');
        if ($envKey !== false && $envKey !== '') {
            return $envKey;
        }

        return '';
    }
}
