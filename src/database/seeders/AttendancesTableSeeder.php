<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendancesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users  = User::all();
        $today  = today();
        $start  = $today->copy()->subDays(20); //20日前
        
        //lte() Carbon比較メソッド　$d <= $today
        foreach ($users as $user) {
            for ($d = $start->copy(); $d->lte($today); $d->addDay()) {
                //以下のifで勤怠を作らない日をスキップしている

                // たまに欠勤（25%）
                if (rand(1, 100) <= 25) {
                    continue; 
                }

                // たまに土日を休みにする（50%）
                if (in_array($d->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]) && rand(0, 1)) {
                    continue;
                }
                //in_array(6, [6,0]) → true   // 土曜
                //in_array(0, [6,0]) → true   // 日曜 
                //in_array(2, [6,0]) → false  // 火曜
                //土日なら半分の確率でスキップ

                DB::transaction(function () use ($user, $d) {
                    $workDate = $d->toDateString();

                    // 出勤時間（8:00〜10:30）
                    $inHour = rand(8, 10);
                    $minutes = [0, 15, 30, 45];
                    $inMin = $minutes[array_rand($minutes)]; //array_rand(...)は配列のキーを返す
                    $clockIn = Carbon::create($d->year, $d->month, $d->day, $inHour, $inMin);

                    // 退勤時間（出勤+8〜10時間）
                    $outHour = $inHour + 8 + rand(0, 2);
                    $outMin  = [0, 10, 20, 30, 40, 50][array_rand([0, 10, 20, 30, 40, 50])];
                    $clockOut = Carbon::create($d->year, $d->month, $d->day, $outHour, $outMin);

                    // 勤怠本体（同日の既存があれば更新）
                    $attendance = Attendance::updateOrCreate(
                        ['user_id' => $user->id, 'work_date' => $workDate],
                        ['clock_in_at' => $clockIn, 'clock_out_at' => $clockOut]
                    );

                    // 既存の休憩は作り直す
                    $attendance->breaks()->delete();

                    // 休憩 0〜2 回
                    $breakCount = rand(0, 2);
                    for ($i = 0; $i < $breakCount; $i++) {
                        // 出勤から2〜4時間後に休憩開始
                        $startAt = $clockIn->copy()
                            ->addHours(rand(2, 4))
                            ->addMinutes(rand(0, 50));
                        // もし休憩開始が退勤後になったら、強制的に「出勤＋2時間」に戻す
                        // if ($startAt->gte($clockOut)) {
                        //     $startAt = $clockIn->copy()->addHours(2);
                        // }

                        // 10〜60分の休憩
                        $endAt = $startAt->copy()->addMinutes(rand(10, 60));

                        // もし休憩終了が退勤後になったら、退勤直前にずらす
                        if ($endAt->gt($clockOut)) {
                            $endAt = $clockOut->copy()->subMinutes(rand(5, 20));
                        }

                        $attendance->breaks()->create([
                            'start_at' => $startAt,
                            'end_at'   => $endAt,
                        ]);
                    }
                });
            }
        }
    }
}
