<?php

namespace App\Console\Commands;

use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TrashCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trash:cleanup
                            {--days=30 : Number of days files must be in trash before permanent deletion}
                            {--dry-run : Simulate the cleanup process without permanently deleting files}
                            {--disk= : The storage disk to delete files from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently and safely delete files that have been in the trash for over the specified period.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $isDryRun = $this->option('dry-run');
        $diskName = $this->option('disk') ?: config('filesystems.default');

        $cutoff = Carbon::now()->subDays($days);

        // بناء الاستعلام الأساسي للملفات المحذوفة مؤقتاً
        $baseQuery = File::onlyTrashed()->where('deleted_at', '<', $cutoff);
        $totalCount = $baseQuery->count();

        if ($totalCount === 0) {
            $this->info("No trashed files older than {$days} days found to clean up.");

            return self::SUCCESS;
        }

        // دعم وضع المعاينة المطور مع عرض عينة تفصيلية
        if ($isDryRun) {
            $this->warn("[Dry Run Mode] Found {$totalCount} trashed files older than {$days} days eligible for permanent deletion.");

            $sample = File::onlyTrashed()
                ->where('deleted_at', '<', $cutoff)
                ->latest('deleted_at')
                ->take(5)
                ->get();

            $this->table(
                ['ID', 'Path', 'Deleted At'],
                $sample->map(fn ($file) => [$file->id, $file->path, $file->deleted_at])->toArray()
            );

            $this->info('No changes were made.');

            return self::SUCCESS;
        }

        $this->info("Found {$totalCount} trashed files to permanently remove (Older than {$days} days). Starting cleanup...");
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $deletedCount = 0;
        $failedCount = 0;
        $disk = Storage::disk($diskName);

        // معالجة البيانات على دفعات باستخدام chunkById لتوفير الذاكرة
        File::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->chunkById(200, function ($files) use (&$deletedCount, &$failedCount, $bar, $disk) {
                $idsToDelete = [];

                foreach ($files as $file) {
                    try {
                        // حذف الملف الأصلي من نظام التخزين إن وجد (تم إزالة الرمز الخاطئ هنا)
                        if (! empty($file->path) && $disk->exists($file->path)) {
                            $disk->delete($file->path);
                        }

                        // حذف الصورة المصغرة إن وجدت (تأكد من أن العمود موجود في جدولك)
                        if (! empty($file->thumbnail) && $disk->exists($file->thumbnail)) {
                            $disk->delete($file->thumbnail);
                        }

                        $idsToDelete[] = $file->id;
                        $deletedCount++;
                    } catch (Throwable $e) {
                        Log::error("TrashCleanup: Failed to process file ID {$file->id}. Error: {$e->getMessage()}");
                        $failedCount++;
                    }

                    $bar->advance();
                }

                // حذف السجلات نهائياً من قاعدة البيانات دفعة واحدة داخل معاملة آمنة
                if (! empty($idsToDelete)) {
                    DB::transaction(function () use ($idsToDelete) {
                        File::onlyTrashed()->whereIn('id', $idsToDelete)->forceDelete();
                    });
                }
            });

        $bar->finish();
        $this->newLine(2);

        // تسجيل النتيجة النهائية في السجلات
        Log::info("TrashCleanup completed. Permanently deleted: {$deletedCount}, Failed: {$failedCount}.");

        // عرض تقرير نهائي احترافي على شكل جدول
        $this->info('Trash cleanup process finished successfully!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Successfully Deleted', $deletedCount],
                ['Failed Operations', $failedCount],
                ['Target Disk', $diskName],
            ]
        );

        if ($failedCount > 0) {
            $this->warn("Completed with {$failedCount} errors. Please check storage logs for details.");
        }

        return self::SUCCESS;
    }
}
