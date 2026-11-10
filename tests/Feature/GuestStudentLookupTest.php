<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestStudentLookupTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parent = User::create([
            'id' => Str::uuid(),
            'role' => 'parent',
            'full_name' => 'Parent Test',
            'email' => 'parent.test@mathflow.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    public function test_lookup_returns_student_data_with_valid_captcha(): void
    {
        $student = User::create([
            'id' => Str::uuid(),
            'role' => 'student',
            'dni' => '70000003',
            'full_name' => 'Ana Garcia Lopez',
            'email' => 'ana.test@mathflow.com',
            'password' => Hash::make('password123'),
        ]);
        $this->linkStudentToParent($student);
        Sanctum::actingAs($this->parent, ['web']);

        $token = Crypt::encryptString(json_encode([
            'code' => 'ABC123',
            'expires_at' => time() + 300,
            'jti' => Str::uuid()->toString(),
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
        Sanctum::actingAs($this->parent, ['web']);

        $token = Crypt::encryptString(json_encode([
            'code' => 'ABC123',
            'expires_at' => time() + 300,
            'jti' => Str::uuid()->toString(),
        ]));

        $payload = [
            'dni' => '70000003',
            'captcha_token' => $token,
            'captcha_answer' => 'WRONG',
        ];

        $this->postJson('/api/v1/guest/student-lookup', $payload)->assertStatus(422);
        $this->postJson('/api/v1/guest/student-lookup', [
            ...$payload,
            'captcha_answer' => 'ABC123',
        ])->assertStatus(422);
    }

    public function test_lookup_rejects_reuse_of_a_valid_captcha(): void
    {
        $student = User::create([
            'id' => Str::uuid(),
            'role' => 'student',
            'dni' => '70000003',
            'full_name' => 'Ana Garcia Lopez',
            'email' => 'ana.reuse@mathflow.com',
            'password' => Hash::make('password123'),
        ]);
        $this->linkStudentToParent($student);
        Sanctum::actingAs($this->parent, ['web']);

        $token = Crypt::encryptString(json_encode([
            'code' => 'ABC123',
            'expires_at' => time() + 300,
            'jti' => Str::uuid()->toString(),
        ]));

        $payload = [
            'dni' => '70000003',
            'captcha_token' => $token,
            'captcha_answer' => 'ABC123',
        ];

        $this->postJson('/api/v1/guest/student-lookup', $payload)->assertOk();
        $this->postJson('/api/v1/guest/student-lookup', [
            ...$payload,
            'dni' => '70000004',
        ])->assertStatus(422);
    }

    public function test_lookup_requires_authentication_and_parent_role(): void
    {
        $payload = [
            'dni' => '70000003',
            'captcha_token' => 'token',
            'captcha_answer' => 'answer',
        ];

        $this->postJson('/api/v1/guest/student-lookup', $payload)->assertUnauthorized();

        $student = User::create([
            'id' => Str::uuid(),
            'role' => 'student',
            'email' => 'student.auth@mathflow.com',
            'full_name' => 'Student Auth',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        Sanctum::actingAs($student, ['web']);

        $this->postJson('/api/v1/guest/student-lookup', $payload)->assertForbidden();
    }

    public function test_lookup_returns_same_not_found_response_for_unlinked_and_unknown_students(): void
    {
        Sanctum::actingAs($this->parent, ['web']);
        $student = User::create([
            'id' => Str::uuid(),
            'role' => 'student',
            'dni' => '70000003',
            'full_name' => 'Ana Garcia Lopez',
            'email' => 'ana.unlinked@mathflow.com',
            'password' => Hash::make('password123'),
        ]);

        $unknownResponse = $this->postJson('/api/v1/guest/student-lookup', $this->validLookupPayload('70000004'));
        $unlinkedResponse = $this->postJson('/api/v1/guest/student-lookup', $this->validLookupPayload($student->dni));

        $unknownResponse->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'No se pudo completar la consulta.']);
        $unlinkedResponse->assertNotFound()
            ->assertExactJson($unknownResponse->json());
    }

    private function linkStudentToParent(User $student): void
    {
        DB::table('parent_student')->insert([
            'id' => Str::uuid(),
            'parent_id' => $this->parent->id,
            'student_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function validLookupPayload(string $dni): array
    {
        return [
            'dni' => $dni,
            'captcha_token' => Crypt::encryptString(json_encode([
                'code' => 'ABC123',
                'expires_at' => time() + 300,
                'jti' => Str::uuid()->toString(),
            ])),
            'captcha_answer' => 'ABC123',
        ];
    }
}
