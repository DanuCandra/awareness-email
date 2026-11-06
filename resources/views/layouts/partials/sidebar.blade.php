<aside id="layout-menu" class="layout-menu menu-vertical menu">
    <div class="app-brand demo">
        <a href="{{ route('dashboard') }}" class="app-brand-link" aria-label="Security Awareness — Dashboard">
            @include('layouts.partials.brand')
        </a>
        <a href="#" class="layout-menu-toggle menu-link text-large ms-auto" aria-label="Lipat atau buka sidebar">
            <i class="icon-base ti menu-toggle-icon d-none d-xl-block" aria-hidden="true"></i>
            <i class="icon-base ti tabler-x d-block d-xl-none" aria-hidden="true"></i>
        </a>
    </div>
    <div class="menu-inner-shadow"></div>
    <ul class="menu-inner py-1">
        <li class="menu-header small"><span class="menu-header-text">Workspace</span></li>
        @foreach ($navigationItems as $item)
            @if ($item['route'] === 'settings.index')
                <li class="menu-header small"><span class="menu-header-text">Sistem</span></li>
            @endif
            <li @class(['menu-item', 'active' => request()->routeIs($item['active'] ?? $item['route'])])>
                <a href="{{ route($item['route']) }}" class="menu-link"
                    @if (request()->routeIs($item['active'] ?? $item['route'])) aria-current="page" @endif>
                    <i class="menu-icon icon-base ti {{ $item['icon'] }}" aria-hidden="true"></i>
                    <div>{{ $item['label'] }}</div>
                </a>
            </li>
        @endforeach
    </ul>
</aside>
<div class="menu-mobile-toggler d-xl-none rounded-1">
    <a href="#" class="layout-menu-toggle menu-link text-large text-bg-secondary p-2 rounded-1" aria-label="Buka menu navigasi">
        <i class="ti tabler-menu icon-base" aria-hidden="true"></i>
        <i class="ti tabler-chevron-right icon-base" aria-hidden="true"></i>
    </a>
</div>
