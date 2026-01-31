<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceApply;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;


class AdminAttendanceController extends Controller
{
    public function index(Request $request)
    {
        // 表示日（なければ今日）
        $date = Carbon::parse($request->get('date', now()->toDateString()));

        // 勤怠（または申請）を取得
        $attendances = Attendance::with(['user', 'breaks', 'request'])
            ->whereDate('work_date', $date->toDateString())
            ->where(function ($q) {
                $q->whereHas('request', function ($q2) {
                    $q2->where('status', AttendanceApply::STATUS_PENDING);
                })
                ->orWhereNotNull('clock_in_at');
            })
            ->get();

        // 出勤時刻でソート（payload優先）
        $attendances = $attendances->sortBy(function ($attendance) {
            if (
                $attendance->request &&
                $attendance->request->status === AttendanceApply::STATUS_PENDING &&
                !empty($attendance->request->payload['clock_in_at'])
            ) {
                return $attendance->request->payload['clock_in_at'];
            }

            return $attendance->clock_in_at ?? '99:99';
        });

        $rows = [];

        foreach ($attendances as $attendance) {

            // ===== 出退勤 =====
            $clockIn  = null;
            $clockOut = null;

            // pending の申請があれば payload を優先
            if ($attendance && $attendance->request &&
                $attendance->request->status === AttendanceApply::STATUS_PENDING) {

                $payload = $attendance->request->payload ?? [];

                $clockIn  = Carbon::parse($payload['clock_in_at'])->format('H:i') ?? null;
                $clockOut = Carbon::parse($payload['clock_out_at'])->format('H:i') ?? null;
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

            $rows[] = [
                'user_id'       => $attendance->user->id,
                'name'          => $attendance->user->name,
                'in'            => $clockIn,
                'out'           => $clockOut,
                'break'         => gmdate('G:i', $breakSeconds),
                'total'         => $totalSeconds !== null
                    ? gmdate('G:i', $totalSeconds)
                    : '',
                'attendance_id' => $attendance->id,
                'is_pending'    => (
                    $attendance &&
                    $attendance->request &&
                    $attendance->request->status === AttendanceApply::STATUS_PENDING
                ),
            ];
        }

        return view('admin.attendance.index', [
            'date' => $date,
            'rows' => $rows,
            'prev' => $date->copy()->subDay()->toDateString(),
            'next' => $date->copy()->addDay()->toDateString(),
        ]);
    }

    public function detail($id)
    {
        // 勤怠を取得
        $attendance = Attendance::with(['user', 'breaks', 'request'])
            ->findOrFail($id);

        // 申請中かどうか
        $isPending = false;
        $payload = [];

        if ($attendance->request &&
            $attendance->request->status === AttendanceApply::STATUS_PENDING) {
            $isPending = true;
            $payload = $attendance->request->payload ?? [];
        }

        // 出勤・退勤（payload → DB の順）
        $clockIn = null;
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
                    'end_at'   => $break->end_at
                        ? $break->end_at->format('H:i')
                        : null,
                ];
            }
        }

        // 休憩は2本分必ず用意
        while (count($breaks) < 2) {
            $breaks[] = ['start_at' => null, 'end_at' => null];
        }

        // 表示用フォームデータ
        $form = [
            'clock_in_at'  => $clockIn,
            'clock_out_at' => $clockOut,
            'breaks'       => $breaks,
            'reason'       => $attendance->request
                                ? $attendance->request->reason
                                : '',
            'is_pending'   => $isPending,
        ];

        // 日付表示
        $date = Carbon::parse($attendance->work_date)->format('Y年n月j日');

        return view('admin.attendance.detail', [
            'attendance' => $attendance,
            'form'       => $form,
            'date'       => $date,
        ]);
    }

    // public function showByDate(int $userId, string $ymd)
    // {
    //     // ユーザー存在確認
    //     $user = User::findOrFail($userId);

    //     // 日付の正規化（保険）
    //     $date = Carbon::parse($ymd)->toDateString();
        
    //     $attendance = Attendance::firstOrCreate([
    //         'user_id'   => $user->id,
    //         'work_date' => $date,
    //     ]);
        
    //     return redirect("/admin/attendance/{$attendance->id}");
    // }
    //管理者側は勤怠idがあるものだけ詳細を開ける

    public function update(AdminAttendanceUpdateRequest $request, $id)
    {
        $attendance = Attendance::with(['breaks', 'request'])
            ->findOrFail($id);

        $date = $attendance->work_date->toDateString();

        // 出勤・退勤
        $clockIn  = $this->mergeTime($date, $request->input('clock_in_at'));
        $clockOut = $this->mergeTime($date, $request->input('clock_out_at'));

        DB::transaction(function () use ($attendance, $clockIn, $clockOut, $request, $date) {

            $attendance->update([
                'clock_in_at'  => $clockIn,
                'clock_out_at' => $clockOut,
                'note'         => $request->input('note'),
            ]);

            $attendance->breaks()->delete();

            foreach ($request->input('breaks', []) as $break) {
                if (!empty($break['start_at']) && !empty($break['end_at'])) {
                    $attendance->breaks()->create([
                        'start_at' => $this->mergeTime($date, $break['start_at']),
                        'end_at'   => $this->mergeTime($date, $break['end_at']),
                    ]);
                }
            }
        });

        return redirect("/admin/attendance/{$attendance->id}")
            ->with('status', '勤怠を更新しました');
    }
    //疑問点
    //更新する際になぜ「休憩は全部消す」のに「勤怠（出退勤）は消さなの？//勤怠は1レコード・単純上書きできる。休憩は複数あり編集が複雑だから全削除が安全。

    private function mergeTime(string $date, ?string $time): ?Carbon
    {
        if (empty($time)) {
            return null;
        }
        // 例: "2024-06-20 09:00"
        return Carbon::parse("{$date} {$time}");
    }


    public function staffIndex()
    {
        // スタッフ一覧を取得（名前順）
        $staffs = User::orderBy('name')->get(['id', 'name', 'email']);

        // 画面に渡す
        return view('admin.attendance.staffs', [
            'staffs' => $staffs,
        ]);
    }

    public function indexByStaff(Request $request, int $id)
    {
        // dd(
        //     'HERE',
        //     $request->all(),
        //     __FILE__,
        //     __LINE__
        // );
        // スタッフ取得
        $user = User::findOrFail($id);

        // 表示する月
        $monthString = $request->get('month', now()->format('Y-m'));
        // $month = Carbon::parse($monthString . '-01');
        $month = Carbon::parse($monthString)->startOfMonth(); 
        $end   = $month->copy()->endOfMonth();

        // 勤怠を取得
        $attendances = Attendance::with(['breaks', 'request'])
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [
                $month->toDateString(),
                $end->toDateString()
            ])
            ->get();

        // 日付キーでまとめる
        $attendanceByDate = [];
        foreach ($attendances as $attendance) {
            $attendanceByDate[$attendance->work_date->toDateString()] = $attendance;
        }

        //  表示用行データ作成
        $rows = [];
        $current = $month->copy();

        while ($current <= $end) {
            // ★ 必須：毎日初期化
            $payload = [];
            $isPending = false;

            $dateKey = $current->toDateString();
            $attendance = $attendanceByDate[$dateKey] ?? null;
;
            

            // --- 出勤・退勤 ---
            $clockIn  = '';
            $clockOut = '';

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



            // --- 休憩 ---
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

            $totalSeconds = null;

            if ($clockIn && $clockOut) {
                $in  = Carbon::parse($clockIn);
                $out = Carbon::parse($clockOut);

                $totalSeconds = max(
                    0,
                    $out->diffInSeconds($in) - $breakSeconds
                );
            }

            $rows[] = [
                'id'      => $attendance ? $attendance->id : null,
                'ymd'   => $dateKey,
                'date'    => $current->locale('ja')->isoFormat('MM/DD(dd)'),
                'in'      => $clockIn,
                'out'     => $clockOut,
                'break'   => $breakSeconds > 0
                    ? gmdate('G:i', $breakSeconds)
                    : '',
                'total'   => $totalSeconds !== null
                    ? gmdate('G:i', $totalSeconds)
                    : '',
                'pending' => (
                    $attendance &&
                    $attendance->request &&
                    $attendance->request->status === AttendanceApply::STATUS_PENDING
                ),
            ];

            $current->addDay();
        }
        

        // CSV 出力
        if ($request->get('export') === 'csv') {
            return $this->exportCsv(
                "{$user->name}_{$month->format('Y-m')}.csv",
                $rows
            );
        }
        // dd(
        //             count($rows),
        //             last($rows),
        //             array_column($rows, 'ymd')
        //         );
        return view('admin.attendance.individual', [
            'user'  => $user,
            'month' => $month,
            'rows'  => $rows,
            'prev' => $month->copy()->subMonth()->format('Y-m'),
            'next' => $month->copy()->addMonth()->format('Y-m'),
            'caption' => $month->format('Y/m'),
        ]);

        
    }

    private function exportCsv(string $filename, array $rows)
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');  //「ファイルを作らず、画面（レスポンス）に直接書く」

            // ★ これを最初に必ず書く（UTF-8 BOM）日本語だと認識させる
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['日付','出勤','退勤','休憩','合計']); //配列を CSV の1行に変換して書き込む。

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['date'],
                    $r['in'],
                    $r['out'],
                    $r['break'],
                    $r['total'],
                ]);
            }

            fclose($out);
        }, $filename);
    }
}
