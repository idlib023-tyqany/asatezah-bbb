<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class TaxonomyController extends Controller
{
    // جلب الهيكل الكامل (مراحل، صفوف، فروعها)
    public function getHierarchy()
    {
        $stages = DB::table('educational_stages')
            ->orderBy('order_index')
            ->get();

        foreach ($stages as $stage) {
            $stage->grades = DB::table('grades')
                ->where('stage_id', $stage->id)
                ->orderBy('order_index')
                ->get();

            foreach ($stage->grades as $grade) {
                $grade->tracks = DB::table('grade_track_pivot')
                    ->join('tracks', 'grade_track_pivot.track_id', '=', 'tracks.id')
                    ->where('grade_track_pivot.grade_id', $grade->id)
                    ->select('tracks.id', 'tracks.name')
                    ->get();
            }
        }

        $subjects = DB::table('subjects')->get();

        return response()->json([
            'status'   => 'success',
            'stages'   => $stages,
            'subjects' => $subjects,
        ]);
    }
}
