<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\AttendanceApply;
use App\Models\WorkBreak;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PunchController extends Controller
{
    public function show()
    {
        //date()	サーバーのタイムゾーン（例：日本なら JST）
        //gmdate()	GMT（タイムゾーンなし）
        //どちらも文字列

        // 今日の勤怠データを取得（なければ作成）
        $attendance = Attendance::firstOrCreate([
            'user_id'   => auth()->id(),
            'work_date' => now()->toDateString(),
        ]);

        // 画面を表示
        return view('staff.attendance.punch', [
            'attendance' => $attendance,
            'status'     => $attendance->status(),
            // 'date'       => now()->format('Y年n月j日(D)'),
            'date'   => now()->locale('ja')->isoFormat('YYYY年M月D日(dd)'),
            'time'       => now()->format('H:i'),
        ]);
    }

    public function clockIn()
    {
        // 今日の勤怠を取得（なければ作成）
        $attendance = Attendance::firstOrCreate([
            'user_id'   => auth()->id(),
            'work_date' => now()->toDateString(),
        ]);

        // すでに出勤していたらエラー
        if ($attendance->clock_in_at !== null) {
            abort(422, 'すでに出勤しています');
        }
        //Blade で session() 表示

        // 出勤時間を保存　// 現在時刻（秒は使わない）
        $attendance->clock_in_at = now()->seconds(0);
        $attendance->save();

        return redirect('/attendance');
    }

    public function breakStart()
    {
        $attendance = Attendance::firstOrCreate([
            'user_id'   => auth()->id(),
            'work_date' => now()->toDateString(),
        ]);

        // 出勤していない or 退勤済み
        if ($attendance->status() == 'off' || $attendance->status() == 'done') {
            abort(422, '休憩に入れません');
        }

        // すでに休憩中か確認
        if ($attendance->status() == 'breaking') {
            abort(422, 'すでに休憩中です');
        }

        // 休憩開始を記録
        $attendance->breaks()->create([
            'start_at' => now()->seconds(0) ,
        ]);

        return redirect('/attendance');
    }

    public function breakEnd()
    {
        $attendance = Attendance::firstOrCreate([
            'user_id'   => auth()->id(),
            'work_date' => now()->toDateString(),
        ]);

        // 開いている休憩を取得
        $break = $attendance->currentBreak();

        if (! $break || $break->isClosed()) {
            abort(422, '休憩中ではありません');
        }

        // 休憩終了
        $break->end_at = now()->seconds(0);
        $break->save();

        return redirect('/attendance');
    }

    public function clockOut()
    {
        $attendance = Attendance::firstOrCreate([
            'user_id'   => auth()->id(),
            'work_date' => now()->toDateString(),
        ]);

        // 出勤していない or 退勤済み
        if ($attendance->status() == 'off') {
            abort(422, 'まだ出勤していません');
        }

        if ($attendance->status() == 'done') {
            abort(422, 'すでに退勤しています');
        }

        // 開いている休憩があれば閉じる
        $break = $attendance->currentBreak();
        if ($break && $break->isOpen()) {
            $break->end_at = now()->seconds(0);
            $break->save();
        }

        // 退勤時間を保存
        $attendance->clock_out_at = now()->seconds(0);
        $attendance->save();

        // 修正申請（下書き）を作成
        AttendanceApply::firstOrCreate([
            'attendance_id' => $attendance->id,
            'requested_by'  => auth()->id(),
            'status'        => 'pending',
        ], [
            'reason'  => null,
            'payload' => $this->makePayload($attendance),
        ]);

        return redirect('/attendance')
            ->with('status', '退勤しました');
    }

    private function makePayload(Attendance $attendance)
    {
        $breaks = [];

        foreach ($attendance->breaks()->orderBy('start_at')->get() as $break) {
            $breaks[] = [
                'start_at' => $break->start_at
                    ? $break->start_at
                    : null,
                'end_at'   => $break->end_at
                    ? $break->end_at
                    : null,
            ];
        }

        return [
            'clock_in_at'  => $attendance->clock_in_at
                ? $attendance->clock_in_at
                : null,
            'clock_out_at' => $attendance->clock_out_at
                ? $attendance->clock_out_at
                : null,
            'breaks'       => $breaks,
        ];
        //$table->json('payload')->nullable();
        // Laravel は JSON に保存するときCarbon オブジェクトを自動で文字列にシリアライズする
        // Carbon::parse（）に入れて表記変換
        //->format('Y-m-d H:i:s')
    }

}

//$attendance->clock_in_at	✅ Carbon
//makePayload() 内	✅ Carbon
//JSON 保存時	❌ 文字列  //自動的に->format('Y-m-d H:i:s')
//DB から取得後 $payload[...]	❌ 文字列
//Carbon::parse() 後	✅ Carbon