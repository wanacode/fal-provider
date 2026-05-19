<?php

declare(strict_types=1);

namespace WordPress\FalAiProvider\Authentication;

use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;

/**
 * HTTP request authentication for fal.ai.
 *
 * fal.ai expects "Authorization: Key <api_key>" rather than the standard
 * "Bearer <token>" scheme used by ApiKeyRequestAuthentication.
 *
 * @since 1.0.0
 */
class FalApiKeyRequestAuthentication extends ApiKeyRequestAuthentication
{
    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function authenticateRequest(Request $request): Request
    {
        return $request->withHeader('Authorization', 'Key ' . $this->apiKey);
    }
}
