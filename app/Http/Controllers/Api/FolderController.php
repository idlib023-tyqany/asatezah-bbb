<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FolderController extends Controller
{
    // جلب المجلد الرئيسي (Root) مع أبنائه المباشرين
    public function getRoot()
    {
        $root = DB::table('folders')->where('is_root', true)->first();
        if (!$root) {
            return response()->json(['status' => 'error', 'message' => 'المجلد الرئيسي غير موجود'], 404);
        }
        return $this->getFolderDetails($root->id);
    }

    // استعراض أي مجلد محدد مع أبنائه وملفاته (يدعم تعدد الآباء)
    public function getFolderDetails($id)
    {
        $folder = DB::table('folders')->where('id', $id)->first();
        if (!$folder) {
            return response()->json(['status' => 'error', 'message' => 'المجلد غير موجود'], 404);
        }

        // الأبناء المباشرين لهذا المجلد عبر جدول الهيكلية
        $subfolders = DB::table('folder_hierarchy')
            ->join('folders', 'folder_hierarchy.child_folder_id', '=', 'folders.id')
            ->where('folder_hierarchy.parent_folder_id', $id)
            ->select('folders.*')
            ->get();

        // الملفات داخل هذا المجلد
        $files = DB::table('files')->where('folder_id', $id)->get();

        return response()->json([
            'status'      => 'success',
            'current'     => $folder,
            'subfolders'  => $subfolders,
            'files'       => $files,
        ]);
    }
}
