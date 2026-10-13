<?php
declare(strict_types=1);
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreDataRequest extends FormRequest
{
    public function authorize(): bool { return app(\App\Services\PublicationState::class)->privacyRequestsAllowed(); }
    protected function failedAuthorization(): void
    {
        abort_unless(app(\App\Services\PublicationState::class)->privacyPageReady(), 503);
        parent::failedAuthorization();
    }
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) { $this->merge(['email' => mb_strtolower(trim($this->input('email')))]); }
    }
    public function rules(): array
    {
        return ['email' => ['required', 'email:rfc,filter', 'max:254'], 'submission_id' => ['required', 'uuid'], 'form_token' => ['required', 'string', 'max:4096'], 'website' => ['nullable', 'string', 'max:0']];
    }
    public function messages(): array { return ['required' => __('ui.required'), 'email.email' => __('ui.invalid_email'), 'max' => __('ui.too_long'), 'website.max' => __('ui.privacy_try_again'), 'uuid' => __('ui.privacy_try_again'), 'string' => __('ui.invalid_value')]; }
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        if ($this->expectsJson()) { throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(['message' => __('ui.privacy_try_again'), 'errors' => $validator->errors()], 422)); }
        throw new \Illuminate\Http\Exceptions\HttpResponseException(redirect()->back()->withErrors($validator)->withInput($this->only('email')));
    }
}
