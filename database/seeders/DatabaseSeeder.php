<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Material;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. إنشاء حساب مدير النظام (Admin)
        $admin = User::firstOrCreate(
            ['email' => 'admin@asatezah.com'],
            [
                'name' => 'مدير النظام',
                'display_name' => 'الإدارة العامة',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'can_contribute' => true,
                'email_verified_at' => now(),
            ]
        );

        // 2. إنشاء حساب معلم تجريبي (Teacher)
        $teacher = User::firstOrCreate(
            ['email' => 'teacher@asatezah.com'],
            [
                'name' => 'أحمد محمود',
                'display_name' => 'أ. أحمد محمود',
                'phone' => '966500000001',
                'password' => Hash::make('password'),
                'role' => 'teacher',
                'specialization' => 'رياضيات',
                'can_contribute' => true,
                'email_verified_at' => now(),
            ]
        );

        // 3. إنشاء حساب طالب تجريبي (Student)
        $student = User::firstOrCreate(
            ['email' => 'student@asatezah.com'],
            [
                'name' => 'محمد علي',
                'display_name' => 'محمد علي',
                'phone' => '966500000002',
                'password' => Hash::make('password'),
                'role' => 'student',
                'can_contribute' => true,
                'email_verified_at' => now(),
            ]
        );

        // 4. إنشاء الصفوف والتصنيفات (Categories)
        $categoriesData = [
            ['name' => 'الصف الأول', 'slug' => 'grade-1'],
            ['name' => 'الصف الثاني', 'slug' => 'grade-2'],
            ['name' => 'الصف الثالث', 'slug' => 'grade-3'],
            ['name' => 'الصف الرابع', 'slug' => 'grade-4'],
            ['name' => 'الصف الخامس', 'slug' => 'grade-5'],
            ['name' => 'الصف السادس', 'slug' => 'grade-6'],
            ['name' => 'الصف السابع', 'slug' => 'grade-7'],
            ['name' => 'الصف الثامن', 'slug' => 'grade-8'],
            ['name' => 'الصف التاسع', 'slug' => 'grade-9'],
        ];

        $categories = [];
        foreach ($categoriesData as $cat) {
            $categories[] = Category::firstOrCreate(['slug' => $cat['slug']], $cat);
        }

        $firstCategory = $categories[0] ?? null;

        // 5. إنشاء المواد الدراسية وربطها بتصنيف (Materials)
        $materialsData = [
            ['name' => 'رياضيات', 'slug' => 'math', 'code' => 'MATH-101'],
            ['name' => 'علوم', 'slug' => 'science', 'code' => 'SCI-101'],
            ['name' => 'لغة عربية', 'slug' => 'arabic', 'code' => 'ARAB-101'],
            ['name' => 'لغة إنجليزية', 'slug' => 'english', 'code' => 'ENG-101'],
            ['name' => 'تاريخ', 'slug' => 'history', 'code' => 'HIST-101'],
            ['name' => 'جغرافيا', 'slug' => 'geography', 'code' => 'GEO-101'],
            ['name' => 'قرآن كريم', 'slug' => 'quran', 'code' => 'QUR-101'],
            ['name' => 'تربية إسلامية', 'slug' => 'islamic', 'code' => 'ISL-101'],
        ];

        foreach ($materialsData as $mat) {
            Material::firstOrCreate(
                ['slug' => $mat['slug']],
                array_merge($mat, ['category_id' => $firstCategory?->id])
            );
        }

        // 6. زرع إعدادات النظام (Settings)
        $settings = [
            'app_name' => ['value' => 'أساتذة', 'type' => 'string'],
            'app_version' => ['value' => '1.0.0', 'type' => 'string'],
            'allow_registration' => ['value' => '1', 'type' => 'boolean'],
            'maintenance_mode' => ['value' => '0', 'type' => 'boolean'],
        ];

        foreach ($settings as $key => $setting) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $setting['value'], 'type' => $setting['type']]
            );
        }
    }
}
