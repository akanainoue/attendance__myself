<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceApply extends Model
{
    use HasFactory;

    /**
     * 一括代入を許可するカラム
     */
    protected $fillable = [
        'attendance_id',
        'requested_by',
        'status',
        'reason',
        'payload',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * 申請ステータス
     */
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    // const STATUS_REJECTED = 'rejected';

    /**
     * 型変換
     */
    protected $casts = [
        'payload'     => 'array',  //$req->payload['clock_in_at']みたいに使える
        'reviewed_at' => 'datetime',
    ];

    /* ======================
    | リレーション
     ====================== */

    /**
     * 対象の勤怠
     */
    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * 申請者（一般ユーザー）
     */
    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * 承認者（管理者）
     */
    public function reviewer()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    /* ======================
    | 状態判定（初心者向け）
     ====================== */

    /**
     * 申請中かどうか
     */
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;  //public constを参照
        //return $this->status === 'pending';と同じこと
    }
    //戻り値は 真偽値（boolean）
    //if ($attendanceRequest->status === 'pending') { normal
    //if ($attendanceRequest->isPending()) { better

    /**
     * 承認済みかどうか
     */
    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
