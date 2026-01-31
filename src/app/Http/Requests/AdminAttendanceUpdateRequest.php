<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminAttendanceUpdateRequest extends FormRequest
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
            // 出勤・退勤（時:分 の形式）
            'clock_in_at'  => ['nullable', 'date_format:H:i'],
            'clock_out_at' => ['nullable', 'date_format:H:i'],

            // 休憩（配列）
            'breaks' => ['nullable', 'array'],

            // 休憩開始・終了
            'breaks.*.start_at' => ['nullable', 'date_format:H:i'],
            'breaks.*.end_at'   => ['nullable', 'date_format:H:i'],

            // 備考
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages()
    {
        return [
            'clock_in_at.date_format'  => '出勤時間は 09:00 の形式で入力してください',
            'clock_out_at.date_format' => '退勤時間は 18:00 の形式で入力してください',
            'breaks.*.start_at.date_format' => '休憩開始は 12:00 の形式で入力してください',
            'breaks.*.end_at.date_format'   => '休憩終了は 13:00 の形式で入力してください',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $in  = $this->input('clock_in_at');
            $out = $this->input('clock_out_at');
    
            if ($in && $out && $in > $out) {
                $validator->errors()->add(
                    'work_time',
                    '出勤時間は退勤時間より前にしてください'
                );
            }
        });
    }
}
