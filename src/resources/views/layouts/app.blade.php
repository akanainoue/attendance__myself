<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance</title>
    <link rel="stylesheet" href="{{asset('css/sanitize.css')}}">
    <link rel="stylesheet" href="{{asset('css/common.css')}}">
    @yield('css')
</head>
<body>
    <header>
        <div class="header-inner">
            <a href="/attendance" class="index-link">
                    <img class="logo-image" src="{{asset('items/logo.svg')}}" alt="logo">
            </a>
            @yield('nav')
        </div>
    </header>
    <main>
        @yield('content')
    </main>
</body>
</html>