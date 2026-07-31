<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_always_returns_accepted_without_disclosing_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'driver@example.test']);

        $this->postWithCsrf('/api/auth/forgot-password', ['email' => $user->email])
            ->assertAccepted()
            ->assertNoContent(202);

        Notification::assertSentTo($user, ResetPassword::class);

        $this->postWithCsrf('/api/auth/forgot-password', ['email' => 'missing@example.test'])
            ->assertAccepted()
            ->assertNoContent(202);
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'driver@example.test']);
        $token = Password::broker()->createToken($user);

        $this->postWithCsrf('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])->assertOk()
            ->assertJsonPath('data.id', (string) $user->getKey())
            ->assertJsonPath('data.type', 'users');

        $this->assertTrue(Hash::check('replacement-password', $user->fresh()->password));
    }

    public function test_invalid_reset_token_has_a_consistent_error_response(): void
    {
        $user = User::factory()->create(['email' => 'driver@example.test']);

        $this->postWithCsrf('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'password_reset_failed');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postWithCsrf(string $uri, array $data): TestResponse
    {
        $csrfToken = Str::random(64);

        return $this->withCredentials()
            ->withUnencryptedCookie(config('jwt.csrf.cookie_name'), $csrfToken)
            ->withHeader(config('jwt.csrf.header_name'), $csrfToken)
            ->postJson($uri, $data);
    }
}
