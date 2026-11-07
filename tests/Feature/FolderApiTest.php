<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_rename_and_move_a_folder(): void
    {
        $admin = User::factory()->admin()->create();
        $oldParent = Folder::create(['name' => 'Old parent']);
        $newParent = Folder::create(['name' => 'New parent']);
        $folder = Folder::create([
            'name' => 'Original name',
            'parent_id' => $oldParent->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/folders/{$folder->id}", [
                'name' => 'Updated name',
                'parent_id' => $newParent->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated name')
            ->assertJsonPath('data.parent_id', $newParent->id);

        $this->assertDatabaseHas('folders', [
            'id' => $folder->id,
            'name' => 'Updated name',
            'parent_id' => $newParent->id,
        ]);
    }

    public function test_show_returns_breadcrumbs_from_root_to_current_folder(): void
    {
        $root = Folder::create(['name' => 'Root']);
        $parent = Folder::create([
            'name' => 'Parent',
            'parent_id' => $root->id,
        ]);
        $folder = Folder::create([
            'name' => 'Current',
            'parent_id' => $parent->id,
        ]);

        $this->assertSame([
            [
                'id' => $root->id,
                'name' => 'Root',
                'parent_id' => null,
            ],
            [
                'id' => $parent->id,
                'name' => 'Parent',
                'parent_id' => $root->id,
            ],
            [
                'id' => $folder->id,
                'name' => 'Current',
                'parent_id' => $parent->id,
            ],
        ], $folder->getBreadcrumbs());

        $this->getJson("/api/folders/{$folder->id}")
            ->assertOk()
            ->assertJsonPath('data.breadcrumbs', [
                [
                    'id' => $root->id,
                    'name' => 'Root',
                    'parent_id' => null,
                ],
                [
                    'id' => $parent->id,
                    'name' => 'Parent',
                    'parent_id' => $root->id,
                ],
                [
                    'id' => $folder->id,
                    'name' => 'Current',
                    'parent_id' => $parent->id,
                ],
            ]);
    }

    public function test_folder_cannot_be_moved_into_itself_or_a_descendant(): void
    {
        $admin = User::factory()->admin()->create();
        $folder = Folder::create(['name' => 'Parent']);
        $child = Folder::create([
            'name' => 'Child',
            'parent_id' => $folder->id,
        ]);
        $grandchild = Folder::create([
            'name' => 'Grandchild',
            'parent_id' => $child->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/folders/{$folder->id}", [
                'parent_id' => $child->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');

        $this->putJson("/api/admin/folders/{$folder->id}", [
            'parent_id' => $grandchild->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');

        $this->putJson("/api/admin/folders/{$folder->id}", [
            'parent_id' => $folder->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_only_admins_can_update_folders(): void
    {
        $user = User::factory()->create();
        $folder = Folder::create(['name' => 'Protected folder']);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/folders/{$folder->id}", ['name' => 'Changed'])
            ->assertForbidden();

        $this->assertDatabaseHas('folders', [
            'id' => $folder->id,
            'name' => 'Protected folder',
        ]);
    }

    public function test_deleting_a_folder_cascades_to_its_children(): void
    {
        $folder = Folder::create(['name' => 'Parent']);
        $child = Folder::create([
            'name' => 'Child',
            'parent_id' => $folder->id,
        ]);

        $folder->delete();

        $this->assertDatabaseMissing('folders', ['id' => $child->id]);
    }
}
