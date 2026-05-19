<?php

declare(strict_types=1);

namespace WordPress\FalAiProvider\Provider;

use Exception;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithHttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Traits\WithHttpTransporterTrait;
use WordPress\AiClient\Providers\Http\Traits\WithRequestAuthenticationTrait;

/**
 * Checks whether the fal.ai provider is configured with a valid API key.
 *
 * Validates the injected API key by making an authenticated GET request to a
 * fal.ai queue status endpoint with a bogus request ID. An invalid key returns
 * 401/403; a valid key returns 404 (request not found) — which still proves
 * authentication succeeded.
 *
 * @since 1.0.0
 */
class FalProviderAvailability implements
    ProviderAvailabilityInterface,
    WithRequestAuthenticationInterface,
    WithHttpTransporterInterface
{
    use WithRequestAuthenticationTrait;
    use WithHttpTransporterTrait;

    private const TEST_URL = 'https://queue.fal.run/fal-ai/flux/dev/requests/00000000-0000-0000-0000-000000000000/status';

    /**
     * Returns whether the fal.ai provider is configured with a valid API key.
     *
     * @since 1.0.0
     *
     * @return bool True if the provider is configured, false otherwise.
     */
    public function isConfigured(): bool
    {
        try {
            $request = new Request(HttpMethodEnum::GET(), self::TEST_URL);
            $request = $this->getRequestAuthentication()->authenticateRequest($request);
            $response = $this->getHttpTransporter()->send($request);

            $status = $response->getStatusCode();

            // 401/403 means the key was rejected. Anything else (including 404
            // for the bogus request ID) means authentication succeeded.
            return $status !== 401 && $status !== 403;
        } catch (Exception $e) {
            return false;
        }
    }
}
