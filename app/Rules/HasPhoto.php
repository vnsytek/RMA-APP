<?php

namespace App\Rules;

use App\Enums\AttachmentStage;
use App\Services\AttachmentStorage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The uploaded files must include at least one photo of the device.
 */
class HasPhoto implements ValidationRule
{
    /**
     * Run even when nothing was uploaded.
     */
    public bool $implicit = true;

    public function __construct(private AttachmentStage $stage) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! AttachmentStorage::containsPhoto(is_array($value) ? $value : [])) {
            $fail(AttachmentStorage::missingPhotoMessage($this->stage));
        }
    }
}
