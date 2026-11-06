<nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar" aria-label="Navigasi workspace">
    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
        <a class="nav-item nav-link px-0 me-xl-6" href="#" aria-label="Buka menu navigasi">
            <i class="icon-base ti tabler-menu-2 icon-md" aria-hidden="true"></i>
        </a>
    </div>
    <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
        <div class="navbar-nav align-items-center me-auto">
            <div class="nav-item d-flex align-items-center" id="autocomplete"></div>
        </div>
        <ul class="navbar-nav flex-row align-items-center ms-md-auto">
            <li class="nav-item me-3 d-none d-sm-block"><span class="badge bg-label-primary">{{ request()->routeIs('emails.*', 'email-templates.*', 'admins.*', 'settings.*', 'campaigns.*', 'reports.*') ? 'Workspace' : 'Pratinjau' }}</span></li>
            <li class="nav-item dropdown me-2 me-xl-0">
                <a class="nav-link dropdown-toggle hide-arrow" id="nav-theme" href="#" data-bs-toggle="dropdown" aria-label="Pilih tema tampilan" aria-expanded="false">
                    <i class="icon-base ti tabler-sun icon-md theme-icon-active" aria-hidden="true"></i>
                    <span class="d-none ms-2" id="nav-theme-text">Tema tampilan</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="nav-theme-text">
                    <li><button type="button" class="dropdown-item align-items-center active" data-bs-theme-value="light" aria-pressed="true"><span><i class="icon-base ti tabler-sun icon-md me-3" data-icon="sun" aria-hidden="true"></i>Terang</span></button></li>
                    <li><button type="button" class="dropdown-item align-items-center" data-bs-theme-value="dark" aria-pressed="false"><span><i class="icon-base ti tabler-moon-stars icon-md me-3" data-icon="moon-stars" aria-hidden="true"></i>Gelap</span></button></li>
                    <li><button type="button" class="dropdown-item align-items-center" data-bs-theme-value="system" aria-pressed="false"><span><i class="icon-base ti tabler-device-desktop-analytics icon-md me-3" data-icon="device-desktop-analytics" aria-hidden="true"></i>Sistem</span></button></li>
                </ul>
            </li>
            <li class="nav-item me-3">
                <button type="button" class="nav-link border-0 bg-transparent" data-open-customizer aria-label="Buka Template Customizer" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Template Customizer">
                    <i class="icon-base ti tabler-palette icon-md" aria-hidden="true"></i>
                </button>
            </li>
            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="#" data-bs-toggle="dropdown" aria-label="Menu Administrator" aria-expanded="false">
                    <div class="avatar avatar-online"><img src="{{ asset('assets/img/avatars/1.png') }}" alt="Avatar Administrator" class="rounded-circle"></div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-3 py-2">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3"><div class="avatar avatar-online"><img src="{{ asset('assets/img/avatars/1.png') }}" alt="" class="w-px-40 h-auto rounded-circle"></div></div>
                            <div class="flex-grow-1 overflow-hidden"><h6 class="mb-0 text-break" data-admin-session-name>{{ auth()->user()->name }}</h6><small class="text-body-secondary text-break" data-admin-session-username>{{ '@'.auth()->user()->username }}</small></div>
                        </div>
                    </li>
                    <li><div class="dropdown-divider my-1 mx-n2"></div></li>
                    <li data-admin-account><a class="dropdown-item" href="{{ route('admins.index', ['edit' => 'me']) }}"><i class="icon-base ti tabler-user icon-md me-3" aria-hidden="true"></i>Akun saya</a></li>
                    <li><a class="dropdown-item" href="{{ route('admins.index') }}"><i class="icon-base ti tabler-user-shield icon-md me-3" aria-hidden="true"></i>Kelola admin</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.index') }}"><i class="icon-base ti tabler-settings icon-md me-3" aria-hidden="true"></i>Pengaturan</a></li>
                    <li><button type="button" class="dropdown-item" data-open-customizer><i class="icon-base ti tabler-palette icon-md me-3" aria-hidden="true"></i>Atur tampilan</button></li>
                    <li><div class="dropdown-divider my-1 mx-n2"></div></li>
<li><form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="dropdown-item text-danger"><i class="icon-base ti tabler-logout icon-md me-3" aria-hidden="true"></i>Keluar</button></form></li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
