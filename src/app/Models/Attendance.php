<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    /**
     * 一括代入を許可するカラム
     */
    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in_at',
        'clock_out_at',
    ];

    /**
     * 日付・時刻として扱うカラム；casts:DBの値を、PHPで使いやすい形に自動変換する
     */
    protected $casts = [
        'work_date'    => 'date',
        'clock_in_at'  => 'datetime',  //PHPではCarbon オブジェクトとして使える
        'clock_out_at' => 'datetime',  //$data->clock_out_atみたいに
    ];

    /* ======================
    | リレーション
     ====================== */

    /**
     * ユーザー
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 休憩一覧
     */
    public function breaks()
    {
        return $this->hasMany(WorkBreak::class);
    }

    public function currentBreak()
    {
        return $this->breaks()
            ->latest('start_at')
            ->first();
    }

    /**
     * 打刻修正申請一覧
     */
    public function requests()
    {
        return $this->hasMany(AttendanceApply::class);
    }

    /**
     * 最新の打刻修正申請
     */
    public function request()
    {
        return $this->hasOne(AttendanceApply::class)->latest();
    }

    /* ======================
    | 状態判定
     ====================== */

    /**
     * 現在の勤務状態を返す
     *
     * off      : 出勤前
     * working  : 出勤中
     * breaking : 休憩中
     * done     : 退勤済
     */
    public function status()
    {
        // 出勤していない
        if ($this->clock_in_at === null) {
            return 'off';
        }

        // 退勤している
        if ($this->clock_out_at !== null) {
            return 'done';
        }

        // 休憩中かどうかを調べる
        $onBreak = $this->breaks()
            ->whereNull('end_at')
            ->exists();

        if ($onBreak) {
            return 'breaking';
        }

        return 'working';
    }

    /* ======================
    | 勤務時間計算
     ====================== */

    /**
     * 休憩の合計秒数
     */
    public function getBreakSeconds()
    {
        $seconds = 0;

        foreach ($this->breaks as $break) {
            if ($break->end_at !== null) {
                $seconds += $break->end_at->diffInSeconds($break->start_at);
            }
        }

        return $seconds;  //すべての休憩の合計秒数
    }
    // diffInSeconds の正体:2つの時刻の差を“絶対値”で返す
    // end → start にしてるのは”終了時刻 - 開始時刻”が見やすいから

    /**
     * 実働の合計秒数
     */
    public function getWorkSeconds()
    {
        // 出勤または退勤が未確定なら計算しない
        if ($this->clock_in_at === null || $this->clock_out_at === null) {
            return null;
        }

        $total = $this->clock_out_at->diffInSeconds($this->clock_in_at);
        $work  = $total - $this->getBreakSeconds();

        return max(0, $work);
    }

    /* ======================
    | 表示用
     ====================== */
    // gmdate('G:i', 秒)  秒 → 時:分
    // = Greenwich Mean date

    /**
     * 休憩時間（G:i 形式）
     */
    public function breakTime()
    {
        return gmdate('G:i', $this->getBreakSeconds());
    }

    /**
     * 実働時間（G:i 形式）
     */
    public function workTime()
    {
        $seconds = $this->getWorkSeconds();

        if ($seconds === null) {
            return null;
        }

        return gmdate('G:i', $seconds);
    }

    /* ======================
    | スコープ
     ====================== */

    /**
     * 日付指定
     */
    public function scopeForDate($query, $date)
    {
        $d = Carbon::parse($date)->toDateString();  //$date を Carbon に変換
        // いろんな形式の日付を受け取ってもYYYY-MM-DD に正規化する

        return $query->whereDate('work_date', $d); //日付一致検索
    }
    //使い方 Attendance::forDate(...)

    /**
     * 月指定（YYYY-MM）
     */
    public function scopeForMonth($query, $ym)
    {
        $start = Carbon::parse($ym . '-01')->startOfMonth();
        // もし $ym が'2024-03'なら'2024-03-01'にして Carbon に渡しています

        $end   = Carbon::parse($ym . '-01')->endOfMonth();

        return $query->whereBetween('work_date', [
            $start->toDateString(),
            $end->toDateString(),
        ]);
    }
}
