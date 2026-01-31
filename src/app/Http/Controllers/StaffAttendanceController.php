<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Attendance;
use App\Models\AttendanceApply;
use App\Models\User;
use App\Models\WorkBreak;
use Carbon\Carbon;

class StaffAttendanceController extends Controller
{
    public function index(Request $request)
    {
        
        // dd(
        //     'HERE',
        //     $request->query(),   // ← これが重要
        //     $request->get('month'),
        // );

        // 表示する月を決める（例: 2024-06）
        $monthString = $request->get('month', now()->format('Y-m'));
        $month = Carbon::parse($monthString)->startOfMonth(); //例: 2024-06-01 00:00:00
        $end   = $month->copy()->endOfMonth();  //例: 2024-06-30 23:59:59
        //copy()がないと$month === 2024-06-30 23:59:59になる

        // 今月の勤怠を取得
        $attendances = Attendance::with(['breaks', 'request'])
            ->where('user_id', auth()->id())
            ->whereBetween('work_date', [$month->toDateString(), $end->toDateString()])
            ->get();

        // 日付ごとに取り出しやすくする
        $attendanceByDate = [];
        //勤怠を日付をキーにした配列 に変換している
        foreach ($attendances as $attendance) {
            $attendanceByDate[$attendance->work_date->format('Y-m-d')] = $attendance;
        }
        // 変換前（コレクション）
        // [
        //     0 => Attendance(2024-06-01),
        //     1 => Attendance(2024-06-02),
        // ]
        //変換後（配列）
        // [
        //     '2024-06-01' => Attendance(...),
        //     '2024-06-02' => Attendance(...),
        // ]

        $rows = [];

        // 月初〜月末まで1日ずつ処理
        $current = $month->copy();
        while ($current <= $end) {
            $dateKey = $current->toDateString();  // '2024-06-20'
            $attendance = $attendanceByDate[$dateKey] ?? null;

            // ★ 必須：毎日初期化
            $payload = [];
            $isPending = false;


            // ===== 出退勤 =====
            $clockIn  = null;
            $clockOut = null;

            // pending の申請があれば payload を優先
            if ($attendance && $attendance->request &&
                $attendance->request->status === AttendanceApply::STATUS_PENDING) {

                $isPending = true;
                $payload = $attendance->request->payload ?? [];

                if (!empty($payload['clock_in_at'])) {
                    $clockIn = Carbon::parse($payload['clock_in_at'])->format('H:i');
                }
                if (!empty($payload['clock_out_at'])) {
                    $clockOut = Carbon::parse($payload['clock_out_at'])->format('H:i');
                }
            }

            // 申請がなければ DB の値
            if (!$clockIn && $attendance && $attendance->clock_in_at) {
                $clockIn = $attendance->clock_in_at->format('H:i');
            }

            if (!$clockOut && $attendance && $attendance->clock_out_at) {
                $clockOut = $attendance->clock_out_at->format('H:i');
            }

            // ===== 休憩 =====
            $breakSeconds = 0;

            // payload に休憩があればそれを使う
            if (!empty($payload['breaks'])) {
                foreach ($payload['breaks'] as $break) {
                    if (!empty($break['start_at']) && !empty($break['end_at'])) {
                        $start = Carbon::parse($break['start_at']);
                        //Carbon::parse("$dateKey {$break['start_at']}");
                        $end   = Carbon::parse($break['end_at']);
                        $breakSeconds += max(0, $end->diffInSeconds($start));
                    }
                }
            }
            // なければ DB の休憩
            elseif ($attendance) {
                foreach ($attendance->breaks as $break) {
                    if ($break->start_at && $break->end_at) {
                        $breakSeconds += $break->end_at->diffInSeconds($break->start_at);
                    }
                }
            }

            // ===== 合計時間 =====
            $totalSeconds = null;

            if ($clockIn && $clockOut) {
                $in  = Carbon::parse($clockIn);
                $out = Carbon::parse($clockOut);

                $totalSeconds = max(
                    0,
                    $out->diffInSeconds($in) - $breakSeconds
                );
            }

            // ===== 行データ =====
            $rows[] = [
                'id'    => $attendance ? $attendance->id : null,
                'ymd'   => $dateKey,
                'date'  => $current->locale('ja')->isoFormat('MM/DD(dd)'),
                'in'    => $clockIn  ?? '',
                'out'   => $clockOut ?? '',

                // 休憩は「必ず秒がある」前提ならOK
                'break' => $breakSeconds > 0
                    ? gmdate('G:i', $breakSeconds)
                    : '',

                // 合計は「計算できたときだけ表示」
                'total' => $totalSeconds !== null
                    ? gmdate('G:i', $totalSeconds)
                    : '',

                // 'break' => gmdate('G:i', $breakSeconds),
                // 'total' => is_int($totalSeconds) ? gmdate('G:i', $totalSeconds) : '',
                'is_pending' => (
                    $attendance &&
                    $attendance->request &&
                    $attendance->request->status === AttendanceApply::STATUS_PENDING
                ),
            ];

            $current->addDay();
        }

        return view('staff.attendance.index', [
            'month' => $month,
            'rows'  => $rows,
            'prev'    => $month->copy()->subMonth()->format('Y-m'),
            'next'    => $month->copy()->addMonth()->format('Y-m'),
            'caption' => $month->format('Y/m'),
        ]);
    }

