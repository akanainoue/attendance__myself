@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-detail.css') }}">
@endsection

@section('nav')
<nav class="nav">
    <a href="/attendance">勤怠</a>
    <a href="/attendance/list">勤怠一覧</a>
    <a href="/stamp_correction_request/list">申請</a>
    <form method="POST" action="/logout" class="logout-form">
        @csrf
        <button type="submit">ログアウト</button>
    </form>
</nav>
@endsection

@section('content')
<main class="detail-main">
<div class="container">

<h1 class="page-title">勤怠詳細</h1>

<form method="POST"
    action="/attendance/detail/{{ $attendance->id }}/notes"
    class="detail-card card {{ $form['is_locked'] ? 'form-locked' : '' }}">

@csrf

{{-- 名前 --}}
<div class="row">
    <div class="label">名前</div>
    <div class="value">{{ $attendance->user->name }}</div>
</div>

{{-- 日付 --}}
<div class="row">
    <div class="label">日付</div>
    <div class="value">{{ $date }}</div>
</div>

{{-- 出勤・退勤 --}}
<div class="row">
    <div class="label">出勤・退勤</div>
    <div class="value time-range">
        <input type="time" name="clock_in_at"
            value="{{ old('clock_in_at', $form['clock_in_at']) }}"
            {{ $form['is_locked'] ? 'disabled' : '' }}>

        <span>〜</span>

        <input type="time" name="clock_out_at"
            value="{{ old('clock_out_at', $form['clock_out_at']) }}"
            {{ $form['is_locked'] ? 'disabled' : '' }}>

        @if ($errors->has('work_time'))
            <p class="field-error">{{ $errors->first('work_time') }}</p>
        @endif
    </div>
</div>

{{-- 休憩1 --}}
<div class="row">
    <div class="label">休憩</div>
    <div class="value time-range">
        <input type="time" name="breaks[0][start_at]"
            value="{{ old('breaks.0.start_at', $form['breaks'][0]['start_at']) }}"
            {{ $form['is_locked'] ? 'disabled' : '' }}>

        <span>〜</span>

        <input type="time" name="breaks[0][end_at]"
            value="{{ old('breaks.0.end_at', $form['breaks'][0]['end_at']) }}"
            {{ $form['is_locked'] ? 'disabled' : '' }}>
    </div>
</div>

{{-- 休憩2 --}}
<div class="row">
    <div class="label">休憩2</div>
    <div class="value time-range">
        <input type="time" name="breaks[1][start_at]"
            value="{{ old('breaks.1.start_at', $form['breaks'][1]['start_at']) }}"
            {{ $form['is_locked'] ? 'disabled' : '' }}>

        <span>〜</span>

        <input type="time" name="breaks[1][end_at]"
            value="{{ old('breaks.1.end_at', $form['breaks'][1]['end_at']) }}"
            {{ $form['is_locked'] ? 'disabled' : '' }}>
    </div>
</div>

{{-- 備考 --}}
<div class="row">
    <div class="label">備考</div>
    <div class="value">
        <textarea name="reason" rows="3"
            {{ $form['is_locked'] ? 'disabled' : '' }}>{{ old('reason', $form['reason']) }}</textarea>
        @error('reason')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>
</div>

@if (!$form['is_locked'])
<div class="actions">
    <button type="submit" class="btn-primary">修正</button>
</div>
@endif

</form>

@if ($form['is_locked'])
<p class="pending-note">※ 承認待ちのため修正できません。</p>
@endif

@if (session('status'))
<p class="flash">{{ session('status') }}</p>
@endif

</div>
</main>
@endsection
