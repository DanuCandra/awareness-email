@php
    $authLayout = false;
    $navigationItems = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'tabler-smart-home'],
        ['label' => 'Daftar Email', 'route' => 'emails.index', 'icon' => 'tabler-mail'],
        ['label' => 'Simulasi', 'route' => 'campaigns.index', 'icon' => 'tabler-send', 'active' => 'campaigns.*'],
        ['label' => 'Template Email', 'route' => 'email-templates.index', 'icon' => 'tabler-file-description'],
        ['label' => 'Laporan', 'route' => 'reports.index', 'icon' => 'tabler-chart-bar'],
        ['label' => 'Pengaturan', 'route' => 'settings.index', 'icon' => 'tabler-settings'],
        ['label' => 'Kelola Admin', 'route' => 'admins.index', 'icon' => 'tabler-user-shield'],
    ];
@endphp
<!doctype html>
<html lang="id" class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr"
    data-skin="default" data-assets-path="{{ asset('assets') }}/"
    data-template="security-awareness" data-bs-theme="light" data-layout="app">
<head>
    @include('layouts.partials.head')
</head>
<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            @include('layouts.partials.sidebar')
            <div class="layout-page">
                @include('layouts.partials.navbar')
                <div class="content-wrapper">
                    <main class="container-xxl flex-grow-1 container-p-y" id="main-content">
                        @yield('content')
                    </main>
                    @include('layouts.partials.footer')
                    <div class="content-backdrop fade"></div>
                </div>
            </div>
        </div>
        <div class="layout-overlay layout-menu-toggle"></div>
        <div class="drag-target"></div>
    </div>
    @include('layouts.partials.scripts')
</body>
</html>
