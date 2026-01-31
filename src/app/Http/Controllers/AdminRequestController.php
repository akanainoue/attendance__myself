<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AttendanceApply;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AdminRequestController extends Controller
{
    public function requestIndex(Request $request)
    {
        // ① 表示する状態（pending / approved）
        $status = $request->get('status', 'pending');

        // ② 申請を取得
        $query = AttendanceApply::with([
            'requester',
            'attendance',
        ])->orderBy('created_at', 'desc');

        if ($status === 'approved') {
            $query->where('status', AttendanceApply::STATUS_APPROVED);
        } else {
            $query->where('status', AttendanceApply::STATUS_PENDING);
            $status = 'pending';
        }

        $items = $query->get();

        // ③ 画面用の配列を作る
        $rows = [];

        foreach ($items as $req) {

            // 対象日
            $targetDate = '-';
            if ($req->attendance && $req->attendance->work_date) {
                $targetDate = Carbon::parse(
                    $req->attendance->work_date
                )->format('Y/m/d');
            }

            $rows[] = [
                'id'      => $req->id,
                'status'  => $req->status === AttendanceApply::STATUS_APPROVED
                                ? '承認済み'
                                : '承認待ち',
                'name'    => $req->requester
                                ? $req->requester->name
                                : '',
                'target'  => $targetDate,
                'reason'  => $req->reason ?? '',
                'applied' => $req->created_at
                                ? $req->created_at->format('Y/m/d')
                                : '',
            ];
        }

        return view('admin.attendance.requests', [
            'rows'   => $rows,
            'status' => $status,
        ]);
    }

    public function showRequest($id)
    {
        // ① 申請を取得（申請者＋勤怠＋休憩）
        $request = AttendanceApply::with([
            'requester',
            'attendance.breaks',
        ])->findOrFail($id);

        $attendance = $request->attendance;
        $payload = (array) ($request->payload ?? []);

        /*
        |--------------------------------------------------------------------------
        | 基本情報
        |--------------------------------------------------------------------------
        */

        // 名前（申請者）
        $name = $request->requester->name ?? '—';

        // 日付（勤怠 → なければ申請日）
        $date = $attendance
            ? Carbon::parse($attendance->work_date)->format('Y年n月j日')
            : Carbon::parse($request->created_at)->format('Y年n月j日');

        /*
        |--------------------------------------------------------------------------
        | 出勤・退勤（payload 優先 → DB）
        |--------------------------------------------------------------------------
        */

        $clockIn = null;
        $clockOut = null;

        if (!empty($payload['clock_in_at'])) {
            $clockIn = Carbon::parse($payload['clock_in_at'])->format('H:i');
        } elseif ($attendance && $attendance->clock_in_at) {
            $clockIn = $attendance->clock_in_at->format('H:i');
        }

        if (!empty($payload['clock_out_at'])) {
            $clockOut = Carbon::parse($payload['clock_out_at'])->format('H:i');
        } elseif ($attendance && $attendance->clock_out_at) {
            $clockOut = $attendance->clock_out_at->format('H:i');
        }

        /*
        |--------------------------------------------------------------------------
        | 休憩（payload → DB）
        |--------------------------------------------------------------------------
        */

        $breaks = [];

        if (!empty($payload['breaks'])) {
            foreach ($payload['breaks'] as $b) {
                $breaks[] = [
                    'start_at' => !empty($b['start_at'])
                        ? Carbon::parse($b['start_at'])->format('H:i')
                        : null,

                    'end_at' => !empty($b['end_at'])
                        ? Carbon::parse($b['end_at'])->format('H:i')
                        : null,
                ];
            }
        } elseif ($attendance) {
            foreach ($attendance->breaks as $b) {
                $breaks[] = [
                    'start_at' => $b->start_at
                        ? $b->start_at->format('H:i')
                        : null,

                    'end_at' => $b->end_at
                        ? $b->end_at->format('H:i')
                        : null,
                ];
            }
        }

        // 必ず2本分用意
        while (count($breaks) < 2) {
            $breaks[] = ['start_at' => null, 'end_at' => null];
        }

        /*
        |--------------------------------------------------------------------------
        | View
        |--------------------------------------------------------------------------
        */
        return view('admin.attendance.request_confirm', [
            'requestId' => $request->id,
            'approved'  => $request->status === AttendanceApply::STATUS_APPROVED,
            'name'      => $name,
            'date'      => $date,
            'in'        => $clockIn ?? '—',
            'out'       => $clockOut ?? '—',
            'breaks'    => $breaks,
            'reason'    => $request->reason ?? '',
        ]);

    }

    public function accept($id)
    {
        $request = AttendanceApply::with('attendance.breaks')
        ->findOrFail($id);

        if ($request->status !== AttendanceApply::STATUS_PENDING) {
            abort(422, 'すでに処理済みです');
        }

        DB::transaction(function () use ($request) {

            $attendance = $request->attendance;
            $payload = $request->payload ?? [];

            // 出勤・退勤
            if (!empty($payload['clock_in_at'])) {
                $attendance->clock_in_at = Carbon::parse($payload['clock_in_at']);
            }

            if (!empty($payload['clock_out_at'])) {
                $attendance->clock_out_at = Carbon::parse($payload['clock_out_at']);
            }

            $attendance->save();

            // 休憩は必ず作り直す
            $attendance->breaks()->delete();

            if (!empty($payload['breaks'])) {
                foreach ($payload['breaks'] as $break) {
                    if (!empty($break['start_at']) && !empty($break['end_at'])) {
                        $attendance->breaks()->create([
                            'start_at' => Carbon::parse($break['start_at']),
                            'end_at'   => Carbon::parse($break['end_at']),
                        ]);
                    }
                }
            }

            // 申請を承認済みに
            $request->update([
                'status'      => AttendanceApply::STATUS_APPROVED,
                'reviewed_by' => Auth::guard('admin')->id(),
                'reviewed_at' => now(),
            ]);
        });

        //「oneアクションで2回以上 save / delete / create (CRUD操作）するなら transaction」


        return redirect('/admin/stamp_correction_request/list?status=approved')
            ->with('status', '申請を承認しました');
    }
}
