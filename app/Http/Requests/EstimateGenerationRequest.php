<?php

namespace App\Http\Requests;

use App\Enums\Quality;
use App\Enums\Size;
use App\Pricing\GenerationSpec;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The parts of a generation request that affect its price. Lenient on purpose:
 * the estimate updates while the user is still typing.
 */
class EstimateGenerationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['nullable', 'string', 'max:4000'],
            'size' => ['required', Rule::enum(Size::class)],
            'quality' => ['required', Rule::enum(Quality::class)],
            'reference_image_count' => ['required', 'integer', 'min:0', 'max:4'],
        ];
    }

    public function spec(): GenerationSpec
    {
        return new GenerationSpec(
            prompt: $this->string('prompt')->toString(),
            size: $this->enum('size', Size::class) ?? Size::Square,
            quality: $this->enum('quality', Quality::class) ?? Quality::Medium,
            referenceImageCount: $this->integer('reference_image_count'),
        );
    }
}
