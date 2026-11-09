<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. المراحل الدراسية
        $primaryId = DB::table('educational_stages')->insertGetId(['name' => 'المرحلة الابتدائية', 'order_index' => 1, 'created_at' => now()]);
        $prepId    = DB::table('educational_stages')->insertGetId(['name' => 'المرحلة الإعدادية', 'order_index' => 2, 'created_at' => now()]);
        $secId     = DB::table('educational_stages')->insertGetId(['name' => 'المرحلة الثانوية', 'order_index' => 3, 'created_at' => now()]);

        // 2. الصفوف
        $grades = [
            // ابتدائي
            ['stage_id' => $primaryId, 'name' => 'الصف الأول الأساسي', 'order_index' => 1],
            ['stage_id' => $primaryId, 'name' => 'الصف الثاني الأساسي', 'order_index' => 2],
            ['stage_id' => $primaryId, 'name' => 'الصف الثالث الأساسي', 'order_index' => 3],
            ['stage_id' => $primaryId, 'name' => 'الصف الرابع الأساسي', 'order_index' => 4],
            ['stage_id' => $primaryId, 'name' => 'الصف الخامس الأساسي', 'order_index' => 5],
            ['stage_id' => $primaryId, 'name' => 'الصف السادس الأساسي', 'order_index' => 6],
            // إعدادي
            ['stage_id' => $prepId, 'name' => 'الصف السابع الأساسي', 'order_index' => 7],
            ['stage_id' => $prepId, 'name' => 'الصف الثامن الأساسي', 'order_index' => 8],
            ['stage_id' => $prepId, 'name' => 'الصف التاسع (شهادة التعليم الأساسي)', 'order_index' => 9],
            // ثانوي
            ['stage_id' => $secId, 'name' => 'الصف العاشر (الأول الثانوي)', 'order_index' => 10],
            ['stage_id' => $secId, 'name' => 'الصف الحادي عشر (الثاني الثانوي)', 'order_index' => 11],
            ['stage_id' => $secId, 'name' => 'الثالث الثانوي (شهادة البكالوريا)', 'order_index' => 12],
        ];

        $insertedGrades = [];
        foreach ($grades as $g) {
            $insertedGrades[$g['name']] = DB::table('grades')->insertGetId(array_merge($g, ['created_at' => now()]));
        }

        // 3. الأقسام والفروع
        $tracks = ['علمي', 'أدبي', 'شرعي', 'صناعي', 'تجاري', 'تقني'];
        $trackIds = [];
        foreach ($tracks as $t) {
            $trackIds[$t] = DB::table('tracks')->insertGetId(['name' => $t, 'is_active' => true, 'created_at' => now()]);
        }

        // 4. ربط فروع البكالوريا والعاشر والحادي عشر
        DB::table('grade_track_pivot')->insert([
            ['grade_id' => $insertedGrades['الثالث الثانوي (شهادة البكالوريا)'], 'track_id' => $trackIds['علمي']],
            ['grade_id' => $insertedGrades['الثالث الثانوي (شهادة البكالوريا)'], 'track_id' => $trackIds['أدبي']],
            ['grade_id' => $insertedGrades['الثالث الثانوي (شهادة البكالوريا)'], 'track_id' => $trackIds['شرعي']],
            ['grade_id' => $insertedGrades['الصف العاشر (الأول الثانوي)'], 'track_id' => $trackIds['علمي']],
            ['grade_id' => $insertedGrades['الصف العاشر (الأول الثانوي)'], 'track_id' => $trackIds['شرعي']],
        ]);

        // 5. المواد الأساسية
        $subjects = ['فيزياء وكيمياء', 'رياضيات (تحليل)', 'رياضيات (جبر وهندسة)', 'علوم طبيعية', 'لغة عربية', 'لغة إنكليزية'];
        foreach ($subjects as $s) {
            DB::table('subjects')->insert(['name' => $s, 'created_at' => now()]);
        }

        // 6. المجلد الرئيسي (Root) ومجلدات الصفوف التابعة له
        $rootFolderId = DB::table('folders')->insertGetId([
            'name'        => 'المجلد الرئيسي (الشاشة الرئيسية)',
            'is_root'     => true,
            'grade_id'    => null,
            'order_index' => 0,
            'created_at'  => now(),
        ]);

        foreach ($insertedGrades as $name => $gId) {
            $childFolderId = DB::table('folders')->insertGetId([
                'name'        => "مجلد {$name}",
                'is_root'     => false,
                'grade_id'    => $gId,
                'order_index' => 0,
                'created_at'  => now(),
            ]);

            // ربط المجلد كابن للمجلد الرئيسي في جدول الهيكلية
            DB::table('folder_hierarchy')->insert([
                'parent_folder_id' => $rootFolderId,
                'child_folder_id'  => $childFolderId,
            ]);
        }

        // 7. حساب مدير النظام (Admin)
        DB::table('users')->insert([
            'display_title'      => 'مدير النظام (Admin)',
            'first_name'         => 'المدير',
            'father_name'        => 'العام',
            'last_name'          => 'للمنصة',
            'email'              => 'admin@asatezah.com',
            'phone'              => '+963900000000',
            'teacher_type'       => 'subject_teacher',
            'status'             => 'active',
            'email_verified_at'  => now(),
            'phone_verified_at'  => now(),
            'password'           => Hash::make('password123'),
            'created_at'         => now(),
        ]);
    }
}
