<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_cooldown_survives_separate_requests_and_reports_remaining_time(): void
    {
        Http::fake([
            'api.msgway.com/send' => Http::response(['status' => 'success']),
        ]);

        $payload = ['mobile' => '09121234567', 'purpose' => 'register'];

        $this->postJson('/api/send-otp', $payload)
            ->assertOk()
            ->assertJsonPath('retry_after', 120)
            ->assertJsonStructure(['expires_at']);

        $this->getJson('/api/otp/status?mobile=09121234567&purpose=register')
            ->assertOk()
            ->assertJsonPath('active', true);

        $this->postJson('/api/send-otp', $payload)
            ->assertStatus(429)
            ->assertJsonStructure(['expires_at', 'retry_after']);

        Http::assertSentCount(1);
    }

    public function test_registered_user_cannot_request_registration_otp(): void
    {
        User::factory()->create([
            'phone' => '09121234567',
            'is_completed' => true,
        ]);

        $this->postJson('/api/send-otp', [
            'mobile' => '09121234567',
            'purpose' => 'register',
        ])->assertStatus(409)->assertJsonPath('action', 'login');
    }

    public function test_phone_changes_only_after_msgway_verifies_the_code(): void
    {
        Http::fakeSequence()
            ->push(['status' => 'success'], 200)
            ->push(['status' => 'success'], 200);

        $user = User::factory()->create([
            'phone' => '09121234567',
            'is_completed' => true,
        ]);
        Sanctum::actingAs($user);

        $requestId = $this->postJson('/api/profile/change-phone', [
            'new_phone' => '09351234567',
        ])->assertOk()->json('request_id');

        $this->assertSame('09121234567', $user->fresh()->phone);

        $this->postJson('/api/profile/verify-phone', [
            'request_id' => $requestId,
            'otp' => '1234567',
        ])->assertOk()->assertJsonPath('phone', '09351234567');

        $this->assertSame('09351234567', $user->fresh()->phone);
    }

    public function test_email_is_optional_at_registration_schema_level(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'phone' => '09121234567',
        ]);

        $this->assertNull($user->email);
    }
}
