<?php

declare(strict_types=1);

namespace WordPress\FalAiProvider\Models;

use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\ImageGeneration\Contracts\ImageGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\FalAiProvider\Metadata\FalModelMetadataDirectory;
use WordPress\FalAiProvider\Provider\FalProvider;

/**
 * Image generation model for fal.ai using the queue-based API.
 *
 * Uses fal.ai's recommended queue pattern: submit → poll → retrieve.
 *
 * @since 1.0.0
 *
 * @phpstan-type QueueSubmitResponse array{request_id: string, response_url: string, status_url: string, cancel_url: string}
 * @phpstan-type QueueStatusResponse array{status: string, request_id: string, response_url?: string, queue_position?: int}
 * @phpstan-type ImageFileData array{url: string, width?: int, height?: int, content_type?: string, file_name?: string, file_size?: int}
 * @phpstan-type ImageGenerationResponse array{images: list<ImageFileData>, prompt?: string, seed?: int, has_nsfw_concepts?: list<bool>}
 */
class FalImageGenerationModel extends AbstractApiBasedModel implements ImageGenerationModelInterface
{
    private const POLL_INTERVAL_SECONDS = 2;
    private const MAX_POLL_DURATION_SECONDS = 180;

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function generateImageResult(array $prompt): GenerativeAiResult
    {
        $httpTransporter = $this->getHttpTransporter();
        $params = $this->prepareGenerateImageParams($prompt);
        $endpointId = FalModelMetadataDirectory::getEndpointId($this->metadata()->getId());

        $submitRequest = $this->createAuthenticatedRequest(
            HttpMethodEnum::POST(),
            $endpointId,
            ['Content-Type' => 'application/json'],
            $params
        );

        $submitResponse = $httpTransporter->send($submitRequest);
        ResponseUtil::throwIfNotSuccessful($submitResponse);

        $submitData = $submitResponse->getData();
        $requestId = $submitData['request_id'] ?? null;
        $responseUrl = $submitData['response_url'] ?? null;
        $statusUrl = $submitData['status_url'] ?? null;

        if (!is_string($requestId) || !is_string($responseUrl) || !is_string($statusUrl)) {
            throw ResponseException::fromMissingData(
                $this->providerMetadata()->getName(),
                'request_id, response_url, or status_url'
            );
        }

        $resultData = $this->pollUntilComplete($statusUrl, $responseUrl, $httpTransporter);

        return $this->parseImageResponseToResult($resultData, $requestId);
    }

