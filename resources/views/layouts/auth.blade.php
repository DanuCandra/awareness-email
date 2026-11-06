@php($authLayout = true)
<!doctype html>
<html lang="id" dir="ltr" data-skin="default" data-assets-path="{{ asset('assets') }}/"
    data-template="security-awareness" data-bs-theme="light" data-layout="auth">
<head>
    @include('layouts.partials.head')
</head>
<body>
    @yield('content')
    @include('layouts.partials.scripts')
</body>
</html>
