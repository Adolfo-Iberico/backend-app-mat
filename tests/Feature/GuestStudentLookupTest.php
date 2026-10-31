<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestStudentLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_returns_student_data_with_valid_captcha(): void
    {
        $studentRole = 'student';
        $student = User::create([
            'id' => Str::uuid(),
            'role' => $studentRole,
            'dni' => '70000003',
            'full_name' => 'Ana Garcia Lopez',
            'email' => 'ana.test@mathflow.com',
            'password' => Hash::make('password123'),
        ]);

        $token = Crypt::encryptString(json_encode([
            'code' => 'ABC123',
            'expires_at' => time() + 300,
        ]));

        $response = $this->postJson('/api/v1/guest/student-lookup', [
            'dni' => '70000003',
            'captcha_token' => $token,
            'captcha_answer' => 'abc123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student.name', 'Ana Garcia Lopez');
    }

    public function test_lookup_rejects_incorrect_captcha(): void
    {
        $token = Crypt::encryptString(json_encode([
            'code' => 'ABC123',
            'expires_at' => time() + 300,
        ]));

        $this->postJson('/api/v1/guest/student-lookup', [
            'dni' => '70000003',
            'captcha_token' => $token,
            'captcha_answer' => 'WRONG',
        ])->assertStatus(422);
    }
}
