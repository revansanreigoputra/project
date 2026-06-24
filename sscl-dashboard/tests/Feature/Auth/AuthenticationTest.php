<?php

namespace Tests\Feature\Auth;

use App\Models\DetectionResult;
use App\Models\ScanUpload;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_admin_authenticates_to_access_management(): void
    {
        $user = $this->userWithRole(RolePermissionSeeder::ADMIN_ROLE);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.users.index', absolute: false));
    }

    public function test_petugas_authenticates_to_dashboard(): void
    {
        $user = $this->userWithRole(RolePermissionSeeder::PETUGAS_ROLE);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_detection_result_email_link_requires_login_and_returns_to_detail(): void
    {
        $user = $this->userWithRole(RolePermissionSeeder::PETUGAS_ROLE);
        $upload = ScanUpload::create([
            'user_id' => $user->id,
            'original_name' => 'email-link.csv',
            'stored_name' => 'email-link.csv',
            'path' => 'scan-uploads/email-link.csv',
            'mime_type' => 'text/csv',
            'extension' => 'csv',
            'size' => 24,
            'status' => 'processed',
        ]);
        $detectionResult = DetectionResult::create([
            'user_id' => $user->id,
            'scan_upload_id' => $upload->id,
            'file_name' => $upload->original_name,
            'processed_at' => now(),
            'total' => 1,
            'normal_count' => 0,
            'malware_count' => 1,
            'unknown_count' => 0,
            'normal_percent' => 0,
            'malware_percent' => 100,
            'average_confidence' => 98,
            'status' => 'Selesai',
            'summary' => [
                'normal' => 0,
                'malware' => 1,
                'unknown' => 0,
                'normal_percent' => 0,
                'malware_percent' => 100,
                'average_confidence' => 98,
                'labels' => ['Trojan' => 1],
                'malware_labels' => ['Trojan' => 1],
            ],
            'results' => [
                [
                    'row' => 1,
                    'label' => 'Trojan',
                    'confidence' => 0.98,
                    'probabilities' => ['Trojan' => 0.98],
                ],
            ],
        ]);
        $detailUrl = route('hasildeteksi.show', $detectionResult);

        $this->get($detailUrl)
            ->assertRedirect(route('login', absolute: false));

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect($detailUrl);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
