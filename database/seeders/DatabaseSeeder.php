<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $primaryId = $this->firstOrCreateId('educational_stages', ['name' => 'المرحلة الابتدائية'], [
                'order_index' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $prepId = $this->firstOrCreateId('educational_stages', ['name' => 'المرحلة الإعدادية'], [
                'order_index' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $secId = $this->firstOrCreateId('educational_stages', ['name' => 'المرحلة الثانوية'], [
                'order_index' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $grades = [
                ['stage_id' => $primaryId, 'name' => 'الصف الأول الأساسي', 'order_index' => 1],
                ['stage_id' => $primaryId, 'name' => 'الصف الثاني الأساسي', 'order_index' => 2],
                ['stage_id' => $primaryId, 'name' => 'الصف الثالث الأساسي', 'order_index' => 3],
                ['stage_id' => $primaryId, 'name' => 'الصف الرابع الأساسي', 'order_index' => 4],
                ['stage_id' => $primaryId, 'name' => 'الصف الخامس الأساسي', 'order_index' => 5],
                ['stage_id' => $primaryId, 'name' => 'الصف السادس الأساسي', 'order_index' => 6],
                ['stage_id' => $prepId, 'name' => 'الصف السابع الأساسي', 'order_index' => 7],
                ['stage_id' => $prepId, 'name' => 'الصف الثامن الأساسي', 'order_index' => 8],
                ['stage_id' => $prepId, 'name' => 'الصف التاسع (شهادة التعليم الأساسي)', 'order_index' => 9],
                ['stage_id' => $secId, 'name' => 'الصف العاشر (الأول الثانوي)', 'order_index' => 10],
                ['stage_id' => $secId, 'name' => 'الصف الحادي عشر (الثاني الثانوي)', 'order_index' => 11],
                ['stage_id' => $secId, 'name' => 'الثالث الثانوي (شهادة البكالوريا)', 'order_index' => 12],
            ];

            $insertedGrades = [];
            foreach ($grades as $grade) {
                $insertedGrades[$grade['name']] = $this->firstOrCreateId(
                    'grades',
                    ['stage_id' => $grade['stage_id'], 'name' => $grade['name']],
                    ['order_index' => $grade['order_index'], 'created_at' => now(), 'updated_at' => now()]
                );
            }

            $tracks = ['علمي', 'أدبي', 'شرعي', 'صناعي', 'تجاري', 'تقني'];
            $trackIds = [];
            foreach ($tracks as $track) {
                $trackIds[$track] = $this->firstOrCreateId('tracks', ['name' => $track], [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $gradeTracks = [
                ['grade_id' => $insertedGrades['الثالث الثانوي (شهادة البكالوريا)'], 'track_id' => $trackIds['علمي']],
                ['grade_id' => $insertedGrades['الثالث الثانوي (شهادة البكالوريا)'], 'track_id' => $trackIds['أدبي']],
                ['grade_id' => $insertedGrades['الثالث الثانوي (شهادة البكالوريا)'], 'track_id' => $trackIds['شرعي']],
                ['grade_id' => $insertedGrades['الصف العاشر (الأول الثانوي)'], 'track_id' => $trackIds['علمي']],
                ['grade_id' => $insertedGrades['الصف العاشر (الأول الثانوي)'], 'track_id' => $trackIds['شرعي']],
            ];
            foreach ($gradeTracks as $gradeTrack) {
                $this->insertIfMissing('grade_track_pivot', $gradeTrack);
            }

            $subjects = ['فيزياء وكيمياء', 'رياضيات (تحليل)', 'رياضيات (جبر وهندسة)', 'علوم طبيعية', 'لغة عربية', 'لغة إنكليزية'];
            foreach ($subjects as $subject) {
                $this->firstOrCreateId('subjects', ['name' => $subject], [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $rootFolderId = $this->firstOrCreateId('folders', [
                'name' => 'المجلد الرئيسي (الشاشة الرئيسية)',
                'is_root' => true,
                'grade_id' => null,
            ], [
                'order_index' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($insertedGrades as $name => $gradeId) {
                $childFolderId = $this->firstOrCreateId('folders', [
                    'name' => "مجلد {$name}",
                    'is_root' => false,
                    'grade_id' => $gradeId,
                ], [
                    'order_index' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->insertIfMissing('folder_hierarchy', [
                    'parent_folder_id' => $rootFolderId,
                    'child_folder_id' => $childFolderId,
                ]);
            }

            $email = 'admin@asatezah.com';
            $admin = DB::table('users')->where('email', $email)->first(['id']);
            $adminAttributes = [
                'name' => 'المدير العام للمنصة',
                'display_name' => 'مدير النظام (Admin)',
                'phone' => '+963900000000',
                'phone_number' => '+963900000000',
                'trainer_type' => null,
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'updated_at' => now(),
            ];

            if ($admin) {
                DB::table('users')->where('id', $admin->id)->update($adminAttributes);
            } else {
                DB::table('users')->insert(array_merge($adminAttributes, [
                    'email' => $email,
                    'password' => Hash::make('password123'),
                    'created_at' => now(),
                ]));
            }
        });
    }

    private function firstOrCreateId(string $table, array $identity, array $values): int
    {
        $id = DB::table($table)->where($identity)->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        return (int) DB::table($table)->insertGetId($identity + $values);
    }

    private function insertIfMissing(string $table, array $identity): void
    {
        if (! DB::table($table)->where($identity)->exists()) {
            DB::table($table)->insert($identity);
        }
    }
}
