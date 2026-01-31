@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/punch.css') }}" />
@endsection

@section('nav')
<nav class="nav">
    <a href="{{ url('/attendance') }}">勤怠</a>
    {{-- 退勤済みだけラベルを「今月の出勤一覧」に変更（リンク先は同じでOK） --}}
    <a href="{{ url('/attendance/list') }}">
        {{ $status === 'done' ? '今月の出勤一覧' : '勤怠一覧' }}
    </a>
    <a href="{{ url('/stamp_correction_request/list') }}">申請</a>
    <form method="POST" action="{{ url('/logout') }}" class="logout-form">
        @csrf
        <button type="submit">ログアウト</button>
    </form>
</nav>
@endsection

@section('content')

@php
    // 状態が未定義のときの初期値
    if (!isset($status)) {
        $status = 'off';
    }

    // 状態ごとの表示テキスト
    if ($status === 'off') {
        $chipText = '勤務外';
    } elseif ($status === 'working') {
        $chipText = '出勤中';
    } elseif ($status === 'breaking') {
        $chipText = '休憩中';
    } elseif ($status === 'done') {
        $chipText = '退勤済';
    } else {
        $chipText = '勤務外';
    }
@endphp

<div class="container center-stack">
    <span class="chip">{{ $chipText }}</span>
    <h1 class="page-date">{{ $date }}</h1>
    <div class="big-clock">{{ $time }}</div>
    {{-- メッセージ表示 --}}
    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif
    {{-- エラー表示 --}}
    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
    {{-- 操作ボタン --}}
    <div class="actions">
        @if ($status === 'off')
            <form method="POST" action="/attendance/clock-in">
                @csrf
                <button type="submit" class="btn-primary">出 勤</button>
            </form>
        @elseif ($status === 'working')
            <form method="POST" action="/attendance/clock-out">
                @csrf
                <button type="submit" class="btn-primary">退 勤</button>
            </form>
            <form method="POST" action="/attendance/break-start">
                @csrf
                <button type="submit" class="btn-ghost">休憩入</button>
            </form>
        @elseif ($status === 'breaking')
            <form method="POST" action="/attendance/break-end">
                @csrf
                <button type="submit" class="btn-ghost">休憩戻</button>
            </form>
        @elseif ($status === 'done')
            <p class="done-message">お疲れ様でした。</p>
        @endif
    </div>
</div>
@endsection
