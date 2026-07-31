<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\JWT;
use PHPOpenSourceSaver\JWTAuth\JWTAuth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_cookie_is_issued_for_the_stateless_double_submit_flow(): void
    {
        $response = $this->getJson('/api/auth/csrf-cookie');

        $response->assertNoContent()
            ->assertCookie(config('jwt.csrf.cookie_name'));

        $cookie = $response->getCookie(config('jwt.csrf.cookie_name'), false);

        $this->assertFalse($cookie->isHttpOnly());
        $this->assertSame(config('jwt.cookies.same_site'), $cookie->getSameSite());
    }

    public function test_mutating_authentication_requests_require_a_matching_csrf_token(): void
    {
        $this->postJson('/api/auth/register', $this->registrationData())
            ->assertStatus(419)
            ->assertJsonPath('errors.0.code', 'csrf_token_mismatch');

        $this->withCredentials()
            ->withUnencryptedCookie(config('jwt.csrf.cookie_name'), 'cookie-token')
            ->withHeader(config('jwt.csrf.header_name'), 'header-token')
            ->postJson('/api/auth/register', $this->registrationData())
            ->assertStatus(419)
            ->assertJsonPath('errors.0.code', 'csrf_token_mismatch');
    }

    public function test_user_can_register_and_authenticate_with_an_http_only_jwt_cookie(): void
    {
        $response = $this->postWithCsrf('/api/auth/register', $this->registrationData([
            'timezone' => 'America/New_York',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.type', 'users')
            ->assertJsonPath('data.attributes.email', 'driver@example.test')
            ->assertJsonPath('data.attributes.timezone', 'America/New_York')
            ->assertCookie(config('jwt.cookie_key_name'))
            ->assertCookie(config('jwt.csrf.cookie_name'));

        $jwtCookie = $response->getCookie(config('jwt.cookie_key_name'), false);

        $this->assertTrue($jwtCookie->isHttpOnly());
        $this->assertSame(config('jwt.cookies.same_site'), $jwtCookie->getSameSite());

        $user = User::query()->where('email', 'driver@example.test')->firstOrFail();

        $this->assertModelExists($user);
        $this->assertSame('America/New_York', $user->timezone);
    }

    public function test_authenticated_user_endpoint_reads_the_jwt_from_the_cookie(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withCredentials()
            ->withUnencryptedCookie(config('jwt.cookie_key_name'), $token)
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.id', (string) $user->getKey())
            ->assertJsonPath('data.type', 'users')
            ->assertJsonPath('data.attributes.email', $user->email);
    }

    public function test_user_can_log_in_and_invalid_credentials_have_a_clear_error(): void
    {
        $user = User::factory()->create([
            'email' => 'driver@example.test',
            'password' => 'password',
        ]);

        $this->postWithCsrf('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.id', (string) $user->getKey())
            ->assertCookie(config('jwt.cookie_key_name'));

        $this->postWithCsrf('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'invalid_credentials');
    }

    public function test_refresh_rotates_the_cookie_and_invalidates_the_previous_jwt(): void
    {
        $user = User::factory()->create();
        $oldToken = $this->tokenFor($user);

        $response = $this->postWithCsrf('/api/auth/refresh', [], [
            config('jwt.cookie_key_name') => $oldToken,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $user->getKey())
            ->assertCookie(config('jwt.cookie_key_name'));

        $newToken = $response->getCookie(config('jwt.cookie_key_name'), false)->getValue();

        $this->assertNotSame($oldToken, $newToken);

        $jwt = app(JWT::class);

        $this->assertFalse($jwt->setToken($oldToken)->check());
        $this->assertTrue($jwt->setToken($newToken)->check());
        $jwt->unsetToken();
    }

    public function test_logout_invalidates_the_jwt_and_expires_authentication_cookies(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $response = $this->postWithCsrf('/api/auth/logout', [], [
            config('jwt.cookie_key_name') => $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $user->getKey())
            ->assertCookieExpired(config('jwt.cookie_key_name'))
            ->assertCookieExpired(config('jwt.csrf.cookie_name'));

        $this->withCredentials()
            ->withUnencryptedCookie(config('jwt.cookie_key_name'), $token)
            ->getJson('/api/auth/user')
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.code', 'unauthenticated');
    }

    public function test_login_is_rate_limited(): void
    {
        $credentials = [
            'email' => 'driver@example.test',
            'password' => 'wrong-password',
        ];

        foreach (range(1, 5) as $attempt) {
            $this->postWithCsrf('/api/auth/login', $credentials)
                ->assertUnprocessable();
        }

        $this->postWithCsrf('/api/auth/login', $credentials)
            ->assertTooManyRequests()
            ->assertJsonPath('errors.0.code', 'rate_limited');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Driver',
            'email' => 'driver@example.test',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $cookies
     */
    private function postWithCsrf(string $uri, array $data = [], array $cookies = []): TestResponse
    {
        $csrfToken = Str::random(64);

        return $this->withCredentials()
            ->withUnencryptedCookies(array_merge([
                config('jwt.csrf.cookie_name') => $csrfToken,
            ], $cookies))
            ->withHeader(config('jwt.csrf.header_name'), $csrfToken)
            ->postJson($uri, $data);
    }

    private function tokenFor(User $user): string
    {
        return app(JWTAuth::class)->fromUser($user);
    }
}
