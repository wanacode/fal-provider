<?php

declare(strict_types=1);

namespace WordPress\FalAiProvider\Metadata;

use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Curated model metadata directory for fal.ai image generation models.
 *
 * @since 1.0.0
 */
class FalModelMetadataDirectory implements ModelMetadataDirectoryInterface
{
    /**
     * Cached model metadata list.
     *
     * @since 1.0.0
     *
     * @var list<ModelMetadata>|null
     */
    private $cachedModels = null;

    /**
     * Returns the curated list of fal.ai model metadata.
     *
     * @since 1.0.0
     *
     * @return list<ModelMetadata> The model metadata list.
     */
    public function listModelMetadata(): array
    {
        if ($this->cachedModels !== null) {
            return $this->cachedModels;
        }

        $imageOptions = $this->getImageGenerationOptions();

        $this->cachedModels = [
            new ModelMetadata(
                'flux-2-flex',
                'Flux 2 Flex',
                [CapabilityEnum::imageGeneration()],
                $imageOptions
            ),
            new ModelMetadata(
                'flux-2-pro',
                'Flux 2 Pro',
                [CapabilityEnum::imageGeneration()],
                $imageOptions
            ),
            new ModelMetadata(
                'flux-schnell',
                'Flux Schnell',
                [CapabilityEnum::imageGeneration()],
                $imageOptions
            ),
            new ModelMetadata(
                'flux-dev',
                'Flux Dev',
                [CapabilityEnum::imageGeneration()],
                $imageOptions
            ),
            new ModelMetadata(
                'nano-banana-2',
                'Nano Banana 2',
                [CapabilityEnum::imageGeneration()],
                $imageOptions
            ),
            new ModelMetadata(
                'nano-banana-pro',
                'Nano Banana Pro',
                [CapabilityEnum::imageGeneration()],
                $imageOptions
            ),
            new ModelMetadata(
                'flux-2-klein-9b',
                'Flux 2 Klein 9B',
                [CapabilityEnum::imageGeneration()],
                $imageOptions
            ),
            new ModelMetadata(
                'recraft-v4-1-utility',
                'Recraft V4.1 Utility',
                [CapabilityEnum::imageGeneration()],
                $imageOptions
            ),
        ];

        return $this->cachedModels;
    }

    /**
     * Returns whether model metadata exists for the given model ID.
     *
     * @since 1.0.0
     *
     * @param string $modelId The model ID.
     * @return bool True if the model metadata exists, false otherwise.
     */
    public function hasModelMetadata(string $modelId): bool
    {
        foreach ($this->listModelMetadata() as $metadata) {
            if ($metadata->getId() === $modelId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the model metadata for the given model ID.
     *
     * @since 1.0.0
     *
     * @param string $modelId The model ID.
     * @return ModelMetadata The model metadata.
     * @throws \OutOfBoundsException If the model ID is not found.
     */
    public function getModelMetadata(string $modelId): ModelMetadata
    {
        foreach ($this->listModelMetadata() as $metadata) {
            if ($metadata->getId() === $modelId) {
                return $metadata;
            }
        }

        throw new \OutOfBoundsException(
            sprintf('Model metadata for "%s" not found.', $modelId)
        );
    }

    /**
     * Returns the fal.ai endpoint ID for the given model ID.
     *
     * Maps the short model ID used in the metadata to the full
     * fal.ai endpoint path required by the API.
     *
     * @since 1.0.0
     *
     * @param string $modelId The model ID.
     * @return string The fal.ai endpoint ID.
     */
    public static function getEndpointId(string $modelId): string
    {
        $map = [
            'flux-2-flex'     => 'fal-ai/flux-2-flex',
            'flux-2-pro'      => 'fal-ai/flux-2-pro',
            'flux-schnell'    => 'fal-ai/flux/schnell',
            'flux-dev'        => 'fal-ai/flux/dev',
            'nano-banana-2'   => 'fal-ai/nano-banana-2',
            'nano-banana-pro' => 'fal-ai/nano-banana-pro/edit',
            'flux-2-klein-9b' => 'fal-ai/flux-2/klein/9b',
            'recraft-v4-1-utility' => 'fal-ai/recraft/v4.1/utility/text-to-image',
        ];

        if (!isset($map[$modelId])) {
            throw new \InvalidArgumentException(
                sprintf('Unknown fal.ai model ID: "%s".', $modelId)
            );
        }

        return $map[$modelId];
    }

    /**
     * Returns the supported options for image generation models.
     *
     * @since 1.0.0
     *
     * @return list<SupportedOption> The supported options.
     */
    private function getImageGenerationOptions(): array
    {
        return [
            new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::image()]]),
            new SupportedOption(OptionEnum::candidateCount()),
            new SupportedOption(
                OptionEnum::outputMimeType(),
                ['image/png', 'image/jpeg', 'image/webp']
            ),
            new SupportedOption(
                OptionEnum::outputFileType(),
                [FileTypeEnum::remote(), FileTypeEnum::inline()]
            ),
            new SupportedOption(
                OptionEnum::outputMediaOrientation(),
                [
                    MediaOrientationEnum::square(),
                    MediaOrientationEnum::landscape(),
                    MediaOrientationEnum::portrait(),
                ]
            ),
            new SupportedOption(
                OptionEnum::outputMediaAspectRatio(),
                ['1:1', '16:9', '9:16', '4:3', '3:4', '3:2', '2:3']
            ),
            new SupportedOption(OptionEnum::customOptions()),
        ];
    }
}
