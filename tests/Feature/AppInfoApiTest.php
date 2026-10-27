<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppInfoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_settings_updates_invalidate_cached_public_app_info(): void
    {
        Setting::set('app_name', 'Old name');

        $this->getJson('/api/info')
            ->assertOk()
            ->assertJsonPath('data.name', 'Old name');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/settings', ['app_name' => 'New name'])
            ->assertOk();

        $this->getJson('/api/info')
            ->assertOk()
            ->assertJsonPath('data.name', 'New name');
    }
}
