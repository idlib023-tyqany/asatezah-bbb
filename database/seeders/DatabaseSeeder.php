<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Material;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. حساب مدير النظام (Admin) جاهز ومفعل
        $admin = User::firstOrCreate(
            ['email' => 'admin@asatezah.com'],
            [
                'name' => 'مدير النظام',
                'display_name' => 'الإدارة العامة',
                'phone_number' => '+966500000000',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'can_contribute' => true,
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );

        // 2. حساب معلم تجريبي (Teacher)
        $teacher = User::firstOrCreate(
            ['email' => 'teacher@asatezah.com'],
            [
                'name' => 'أحمد محمود',
                'display_name' => 'أ. أحمد محمود',
                'phone_number' => '+966500000001',
                'password' => Hash::make('password'),
                'role' => User::ROLE_TEACHER,
                'specialization' => 'رياضيات',
                'can_contribute' => true,
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );

        // 3. التصنيفات والصفوف التعليمية
        $categoriesData = [
            ['name' => 'الصف الأول الابتدائي', 'slug' => 'grade-1'],
            ['name' => 'الصف الثاني الابتدائي', 'slug' => 'grade-2'],
            ['name' => 'الصف الثالث الابتدائي', 'slug' => 'grade-3'],
            ['name' => 'الصف الأول الإعدادي', 'slug' => 'prep-1'],
            ['name' => 'الصف الأول الثانوي', 'slug' => 'secondary-1'],
            ['name' => 'الصف الثالث الثانوي (بكالوريا)', 'slug' => 'baccalaureate'],
        ];

        $categories = [];
        foreach ($categoriesData as $cat) {
            $categories[] = Category::firstOrCreate(['slug' => $cat['slug']], $cat);
        }

        // 4. المواد الدراسية
        $firstCat = $categories[0] ?? null;
        $materialsData = [
            ['name' => 'رياضيات', 'slug' => 'math', 'code' => 'MATH-101'],
            ['name' => 'علوم', 'slug' => 'science', 'code' => 'SCI-101'],
            ['name' => 'لغة عربية', 'slug' => 'arabic', 'code' => 'ARAB-101'],
            ['name' => 'لغة إنجليزية', 'slug' => 'english', 'code' => 'ENG-101'],
            ['name' => 'فيزياء', 'slug' => 'physics', 'code' => 'PHYS-101'],
            ['name' => 'كيمياء', 'slug' => 'chemistry', 'code' => 'CHEM-101'],
        ];

        foreach ($materialsData as $mat) {
            Material::firstOrCreate(
                ['slug' => $mat['slug']],
                array_merge($mat, ['category_id' => $firstCat?->id])
            );
        }

        // 5. الإعدادات وروابط التواصل
        $settings = [
            'app_name' => ['value' => 'أساتذة', 'type' => 'string'],
            'app_version' => ['value' => '1.0.0', 'type' => 'string'],
            'min_supported_version' => ['value' => '1.0.0', 'type' => 'string'],
            'link_telegram' => ['value' => 'https://t.me/asatezah', 'type' => 'string'],
            'link_website' => ['value' => 'https://asatezah.com', 'type' => 'string'],
            'link_privacy_policy' => ['value' => 'https://asatezah.com/privacy', 'type' => 'string'],
        ];

        foreach ($settings as $key => $setting) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $setting['value'], 'type' => $setting['type']]
            );
        }
    }
}
