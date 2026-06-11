<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level policy authorization is handled in the controller.
        return true;
    }

    public function rules(): array
    {
        $allowed = collect(config('attachments.allowed_mimetypes'))
            ->flatten()
            ->implode(',');

        return [
            'file' => [
                'required',
                'file',
                'mimetypes:' . $allowed,
                'max:' . config('attachments.max_size_kb'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required'  => 'Please select a file.',
            'file.file'      => 'The selected upload must be a valid file.',
            'file.mimetypes' => 'Only images, documents, and videos (mp4, mov, webm, avi, mkv) are allowed.',
            'file.max'       => 'The file must not be larger than ' . (int) (config('attachments.max_size_kb') / 1024) . 'MB.',
        ];
    }
}
