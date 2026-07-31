<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Support\Logging\LogContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'tenant_slug' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tenant_slug' => __('auth.fields.tenant_slug'),
            'email' => __('auth.fields.email'),
            'password' => __('auth.fields.password'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tenant_slug' => $this->string('tenant_slug')->trim()->toString(),
            'email' => $this->string('email')->trim()->toString(),
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->clearLoginContext();

        parent::failedValidation($validator);
    }

    private function clearLoginContext(): void
    {
        Auth::logout();
        $this->session()->forget(['tenant_id', 'branch_id']);
        app(BranchContext::class)->clear();
        app(TenantResolver::class)->clear();
        LogContext::refreshRuntimeContext('identity');
    }
}
