<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_returns_schema_backed_category_and_material_fields(): void
    {
        $category = Category::create([
            'name' => 'Mathematics',
            'slug' => 'mathematics',
            'icon' => 'calculator',
        ]);
        $material = Material::create([
            'name' => 'Algebra',
            'slug' => 'algebra',
            'category_id' => $category->id,
        ]);

        $this->actingAs(User::factory()->teacher()->create(), 'sanctum')
            ->getJson('/api/filters')
            ->assertOk()
            ->assertJsonPath('categories.0.icon', 'calculator')
            ->assertJsonPath('materials.0.category_id', $category->id)
            ->assertJsonPath('materials.0.name', $material->name);
    }

    public function test_admin_catalog_changes_invalidate_authenticated_filter_cache(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/filters')
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/categories', ['name' => 'Science'])
            ->assertCreated();

        $this->getJson('/api/filters')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Science']);
    }
}
