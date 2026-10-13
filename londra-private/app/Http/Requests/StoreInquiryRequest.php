<?php
declare(strict_types=1);
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Page;
use App\Services\PublicationState;
final class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool { return app(PublicationState::class)->inquiriesAllowed(); }
    protected function failedAuthorization(): void
    {
        abort_unless(app(PublicationState::class)->essentialPagesReady(), 503);
        parent::failedAuthorization();
    }
    protected function prepareForValidation(): void
    {
        $clean=[];
        foreach(['name','email','phone'] as $key) {
            $value=$this->input($key);
            $clean[$key]=is_string($value)?trim($value):$value;
        }
        if(is_string($clean['email'])){$clean['email']=mb_strtolower($clean['email']);}
        $this->merge($clean);
    }
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:2', 'max:120'],
            'email' => ['bail', 'required', 'email:rfc,filter', 'max:254'],
            'phone' => ['bail', 'required', 'string', 'max:40', function (string $attribute, mixed $value, \Closure $fail): void {
                $digits = preg_replace('/\D/', '', (string) $value);
                if (!preg_match('/^[+0-9().\s-]+$/D', (string) $value) || strlen($digits) < 6 || strlen($digits) > 20) { $fail(__('ui.invalid_phone')); }
            }],
            'type' => ['bail', 'required', Rule::in(['booking', 'contact'])],
            'subject' => ['prohibited'],
            'message' => ['prohibited'],
            'bank_account' => ['prohibited'],
            'card_number' => ['prohibited'],
            'payment' => ['prohibited'],
            'submission_id' => ['bail', 'required', 'uuid'],
            'form_token' => ['bail', 'required', 'string', 'max:4096'],
            'website' => ['bail', 'nullable', 'string', 'max:0'],
            'page_key' => ['bail', 'nullable', 'string', 'max:80', Rule::exists('pages', 'key')->where('published', true)],
            'service_key' => ['bail', 'nullable', 'string', 'max:80', Rule::exists('pages', 'key')->where('published', true)->where('kind', 'service')],
        ];
    }
    public function messages(): array
    {
        return ['required' => __('ui.required'), 'required_if' => __('ui.required'), 'email.email' => __('ui.invalid_email'), 'website.max' => __('ui.try_again'), 'min' => __('ui.too_short'), 'max' => __('ui.too_long'), 'string' => __('ui.invalid_value'), 'uuid' => __('ui.try_again'), 'exists' => __('ui.try_again'), 'in' => __('ui.try_again')];
    }
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        if ($this->expectsJson()) { throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(['message' => __('ui.try_again'), 'errors' => $validator->errors()], 422)); }
        throw new \Illuminate\Http\Exceptions\HttpResponseException(redirect()->back()->withErrors($validator)->withInput($this->only(['name', 'email', 'phone', 'type', 'page_key', 'service_key'])));
    }
}
