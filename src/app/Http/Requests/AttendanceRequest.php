<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'clock_in_at'  => 'required|date_format:H:i',
            'clock_out_at' => 'required|date_format:H:i',
            'breaks'       => 'nullable|array',
            'reason'       => 'required|string',
        ];
    }

    /**
     * エラーメッセージ
     */
    public function messages()
    {
        return [
            'clock_in_at.required'  => '出勤時間を入力してください',
            'clock_in_at.date_format'  => '出勤時間は 09:00 の形式で入力してください',
            'clock_out_at.required' => '退勤時間を入力してください',
            'clock_out_at.date_format' => '退勤時間は 18:00 の形式で入力してください',
            'reason.required' => '備考を入力してください',
        ];
    }

    /**
     * 複雑なチェック（時間の前後関係）
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            // 出勤・退勤を時刻に変換
            $clockIn  = $this->toCarbon($this->input('clock_in_at'));
            $clockOut = $this->toCarbon($this->input('clock_out_at'));

            // 出勤 > 退勤 はエラー
            if ($clockIn && $clockOut && $clockIn > $clockOut) {
                $validator->errors()->add(
                    'work_time',
                    '出勤時間と退勤時間の前後関係が正しくありません'
                );
            }

            // 休憩時間チェック
            $breaks = $this->input('breaks', []);

            foreach ($breaks as $break) {
                $start = $this->toCarbon($break['start_at'] ?? null);
                $end   = $this->toCarbon($break['end_at'] ?? null);

                // 開始と終了が両方ある場合
                if ($start && $end && $start > $end) {
                    $validator->errors()->add(
                        'break_time',
                        '休憩時間の前後関係が正しくありません'
                    );
                    break;
                }

                // 出勤前の休憩
                if ($clockIn && $start && $start < $clockIn) {
                    $validator->errors()->add(
                        'break_time',
                        '休憩時間が出勤時間より前になっています'
                    );
                    break;
                }

                // 退勤後の休憩
                if ($clockOut && $end && $end > $clockOut) {
                    $validator->errors()->add(
                        'break_time',
                        '休憩時間が退勤時間より後になっています'
                    );
                    break;
                }
            }
        });
    }

    /**
     * 時刻文字列を Carbon に変換
     */
    private function toCarbon($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}
