<?php

declare(strict_types=1);

namespace App\Livewire\Feedback;

use App\Enums\FeedbackType;
use App\Livewire\Concerns\EnsuresRecaptchaVerifiedWhenEnabled;
use App\Services\Feedback\FeedbackSubmissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class FeedbackModal extends Component
{
    use EnsuresRecaptchaVerifiedWhenEnabled;
    use Toast;

    public bool $open = false;

    public string $type = FeedbackType::Question->value;

    public string $subject = '';

    public string $body = '';

    public string $email = '';

    public string $pageUrl = '';

    public int $modalRenderKey = 0;

    #[On('open-feedback-modal')]
    public function openModal(?string $pageUrl = null): void
    {
        $this->resetForm();
        $this->modalRenderKey++;
        $this->pageUrl = is_string($pageUrl) && $pageUrl !== ''
            ? $pageUrl
            : (string) url()->previous();
        $this->open = true;
    }

    public function closeModal(): void
    {
        $this->open = false;
        $this->resetForm();
    }

    public function submit(FeedbackSubmissionService $submissions): void
    {
        $this->ensureNotRateLimited();

        $rules = [
            'type' => ['required', 'string', Rule::enum(FeedbackType::class)],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:50000'],
            'pageUrl' => ['nullable', 'string', 'max:2048'],
        ];

        if (Auth::guest()) {
            $rules['email'] = ['required', 'string', 'email', 'max:255'];
        } else {
            $rules['email'] = ['nullable', 'string', 'email', 'max:255'];
        }

        $validated = $this->validateFormThenRecaptchaIfEnabled($rules);

        RateLimiter::hit($this->throttleKey(), (int) config('feedback.submit_decay_seconds', 3600));

        $submissions->submit([
            'type' => $validated['type'],
            'subject' => $validated['subject'] ?? '',
            'body' => $validated['body'],
            'email' => $validated['email'] ?? null,
            'page_url' => $validated['pageUrl'] ?? $this->pageUrl,
            'user_agent' => request()->userAgent(),
        ], Auth::user());

        $this->success(__('feedback.modal.success'));
        $this->closeModal();
    }

    /**
     * Mary <x-select> expects [{id, name}, ...], not a value => label map.
     *
     * @return list<array{id: string, name: string}>
     */
    public function typeOptions(): array
    {
        return collect(FeedbackType::cases())
            ->map(fn (FeedbackType $type): array => [
                'id' => $type->value,
                'name' => $type->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function editorConfig(): array
    {
        return [
            'height' => 180,
            'z_index' => 100020,
            'menubar' => false,
            'statusbar' => false,
            'toolbar' => 'undo redo | bold italic | bullist numlist | link image | removeformat',
            'toolbar_mode' => 'wrap',
            'plugins' => 'lists link image autolink',
            'quickbars_selection_toolbar' => false,
            'mobile' => [
                'toolbar' => 'undo redo | bold italic | bullist numlist | link image | removeformat',
                'toolbar_mode' => 'wrap',
                'statusbar' => false,
                'plugins' => 'lists link image autolink',
            ],
        ];
    }

    protected function usesRecaptchaForRequests(): bool
    {
        return Auth::guest() && auth_recaptcha_enforced();
    }

    protected function recaptchaDataCallback(): string
    {
        return 'nerdikFeedbackRecaptcha';
    }

    protected function ensureNotRateLimited(): void
    {
        $maxAttempts = (int) config('feedback.submit_max_attempts', 5);

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $maxAttempts)) {
            return;
        }

        throw ValidationException::withMessages([
            'body' => [__('feedback.modal.rate_limited')],
        ]);
    }

    protected function throttleKey(): string
    {
        $identity = Auth::id() !== null
            ? 'user:'.Auth::id()
            : 'ip:'.(string) request()->ip();

        return Str::transliterate('feedback-submit|'.$identity);
    }

    private function resetForm(): void
    {
        $this->reset('subject', 'body', 'email', 'pageUrl', 'gRecaptchaResponse');
        $this->type = FeedbackType::Question->value;
        $this->resetValidation();
        $this->clearRecaptchaState();
    }

    public function render()
    {
        return view('livewire.feedback.feedback-modal');
    }
}
