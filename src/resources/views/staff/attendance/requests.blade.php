@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/request-list.css') }}">
@endsection

@section('nav')
<nav class="nav">
    <a href="/attendance">勤怠</a>
    <a href="/attendance/list">勤怠一覧</a>
    <a class="active" href="/stamp_correction_request/list">申請</a>
    <form method="POST" action="/logout" class="logout-form">
        @csrf
        <button type="submit">ログアウト</button>
    </form>
</nav>
@endsection

@section('content')
<main class="req-main">
<div class="container">

<h1 class="page-title">申請一覧</h1>

{{-- タブ --}}
<div class="tabs">
    <a href="/stamp_correction_request/list?tab=pending" class="tab {{ $tab === 'pending' ? 'is-active' : '' }}">
        承認待ち
    </a>

    <a href="/stamp_correction_request/list?tab=approved" class="tab {{ $tab === 'approved' ? 'is-active' : '' }}">
        承認済み
    </a>
</div>

<div class="tabs-divider"></div>

{{-- 一覧テーブル --}}
<div class="sheet card">
<table class="req-table">
    <thead>
        <tr>
            <th>状態</th>
            <th>名前</th>
            <th>対象日</th>
            <th>申請理由</th>
            <th>申請日</th>
            <th>詳細</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
        <tr>
            <td>
                <span class="badge">{{ $row['status'] }}</span>
            </td>
            <td>{{ $row['name'] }}</td>
            <td class="mono">{{ $row['target'] }}</td>
            <td class="ellipsis" title="{{ $row['reason'] }}">
                {{ $row['reason'] }}
            </td>
            <td class="mono">{{ $row['applied'] }}</td>
            <td>
                <a class="link-detail"
                    href="/attendance/detail/{{ $row['id'] }}">
                    詳細
                </a>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
</div>

</div>
</main>
@endsection