    public function detail($id)
    {
        // 勤怠を取得（自分の分だけ）
        $attendance = Attendance::with(['user', 'breaks', 'request'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        // 申請中かどうか
        $isPending = false;
        if ($attendance->request &&
            $attendance->request->status === AttendanceApply::STATUS_PENDING) {
            $isPending = true;
        }

        // payload（申請中なら使う）
        $payload = [];
        if ($isPending && $attendance->request->payload) {
            $payload = $attendance->request->payload;
        }


        // 出勤・退勤
        $clockIn  = null;
        $clockOut = null;

        if (!empty($payload['clock_in_at'])) {
            $clockIn = Carbon::parse($payload['clock_in_at'])->format('H:i');
        } elseif ($attendance->clock_in_at) {
            $clockIn = $attendance->clock_in_at->format('H:i');
        }

        if (!empty($payload['clock_out_at'])) {
            $clockOut = Carbon::parse($payload['clock_out_at'])->format('H:i');
        } elseif ($attendance->clock_out_at) {
            $clockOut = $attendance->clock_out_at->format('H:i');
        }

        // 休憩
        $breaks = [];

        if (!empty($payload['breaks'])) {
            foreach ($payload['breaks'] as $break) {
                $breaks[] = [
                    'start_at' => !empty($break['start_at'])
                        ? Carbon::parse($break['start_at'])->format('H:i')
                        : null,

                    'end_at' => !empty($break['end_at'])
                        ? Carbon::parse($break['end_at'])->format('H:i')
                        : null,
                ];
            }
        } else {
            foreach ($attendance->breaks as $break) {
                $breaks[] = [
                    'start_at' => $break->start_at
                        ? $break->start_at->format('H:i')
                        : null,
                    'end_at' => $break->end_at
                        ? $break->end_at->format('H:i')
                        : null,
                ];
            }
        }

        // 休憩は必ず2本分用意
        while (count($breaks) < 2) {
            $breaks[] = ['start_at' => null, 'end_at' => null];
        }

        // 表示用フォーム配列
        $form = [
            'clock_in_at'  => $clockIn,
            'clock_out_at' => $clockOut,
            'breaks'       => $breaks,
            'reason'       => $attendance->request
                                ? $attendance->request->reason
                                : '',
            'is_locked'    => $isPending,
        ];

        // 日付表示
        $date = $attendance->work_date->format('Y年n月j日');

        return view('staff.attendance.detail', [
            'attendance' => $attendance,
            'form'       => $form,
            'date'       => $date,
        ]);
    }

    public function requestIndex(Request $request)
    {
        // タブの種類（pending / approved）
        $tab = $request->get('tab', 'pending');

        // ステータスを決める
        if ($tab === 'approved') {
            $status = AttendanceApply::STATUS_APPROVED;
        } else {
            $status = AttendanceApply::STATUS_PENDING;
        }

        // 自分が出した申請を取得
        $requests = AttendanceApply::with([
                'attendance',
                'requester',
            ])
            ->where('requested_by', auth()->id())
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();

        // 表示用の配列を作る
        $rows = [];

        foreach ($requests as $requestItem) {

            // ステータス表示名
            if ($requestItem->status === AttendanceApply::STATUS_PENDING) {
                $statusLabel = '承認待ち';
            } elseif ($requestItem->status === AttendanceApply::STATUS_APPROVED) {
                $statusLabel = '承認済み';
            } else {
                $statusLabel = $requestItem->status;
            }

            // 対象日
            $targetDate = '';
            if ($requestItem->attendance && $requestItem->attendance->work_date) {
                $targetDate = $requestItem->attendance->work_date->format('Y/m/d');
            }

            // 申請者名
            $name = '';
            if ($requestItem->requester) {
                $name = $requestItem->requester->name;
            }

            // 行データ
            $rows[] = [
                'id'      => $requestItem->attendance_id,
                'status'  => $statusLabel,
                'name'    => $name,
                'target'  => $targetDate,
                'reason'  => $requestItem->reason ?? '',
                'applied' => $requestItem->created_at
                                ? $requestItem->created_at->format('Y/m/d')
                                : '',
            ];
        }

        return view('staff.attendance.requests', [
            'rows' => $rows,
            'tab'  => $tab,
        ]);
    }
}
