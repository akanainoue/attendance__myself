@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-attendance-detail.css') }}">
@endsection

@section('nav')
<nav class="nav">
    <a href="/admin/attendance/list">勤怠一覧</a>
    <a href="/admin/staff/list">スタッフ一覧</a>
    <a href="/admin/stamp_correction_request/list">申請一覧</a>

    <form method="POST" action="/admin/logout" class="logout-form">
        @csrf
        <button type="submit">ログアウト</button>
    </form>
</nav>
@endsection

@section('content')
<main class="main">
<div class="container">

<h1 class="page-title">勤怠詳細</h1>

<form method="POST" action="/admin/attendance/{{ $attendance->id }}">
@csrf
@method('PATCH')

<div class="card detail-grid">

    {{-- 名前 --}}
    <div class="row">
        <div class="th">名前</div>
        <div class="td">{{ $attendance->user->name }}</div>
    </div>

    {{-- 日付 --}}
    <div class="row">
        <div class="th">日付</div>
        <div class="td">{{ $date }}</div>
    </div>

    {{-- 出勤・退勤 --}}
    <div class="row">
        <div class="th">出勤・退勤</div>
        <div class="td">
            <input type="time" name="clock_in_at"
                   value="{{ old('clock_in_at', $form['clock_in_at']) }}">
            〜
            <input type="time" name="clock_out_at"
                   value="{{ old('clock_out_at', $form['clock_out_at']) }}">
        </div>
    </div>

    {{-- 休憩1 --}}
    <div class="row">
        <div class="th">休憩</div>
        <div class="td">
            <input type="time" name="breaks[0][start_at]"
                   value="{{ old('breaks.0.start_at', $form['breaks'][0]['start_at']) }}">
            〜
            <input type="time" name="breaks[0][end_at]"
                   value="{{ old('breaks.0.end_at', $form['breaks'][0]['end_at']) }}">
        </div>
    </div>

    {{-- 休憩2 --}}
    <div class="row">
        <div class="th">休憩2</div>
        <div class="td">
            <input type="time" name="breaks[1][start_at]"
                   value="{{ old('breaks.1.start_at', $form['breaks'][1]['start_at']) }}">
            〜
            <input type="time" name="breaks[1][end_at]"
                   value="{{ old('breaks.1.end_at', $form['breaks'][1]['end_at']) }}">
        </div>
    </div>

    {{-- 備考 --}}
    <div class="row">
        <div class="th">備考</div>
        <div class="td">
            <textarea name="note" rows="3">{{ old('note', $form['reason']) }}</textarea>
        </div>
    </div>

</div>

<div class="actions">
    <button type="submit" class="btn-primary">修正</button>
</div>

</form>

</div>
</main>
@endsection
