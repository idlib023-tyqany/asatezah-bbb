<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\FileIdService;

class FileController extends Controller
{
    public function index()
    {
        $files = DB::table('files')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($files);
    }

    // تفاصيل ملف محدد (مع عدادات المشاهدة)
    public function show($id)
    {
        $file = DB::table('files')->where('id', $id)->first();
        if (!$file) {
            return response()->json(['status' => 'error', 'message' => 'الملف غير موجود'], 404);
        }

        // زيادة عداد المشاهدات
        DB::table('files')->where('id', $id)->increment('views_count');

        $comments = DB::table('file_comments')
            ->join('users', 'file_comments.user_id', '=', 'users.id')
            ->where('file_id', $id)
            ->select('file_comments.*', 'users.display_name')
            ->latest()
            ->get();

        return response()->json([
            'status'   => 'success',
            'file'     => $file,
            'comments' => $comments,
        ]);
    }

    // تبديل حالة المفضلة (قلب الحب)
    public function toggleFavorite(Request $request, $id)
    {
        $userId = $request->user()->id;
        $exists = DB::table('favorites')->where(['user_id' => $userId, 'file_id' => $id])->first();

        if ($exists) {
            DB::table('favorites')->where(['user_id' => $userId, 'file_id' => $id])->delete();
            DB::table('files')->where('id', $id)->decrement('favorites_count');
            return response()->json(['status' => 'success', 'is_favorite' => false]);
        } else {
            DB::table('favorites')->insert([
                'user_id'    => $userId,
                'file_id'    => $id,
                'created_at' => now(),
            ]);
            DB::table('files')->where('id', $id)->increment('favorites_count');
            return response()->json(['status' => 'success', 'is_favorite' => true]);
        }
    }

    // إرسال بلاغ عن ملف
    public function reportFile(Request $request, $id)
    {
        $validated = $request->validate([
            'reason'  => 'required|string',
            'message' => 'required|string',
        ]);

        DB::table('file_reports')->insert([
            'file_id'      => $id,
            'reporter_id'  => $request->user()->id,
            'reason'       => $validated['reason'],
            'message'      => $validated['message'],
            'status'       => 'pending',
            'created_at'   => now(),
        ]);

        return response()->json(['status' => 'success', 'message' => 'تم استلام بلاغك وسيقوم الأدمن بمراجعته']);
    }
}
