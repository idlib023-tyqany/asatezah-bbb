<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class TeacherGeminiController extends Controller
{
    public function handle(Request $request)
    {
        $request->validate([
            'prompt'    => 'required|string',
            'mode'      => 'required|string',
            'folder_id' => 'nullable|integer',
        ]);

        $apiKey = config('services.gemini.api_key', env('GEMINI_API_KEY'));
        
        $folderContext = '';
        if ($request->filled('folder_id')) {
            $folder = DB::table('folders')->where('id', $request->folder_id)->first();
            if ($folder) {
                $folderContext = "أنت تجيب استناداً إلى سياق مجلد المقرر الدراسي: [{$folder->name}]. ";
            }
        }

        $instruction = $folderContext . match ($request->mode) {
            'lesson_plan'   => "أنت مساعد المعلم، صغ خطة درس نموذجية تشمل الأهداف السلوكية، التهيئة الحافزة، خطوات سير الدرس، والتقويم.",
            'exam'          => "أنت خبير امتحاني، صغ أسئلة اختبار مؤتمتة ومسائل مع مفتاح الحل وتوزيع الدرجات الدقيق.",
            'grading_scale' => "أنت حاسبة سلالم الدرجات للأستاذ، قم بتوزيع علامات الامتحان أو السؤال بالتفصيل المالي والدقيق (درجة كتابة القانون، درجة التعويض، درجة الحساب، درجة الواحدة) مع تفصيل الأرباع والأنصاف ليكون التصحيح عادلاً ومريحاً.",
            'homework'      => "أنت منتقي الواجبات المنزلية، اقترح أفضل 3 مسائل أو تمارين متوازنة وغير مجهدة من كتاب المنهاج كواجب بيتي لدرس اليوم.",
            'solve'         => "حل المسألة خطوة بخطوة بالبرهان والتعليل الرياضي/العلمي المفصل.",
            'simplify'      => "بسط النظرية أو المفهوم الصعب مستخدماً استعارات واقعية، واقترح تجارب صفية بديلة بمواد بسيطة رخيصة من البيئة.",
            'formula_sheet' => "لخص قوانين ورموز ووحدات القياس الدولية (SI) الخاصة بهذه الوحدة في بطاقة مراجعة سريعة مرتبة جداً.",
            default         => "أنت المساعد الأكاديمي الشامل للمعلم في منصة ASATEZAH."
        };

        if (empty($apiKey)) {
            return response()->json([
                'status' => 'success',
                'result' => "تم استلام الطلب بنمط ({$request->mode}). (يرجى إضافة GEMINI_API_KEY في ملف .env للحصول على الرد الذكي المباشر)."
            ]);
        }

        try {
            $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent?key={$apiKey}", [
                'systemInstruction' => ['parts' => [['text' => $instruction]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $request->prompt]]]]
            ]);

            return response()->json([
                'status' => 'success',
                'result' => $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'تم إتمام الطلب.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'تعذر الاتصال بـ Gemini: ' . $e->getMessage()
            ], 500);
        }
    }
}
