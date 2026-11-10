<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TeacherGeminiService
{
    public function answer(string $prompt, string $mode, ?int $folderId = null): string
    {
        $apiKey = config('services.gemini.api_key', env('GEMINI_API_KEY'));
        if (empty($apiKey)) {
            throw new RuntimeException('يجب ضبط GEMINI_API_KEY قبل استخدام المساعد الذكي.');
        }

        $folderContext = '';
        if ($folderId !== null) {
            $folder = DB::table('folders')->where('id', $folderId)->first();
            if ($folder) {
                $folderContext = "أنت تجيب استناداً إلى سياق مجلد المقرر الدراسي: [{$folder->name}]. ";
            }
        }

        $instruction = $folderContext . match ($mode) {
            'lesson_plan' => 'أنت مساعد المعلم، صغ خطة درس نموذجية تشمل الأهداف السلوكية، التهيئة الحافزة، خطوات سير الدرس، والتقويم.',
            'exam' => 'أنت خبير امتحاني، صغ أسئلة اختبار مؤتمتة ومسائل مع مفتاح الحل وتوزيع الدرجات الدقيق.',
            'grading_scale' => 'أنت حاسبة سلالم الدرجات للأستاذ، قم بتوزيع علامات الامتحان أو السؤال بالتفصيل المالي والدقيق (درجة كتابة القانون، درجة التعويض، درجة الحساب، درجة الواحدة) مع تفصيل الأرباع والأنصاف ليكون التصحيح عادلاً ومريحاً.',
            'homework' => 'أنت منتقي الواجبات المنزلية، اقترح أفضل 3 مسائل أو تمارين متوازنة وغير مجهدة من كتاب المنهاج كواجب بيتي لدرس اليوم.',
            'solve' => 'حل المسألة خطوة بخطوة بالبرهان والتعليل الرياضي/العلمي المفصل.',
            'simplify' => 'بسط النظرية أو المفهوم الصعب مستخدماً استعارات واقعية، واقترح تجارب صفية بديلة بمواد بسيطة رخيصة من البيئة.',
            'formula_sheet' => 'لخص قوانين ورموز ووحدات القياس الدولية (SI) الخاصة بهذه الوحدة في بطاقة مراجعة سريعة مرتبة جداً.',
            default => 'أنت المساعد الأكاديمي الشامل للمعلم في منصة ASATEZAH.',
        };

        $response = Http::timeout(30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent?key={$apiKey}",
            [
                'systemInstruction' => ['parts' => [['text' => $instruction]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            ],
        )->throw();

        $answer = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($answer) || trim($answer) === '') {
            throw new RuntimeException('استجابة Gemini لا تحتوي على إجابة صالحة.');
        }

        return $answer;
    }
}
