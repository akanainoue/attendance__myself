<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\AttendanceApply;
use App\Http\Requests\AttendanceRequest;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StaffRequestController extends Controller
{
    /**
     * 勤怠詳細画面からの修正申請 post
     */
    public function upsert(AttendanceRequest $request, $id)
    {
        // ① 自分の勤怠データを取得
        $attendance = Attendance::with('request')
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        // ② すでに申請中なら何もしない（連打対策）
        if ($attendance->request &&
            $attendance->request->status === AttendanceApply::STATUS_PENDING) {

            return redirect("/attendance/detail/{$attendance->id}");
        }

        // ③ 修正申請を作成（勤怠はまだ変更しない）
        AttendanceApply::create([
            'attendance_id' => $attendance->id,
            'requested_by'  => auth()->id(),
            'status'        => AttendanceApply::STATUS_PENDING,
            'reason'        => $request->input('reason'),

            // 「こう直したい」という内容をそのまま保存
            'payload' => [
                'work_date'   => $attendance->work_date->toDateString(),
                'clock_in_at' => $request->input('clock_in_at'),
                'clock_out_at'=> $request->input('clock_out_at'),
                'breaks'      => $request->input('breaks', []),
            ],
        ]);

        // ④ 詳細画面へ戻す（申請中メッセージ付き）
        return redirect("/attendance/detail/{$attendance->id}")
            ->with('status', '修正申請を送信しました。承認をお待ちください。');
    }

    /**
     * 日付指定で勤怠詳細（勤怠がまだ無い日）
     */
    public function showByDate(string $ymd)
    {
        $attendance = Attendance::firstOrCreate([
            'user_id'   => auth()->id(),
            'work_date' => $ymd,
        ]);

        return redirect("/attendance/detail/{$attendance->id}");
    }
}



// 勤怠修正の流れ

// ユーザーが勤怠詳細画面で時間を修正する
// 「修正」ボタンを押す
// attendance テーブルは変更しない
// attendance_requests テーブルに申請を1件作る
// 管理者が承認したら、はじめて勤怠が確定変更される
