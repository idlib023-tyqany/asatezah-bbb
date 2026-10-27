<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SupportCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'support:cleanup
                            {--days=60 : Number of days old messages must be to be deleted}
                            {--dry-run : Simulate the cleanup process without actually deleting data}
                            {--disk=public : The storage disk to delete images from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently and safely delete old support messages and their associated storage files.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $isDryRun = $this->option('dry-run');
        $diskName = $this->option('disk');

        $cutoff = Carbon::now()->subDays($days);

        // جلب عدد السجلات المستهدفة
        $query = Message::where('created_at', '<', $cutoff);
        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info("No support messages older than {$days} days found to clean up.");
            return self::SUCCESS;
        }

        // التعامل مع وضع المعاينة المطور
        if ($isDryRun) {
            $this->warn("[Dry Run Mode] Found {$totalCount} messages older than {$days} days eligible for deletion.");

            // ميزة إضافية: عرض عينة من السجلات المستهدفة للمعاينة
            $sample = Message::where('created_at', '<', $cutoff)->latest()->take(5)->get(['id', 'created_at', 'image_path']);

            $this->table(
                ['ID', 'Created At', 'Image Path'],
                $sample->map(fn($msg) => [$msg->id, $msg->created_at, $msg->image_path ?? 'No Image'])->toArray()
            );

            $this->info("No changes were made.");
            return self::SUCCESS;
        }

        $this->info("Found {$totalCount} messages to cleanup (Older than {$days} days). Starting process...");
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $deletedCount = 0;
        $failedCount = 0;
        $disk = Storage::disk($diskName);

        // معالجة البيانات على دفعات مع حماية ضد توقف السكربت
        Message::where('created_at', '<', $cutoff)
            ->chunkById(200, function ($messages) use (&$deletedCount, &$failedCount, $bar, $disk) {
                $idsToDelete = [];

                foreach ($messages as $message) {
                    try {
                        // حذف الملف الفعلي إن وجد
                        if (! empty($message->image_path) && $disk->exists($message->image_path)) {
                            $disk->delete($message->image_path);
                        }

                        $idsToDelete[] = $message->id;
                        $deletedCount++;
                    } catch (Throwable $e) {
                        // تسجيل الخطأ الفردي واستمرار باقي العملية
                        Log::error("SupportCleanup: Failed to process message ID {$message->id}. Error: {$e->getMessage()}");
                        $failedCount++;
                    }

                    $bar->advance();
                }

                // الحذف الفعلي دفعة واحدة داخل معاملة قاعدة بيانات لضمان السلامة
                if (! empty($idsToDelete)) {
                    DB::transaction(function () use ($idsToDelete) {
                        Message::whereIn('id', $idsToDelete)->forceDelete();
                    });
                }
            });

        $bar->finish();
        $this->newLine(2);

        // تسجيل النتيجة النهائية في السجلات
        Log::info("SupportCleanup completed. Deleted: {$deletedCount}, Failed: {$failedCount}.");

        // عرض تقرير نهائي احترافي على شكل جدول
        $this->info("Cleanup process finished successfully!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Successfully Deleted', $deletedCount],
                ['Failed Operations', $failedCount],
                ['Target Disk', $diskName],
            ]
        );

        if ($failedCount > 0) {
            $this->warn("Completed with {$failedCount} errors. Please check Laravel storage logs for details.");
        }

        return self::SUCCESS;
    }
}
