@extends('layouts.app')
@section('css')
<link rel="stylesheet" href="{{asset('css/admin-login.css')}}">
@endsection

@section('content')
<div class="content">
    <form action="/admin/login" class="login-form" method="post">
    @csrf
        <h2 class="form__ttl">
            管理者ログイン
        </h2>
        <div class="form-group">
            <label class="input__label">
                メールアドレス
                <input class="form__input" type="email" name="email">
            </label>
            <div class="error-message">
                @error('email')
                    {{$message}}
                @enderror
            </div>
        </div>
        <div class="form-group">
            <label>
                パスワード
                <input class="form__input" type="password" name="password">
            </label>
            <div class="error-message">
                @error('password')
                    {{$message}}
                @enderror
            </div>
        </div>
        <button class="submit-btn" type="submit">管理者ログインする</button>

    </form>
</div>
@endsection