<?php

namespace App\Http\Requests;

use App\Enums\Quality;
use App\Enums\Size;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mirrors the provider's own limits, so users see problems before we submit anything.
 */
class StoreGenerationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', Rule::enum(Size::class)],
            'quality' => ['required', Rule::enum(Quality::class)],
            'reference_images' => ['present', 'array', 'max:4'],
            'reference_images.*' => ['required', 'string', 'url:http,https', 'max:2048'],
        ];
    }

    public function size(): Size
    {
        return $this->enum('size', Size::class) ?? Size::Square;
    }

    public function quality(): Quality
    {
        return $this->enum('quality', Quality::class) ?? Quality::Medium;
    }

    /**
     * @return list<string>
     */
    public function referenceImages(): array
    {
        return array_values($this->array('reference_images'));
    }
}
