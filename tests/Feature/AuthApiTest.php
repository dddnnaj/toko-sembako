<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_baru_bisa_register_dan_login(): void
    {
        $payload = [
            'name'     => 'Budi Santoso',
            'email'    => 'budi@example.com',
            'password' => 'rahasia123',
            'no_hp'    => '08123456789',
            'alamat'   => 'Jl. Merdeka No. 1',
        ];

        $this->postJson('/api/register', $payload)
            ->assertStatus(201)
            ->assertJsonPath('user.name', 'Budi Santoso')
            ->assertJsonPath('user.email', 'budi@example.com')
            ->assertJsonPath('user.role', 'pembeli')
            ->assertJsonPath('user.no_hp', '08123456789')
            ->assertJsonPath('user.alamat', 'Jl. Merdeka No. 1')
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role', 'no_hp', 'alamat'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email'  => 'budi@example.com',
            'role'   => 'pembeli',
            'no_hp'  => '08123456789',
            'alamat' => 'Jl. Merdeka No. 1',
        ]);

        $this->postJson('/api/login', [
            'email'    => 'budi@example.com',
            'password' => 'rahasia123',
        ])->assertOk()
            ->assertJsonPath('user.email', 'budi@example.com')
            ->assertJsonPath('user.role', 'pembeli')
            ->assertJsonStructure(['user', 'token']);
    }
}