    /**
     * Prepares the parameters for the fal.ai image generation API request.
     *
     * @since 1.0.0
     *
     * @param list<Message> $prompt The prompt messages.
     * @return array<string, mixed> The API request parameters.
     */
    protected function prepareGenerateImageParams(array $prompt): array
    {
        $config = $this->getConfig();
        $promptText = $this->extractPromptText($prompt);

        $params = [
            'prompt' => $promptText,
        ];

        $candidateCount = $config->getCandidateCount();
        if ($candidateCount !== null && $candidateCount > 1) {
            $params['num_images'] = $candidateCount;
        }

        $outputMimeType = $config->getOutputMimeType();
        if ($outputMimeType !== null) {
            $params['output_format'] = $this->mapMimeTypeToFormat($outputMimeType);
        }

        $orientation = $config->getOutputMediaOrientation();
        $aspectRatio = $config->getOutputMediaAspectRatio();
        $imageSize = $this->prepareImageSizeParam($orientation, $aspectRatio);
        if ($imageSize !== null) {
            $params['image_size'] = $imageSize;
        }

        $customOptions = $config->getCustomOptions();
        foreach ($customOptions as $key => $value) {
            if (!isset($params[$key])) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * Extracts the prompt text from the prompt messages.
     *
     * @since 1.0.0
     *
     * @param list<Message> $messages The prompt messages.
     * @return string The extracted prompt text.
     * @throws \InvalidArgumentException If no text content is found.
     */
    protected function extractPromptText(array $messages): string
    {
        $lastUserMessage = null;
        foreach (array_reverse($messages) as $message) {
            if ($message->getRole()->isUser()) {
                $lastUserMessage = $message;
                break;
            }
        }

        if ($lastUserMessage === null && !empty($messages)) {
            $lastUserMessage = $messages[count($messages) - 1];
        }

        if ($lastUserMessage === null) {
            throw new \InvalidArgumentException('No prompt text provided.');
        }

        foreach ($lastUserMessage->getParts() as $part) {
            if ($part->getType()->isText()) {
                $text = $part->getText();
                if ($text !== null && $text !== '') {
                    return $text;
                }
            }
        }

        throw new \InvalidArgumentException('No text content found in prompt messages.');
    }

    /**
     * Maps a MIME type to a fal.ai output format string.
     *
     * @since 1.0.0
     *
     * @param string $mimeType The MIME type.
     * @return string The fal.ai output format.
     */
    protected function mapMimeTypeToFormat(string $mimeType): string
    {
        $map = [
            'image/png'  => 'png',
            'image/jpeg' => 'jpeg',
            'image/webp' => 'webp',
        ];

        return $map[$mimeType] ?? 'png';
    }

    /**
     * Prepares the image_size parameter based on orientation and aspect ratio.
     *
     * fal.ai models accept different image size formats. FLUX models use
     * preset strings like 'landscape_16_9', while others accept 'width' and
     * 'height' keys or aspect ratio strings.
     *
     * @since 1.0.0
     *
     * @param MediaOrientationEnum|null $orientation The desired orientation.
     * @param string|null               $aspectRatio The desired aspect ratio.
     * @return string|array<string, int>|null The image size parameter, or null for default.
     */
    protected function prepareImageSizeParam(?MediaOrientationEnum $orientation, ?string $aspectRatio)
    {
        if ($aspectRatio !== null) {
            $sizeMap = [
                '1:1'   => 'square_hd',
                '16:9'  => 'landscape_16_9',
                '9:16'  => 'portrait_16_9',
                '4:3'   => 'landscape_4_3',
                '3:4'   => 'portrait_4_3',
                '3:2'   => 'landscape_4_3',
                '2:3'   => 'portrait_4_3',
            ];

            if (isset($sizeMap[$aspectRatio])) {
                return $sizeMap[$aspectRatio];
            }
        }

        if ($orientation !== null) {
            if ($orientation->isLandscape()) {
                return 'landscape_16_9';
            }
            if ($orientation->isPortrait()) {
                return 'portrait_16_9';
            }
            return 'square_hd';
        }

        return null;
    }

    /**
     * Polls the fal.ai queue status endpoint until the request is complete.
     *
     * @since 1.0.0
     *
     * @param string                                          $statusUrl   The status URL from the submit response.
     * @param string                                          $responseUrl The response URL from the submit response.
     * @param \WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface $httpTransporter The HTTP transporter.
     * @return array<string, mixed> The result data.
     * @throws \RuntimeException If polling times out or the request fails.
     */
    protected function pollUntilComplete(
        string $statusUrl,
        string $responseUrl,
        $httpTransporter
    ): array {
        $this->validateUrl($statusUrl);
        $this->validateUrl($responseUrl);

        $startTime = time();
        $maxTime = min(self::MAX_POLL_DURATION_SECONDS, $this->getMaxExecutionTime());

        while (true) {
            $elapsed = time() - $startTime;
            if ($elapsed >= $maxTime) {
                throw new \RuntimeException(
                    sprintf(
                        'fal.ai request timed out after %d seconds.',
                        $elapsed
                    )
                );
            }

            $statusRequest = $this->createAuthenticatedUrlRequest(
                HttpMethodEnum::GET(),
                $statusUrl
            );

            $statusResponse = $httpTransporter->send($statusRequest);
            ResponseUtil::throwIfNotSuccessful($statusResponse);

            $statusData = $statusResponse->getData();
            $status = $statusData['status'] ?? '';

            if ($status === 'COMPLETED') {
                $resultRequest = $this->createAuthenticatedUrlRequest(
                    HttpMethodEnum::GET(),
                    $responseUrl
                );

                $resultResponse = $httpTransporter->send($resultRequest);
                ResponseUtil::throwIfNotSuccessful($resultResponse);

                return $resultResponse->getData();
            }

            if ($status === 'FAILED') {
                $errorDetail = $statusData['error'] ?? 'Unknown error';
                throw new \RuntimeException(
                    sprintf('fal.ai request failed: %s', is_string($errorDetail) ? $errorDetail : json_encode($errorDetail))
                );
            }

            usleep(self::POLL_INTERVAL_SECONDS * 1000000);
        }
    }

    /**
     * Returns the effective max execution time for polling.
     *
     * Accounts for PHP's max_execution_time and WordPress's time limits,
     * leaving a buffer so the request can return an error rather than
     * being killed mid-process.
     *
     * @since 1.0.0
     *
     * @return int The maximum polling duration in seconds.
     */
    protected function getMaxExecutionTime(): int
    {
        $phpLimit = (int) ini_get('max_execution_time');
        if ($phpLimit > 0 && $phpLimit < self::MAX_POLL_DURATION_SECONDS) {
            return max($phpLimit - 5, 10);
        }

        return self::MAX_POLL_DURATION_SECONDS;
    }

    /**
     * Validates that a URL is a legitimate fal.ai API URL.
     *
     * Prevents SSRF by ensuring status and response URLs returned by the
     * fal.ai queue API point to the expected fal.ai domain.
     *
     * @since 1.0.0
     *
     * @param string $url The URL to validate.
     * @throws \InvalidArgumentException If the URL is not a valid fal.ai URL.
     */
    protected function validateUrl(string $url): void
    {
        $parsed = parse_url($url);
        if ($parsed === false || !isset($parsed['host'])) {
            throw new \InvalidArgumentException(
                sprintf('Invalid URL: %s', $url)
            );
        }

        $host = $parsed['host'];
        $allowedHosts = [
            'queue.fal.run',
            'fal.run',
            'api.fal.ai',
            'fal.ai',
            'v3.fal.media',
            'v3b.fal.media',
            'fal.media',
        ];

        $isValid = false;
        foreach ($allowedHosts as $allowedHost) {
            $suffix = '.' . $allowedHost;
            if ($host === $allowedHost || substr($host, -strlen($suffix)) === $suffix) {
                $isValid = true;
                break;
            }
        }

        if (!$isValid) {
            throw new \InvalidArgumentException(
                sprintf('URL host "%s" is not a valid fal.ai domain.', $host)
            );
        }

        $scheme = $parsed['scheme'] ?? '';
        if ($scheme !== 'https') {
            throw new \InvalidArgumentException(
                sprintf('URL must use HTTPS: %s', $url)
            );
        }
    }

    /**
     * Parses the fal.ai image generation response into a GenerativeAiResult.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $responseData The response data from fal.ai.
     * @param string               $requestId     The request ID from the queue submission.
     * @return GenerativeAiResult The parsed result.
     */
    protected function parseImageResponseToResult(array $responseData, string $requestId): GenerativeAiResult
    {
        $images = $responseData['images'] ?? [];
        if (!is_array($images) || empty($images)) {
            throw ResponseException::fromMissingData(
                $this->providerMetadata()->getName(),
                'images'
            );
        }

        $outputFileType = $this->getConfig()->getOutputFileType();
        $candidates = [];

        foreach ($images as $imageData) {
            $file = $this->createFileFromImageData($imageData, $outputFileType);
            $part = new MessagePart($file);
            $message = new Message(MessageRoleEnum::model(), [$part]);
            $candidates[] = new Candidate($message, FinishReasonEnum::stop());
        }

        $additionalData = $responseData;
        unset($additionalData['images']);

        return new GenerativeAiResult(
            $requestId,
            $candidates,
            new TokenUsage(0, 0, 0),
            $this->providerMetadata(),
            $this->metadata(),
            $additionalData
        );
    }

    /**
     * Creates a File DTO from fal.ai image response data.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $imageData     The image data from the fal.ai response.
     * @param FileTypeEnum|null    $outputFileType The desired output file type.
     * @return File The file DTO.
     */
    protected function createFileFromImageData(array $imageData, ?FileTypeEnum $outputFileType): File
    {
        $url = $imageData['url'] ?? '';
        $contentType = $imageData['content_type'] ?? 'image/png';

        if ($outputFileType !== null && $outputFileType->isInline()) {
            $imageContent = $this->downloadImageContent($url);
            $base64 = base64_encode($imageContent);
            $dataUri = 'data:' . $contentType . ';base64,' . $base64;

            return File::fromDataUri($dataUri);
        }

        return File::fromUrl($url, $contentType);
    }

    /**
     * Downloads image content from a URL.
     *
     * @since 1.0.0
     *
     * @param string $url The URL to download from.
     * @return string The image content as binary string.
     * @throws \RuntimeException If the download fails.
     */
    protected function downloadImageContent(string $url): string
    {
        $this->validateUrl($url);

        $httpTransporter = $this->getHttpTransporter();

        $request = new Request(
            HttpMethodEnum::GET(),
            $url
        );

        $response = $httpTransporter->send($request);

        $body = $response->getBody();
        if ($body === null || $body === '') {
            throw new \RuntimeException(
                sprintf('Failed to download image from %s.', $url)
            );
        }

        return $body;
    }

    /**
     * Creates an authenticated API request using a fal.ai endpoint ID.
     *
     * Prepends the fal.ai queue base URL to the endpoint ID.
     * The request authentication adds the Authorization header.
     *
     * @since 1.0.0
     *
     * @param HttpMethodEnum    $method  The HTTP method.
     * @param string            $endpointId The fal.ai endpoint ID (e.g. 'fal-ai/flux-2-flex').
     * @param array<string, list<string>|string> $headers The request headers.
     * @param array<string, mixed>|string|null   $data    The request body data.
     * @return Request The authenticated request.
     */
    protected function createAuthenticatedRequest(
        HttpMethodEnum $method,
        string $endpointId,
        array $headers = [],
        $data = null
    ): Request {
        $url = FalProvider::url($endpointId);

        $request = new Request(
            $method,
            $url,
            $headers,
            $data,
            $this->getRequestOptions()
        );

        return $this->getRequestAuthentication()->authenticateRequest($request);
    }

    /**
     * Creates an authenticated API request using an absolute URL.
     *
     * Used for polling status URLs and retrieving results, which are
     * returned as absolute URLs by the fal.ai queue API.
     *
     * @since 1.0.0
     *
     * @param HttpMethodEnum $method  The HTTP method.
     * @param string         $url     The absolute URL.
     * @return Request The authenticated request.
     */
    protected function createAuthenticatedUrlRequest(
        HttpMethodEnum $method,
        string $url
    ): Request {
        $request = new Request(
            $method,
            $url,
            [],
            null,
            $this->getRequestOptions()
        );

        return $this->getRequestAuthentication()->authenticateRequest($request);
    }
}
