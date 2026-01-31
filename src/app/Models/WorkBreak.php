<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkBreak extends Model
{
    use HasFactory;

    /**
     * 一括代入を許可するカラム
     */
    protected $fillable = [
        'attendance_id',
        'start_at',
        'end_at',
    ];

    /**
     * 日時として扱うカラム
     */
    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
    ];

    /* ======================
    | リレーション
     ====================== */

    /**
     * この休憩が属する勤怠
     */
    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    /* ======================
    | 状態判定（初心者向け）
     ====================== */

    /**
     * 休憩中かどうか
     */
    public function isOpen()
    {
        return $this->end_at === null;
    }

    /**
     * 休憩が完了しているか
     */
    public function isClosed()
    {
        return $this->end_at !== null;
    }
}
