@extends('layouts.app')
@section('css')
<link rel="stylesheet" href="{{asset('css/register.css')}}">
@endsection

@section('content')
<div class="content">
    <form action="/register" class="register-form" method="post">
    @csrf
        <h2 class="form__ttl">
            会員登録
        </h2>
        <div class="form-group">
            <label>
                名前
                <input class="form__input" type="text" name="name">
            </label>
            <div class="error-message">
                @error('name')
                    {{$message}}
                @enderror
            </div>
        </div>
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
        <div class="form-group">
            <label>
                パスワード
                <input class="form__input" type="password" name="password_confirmation">
            </label>
            <div class="error-message">
                @error('password_confirmation')
                    {{$message}}
                @enderror
            </div>
        </div>
        <button class="submit-btn" type="submit">会員登録する</button>
        <a href="/register" class="login-link">ログインはこちら</a>

    </form>
</div>
@endsection