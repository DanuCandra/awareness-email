@extends('layouts.app')
@include('pages.partials.compose-assets', ['table' => false, 'editor' => false, 'wizard' => false, 'pageScript' => 'awareness-settings.js'])

@php
    $settingsSections = [
        'identity' => ['Identitas perusahaan', 'tabler-building', 'Identitas pada pratinjau halaman edukasi karyawan.', [
            ['companyName', 'Nama perusahaan', 'text', 100, true], ['contactEmail', 'Email kontak IT', 'email', 254, false],
        ]],
        'smtp' => ['SMTP & pengirim', 'tabler-mail-cog', 'Mail server eksternal dan identitas pengirim simulasi.', [
            ['host', 'Host SMTP', 'text', 253, true, 'Hostname tanpa http:// atau https://.'], ['port', 'Port', 'text', 5, true],
            ['encryption', 'Enkripsi', 'select', 0, true, '', ['starttls' => 'STARTTLS · Umumnya port 587', 'tls' => 'TLS langsung · Umumnya port 465']],
            ['username', 'Username SMTP', 'text', 254, true, 'Akun login SMTP sesuai penyedia mail server.'],
            ['fromName', 'Nama pengirim default', 'text', 80, true], ['fromEmail', 'Email pengirim (From)', 'email', 254, true, 'Alamat yang diizinkan penyedia SMTP. Bisa berbeda dari username.'],
            ['replyTo', 'Alamat balasan (Reply-To)', 'email', 254, false, 'Opsional. Kosongkan untuk mengikuti email pengirim.'],
            ['sendIntervalSeconds', 'Jeda antar email (detik)', 'number', 16, true, 'Isi 0 untuk tanpa jeda. Contoh: 5 memberi jarak 5 detik antar proses pengiriman email.'],
        ]],
        'landing' => ['Tautan & edukasi', 'tabler-link', 'Tampilan yang dilihat karyawan setelah membuka tautan.', [
            ['baseUrl', 'Alamat dasar tautan simulasi', 'url', 250, true],
            ['type', 'Jenis halaman setelah diklik', 'select', 0, true, '', ['education' => 'Edukasi · Penjelasan simulasi dan tips keamanan', 'confirmation' => 'Konfirmasi · Pesan singkat setelah akses tautan']],
            ['title', 'Judul halaman', 'text', 120, true], ['message', 'Pesan untuk penerima', 'textarea', 1200, true], ['tips', 'Tips keamanan', 'textarea', 1500, false, 'Satu tips pada setiap baris.'],
        ]],
        'reports' => ['Preferensi laporan', 'tabler-chart-bar', 'Pilihan awal saat membuka halaman laporan.', [
            ['pageLength', 'Jumlah baris aktivitas', 'select', 0, true, '', [10 => '10 baris', 25 => '25 baris', 50 => '50 baris']],
            ['period', 'Periode laporan default', 'select', 0, true, '', ['all' => 'Seluruh periode', '30' => '30 hari terakhir', '90' => '90 hari terakhir']],
        ]],
        'audit' => ['Data audit', 'tabler-database', 'Preferensi masa penyimpanan hasil simulasi.', [
            ['retentionMonths', 'Masa penyimpanan laporan', 'select', 0, true, '', [3 => '3 bulan', 6 => '6 bulan', 12 => '12 bulan']],
        ]],
    ];
@endphp

@section('content')
    <div id="settingsWorkspace">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-6"><div><h4 class="mb-1">Pengaturan</h4><p class="mb-0 text-body-secondary">Atur identitas perusahaan, pengirim email, dan preferensi workspace.</p></div><button type="button" class="btn btn-label-secondary" data-open-customizer><i class="icon-base ti tabler-palette icon-sm me-2" aria-hidden="true"></i>Atur tampilan</button></div>
        <div class="alert alert-primary d-flex align-items-start gap-3 mb-6" role="note"><i class="icon-base ti tabler-info-circle icon-22px mt-1" aria-hidden="true"></i><div><span class="fw-medium">Mode pratinjau.</span> Pengaturan disimpan sementara di tab ini. Gunakan data SMTP contoh; password tidak disimpan dan pengujian tidak menghubungi mail server.</div></div>
        <div class="row g-6">
            <div class="col-lg-3"><div class="card mb-6"><div class="card-body"><h6 class="text-body-secondary small text-uppercase mb-4">Workspace</h6><div class="nav nav-pills flex-column gap-2" role="tablist" aria-orientation="vertical">
                @foreach ($settingsSections as $key => [$label, $icon])
                    <button type="button" class="nav-link d-flex align-items-center text-start {{ $loop->first ? 'active' : '' }}" id="settings-{{ $key }}-tab" data-bs-toggle="tab" data-bs-target="#settings-{{ $key }}-panel" role="tab" aria-controls="settings-{{ $key }}-panel" aria-selected="{{ $loop->first ? 'true' : 'false' }}"><i class="icon-base ti {{ $icon }} icon-sm me-3" aria-hidden="true"></i>{{ $label }}</button>
                @endforeach
            </div></div></div><div class="card"><div class="card-body"><div class="avatar mb-4"><span class="avatar-initial rounded bg-label-primary"><i class="icon-base ti tabler-send icon-26px" aria-hidden="true"></i></span></div><h6 class="mb-2">Pengirim simulasi</h6><p class="small text-body-secondary mb-2">Email mengikuti pengaturan SMTP yang tersimpan.</p><p class="fw-medium small text-break mb-4" id="settingsSavedSender"></p><a href="{{ route('campaigns.create') }}" class="btn btn-sm btn-label-primary w-100">Buat simulasi<i class="icon-base ti tabler-arrow-right icon-sm ms-2" aria-hidden="true"></i></a><p class="small text-body-secondary mt-4 mb-0" id="settingsUpdated" role="status"></p></div></div></div>
            <div class="col-lg-9"><div class="tab-content bg-transparent shadow-none p-0">
                @foreach ($settingsSections as $section => [$title, $icon, $description, $fields])
                    <section class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="settings-{{ $section }}-panel" role="tabpanel" aria-labelledby="settings-{{ $section }}-tab"><form class="card settings-form" data-section="{{ $section }}" novalidate>
                        <div class="card-header border-bottom"><div class="d-flex flex-wrap align-items-start justify-content-between gap-3"><div><h5 class="card-title mb-1">{{ $title }}</h5><p class="card-subtitle">{{ $description }}</p></div>@if ($section === 'smtp')<span class="badge bg-label-secondary">Belum diuji ke server</span>@endif</div></div>
                        <div class="card-body pt-6">
                            @if ($section === 'identity')
                                <div class="d-flex flex-wrap align-items-center gap-5 mb-6"><div class="avatar avatar-xl"><span class="avatar-initial rounded bg-label-primary" id="settingsLogoFallback"><i class="icon-base ti tabler-building icon-40px" aria-hidden="true"></i></span><img id="settingsLogoImage" class="rounded d-none object-fit-contain" alt="Pratinjau logo perusahaan"></div><div><label class="btn btn-label-primary mb-3" for="settingsLogoFile"><i class="icon-base ti tabler-upload icon-sm me-2" aria-hidden="true"></i>Pilih logo</label><input type="file" class="visually-hidden" id="settingsLogoFile" accept="image/png,image/jpeg,image/webp"><button type="button" class="btn btn-label-secondary mb-3 ms-2" id="removeSettingsLogo">Hapus logo</button><p class="small text-body-secondary mb-0">PNG, JPG, atau WebP. Maksimal 500 KB.</p><div class="small text-danger mt-2" data-setting-error="identity.logo" role="alert"></div></div></div>
                            @elseif ($section === 'smtp')
                                <div class="alert alert-secondary mb-6">Email pengirim di simulasi otomatis mengikuti <strong>Email pengirim (From)</strong> di bawah. Nama pengirim dapat disesuaikan per simulasi.</div>
                            @elseif ($section === 'reports')
                                <div class="mb-5"><label class="form-label" for="settingsTimezone">Zona waktu</label><input class="form-control" id="settingsTimezone" value="Asia/Jakarta · WIB (UTC+07:00)" readonly><div class="form-text">Jadwal simulasi dan seluruh waktu laporan menggunakan WIB.</div></div>
                            @endif
                            <div class="row g-5">
                                @foreach ($fields as $field)
                                    @php([$key, $label, $type, $max, $required] = $field)
                                    <div class="{{ in_array($section, ['landing', 'audit']) ? 'col-12' : 'col-md-6' }}" @if ($section === 'landing' && $key === 'tips') id="settingsEducationTips" @endif>
                                        <label class="form-label" for="setting-{{ $section }}-{{ $key }}">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
                                        @if ($type === 'select')
                                            <select class="form-select" id="setting-{{ $section }}-{{ $key }}" data-setting="{{ $section }}.{{ $key }}">@foreach ($field[6] as $value => $option)<option value="{{ $value }}">{{ $option }}</option>@endforeach</select>
                                        @elseif ($type === 'textarea')
                                            <textarea class="form-control" id="setting-{{ $section }}-{{ $key }}" data-setting="{{ $section }}.{{ $key }}" rows="4" maxlength="{{ $max }}"></textarea>
                                        @else
                                            <input type="{{ $type }}" class="form-control" id="setting-{{ $section }}-{{ $key }}" data-setting="{{ $section }}.{{ $key }}" maxlength="{{ $max }}" @if ($key === 'port') inputmode="numeric" @endif @if ($type === 'number') min="0" step="1" inputmode="numeric" @endif>
                                        @endif
                                        <div class="invalid-feedback" data-setting-error="{{ $section }}.{{ $key }}"></div>
                                        @if ($field[5] ?? '')<div class="form-text">{{ $field[5] }}</div>@endif
                                    </div>
                                @endforeach
                            </div>
                            @if ($section === 'smtp')
                                <div class="mt-5"><label class="form-label" for="settingsSmtpPassword">Password SMTP</label><div class="input-group input-group-merge"><input type="password" class="form-control" id="settingsSmtpPassword" autocomplete="new-password" maxlength="200" placeholder="Password contoh — tidak disimpan"><button type="button" class="input-group-text" id="toggleSettingsPassword" aria-label="Tampilkan password" aria-pressed="false"><i class="icon-base ti tabler-eye" aria-hidden="true"></i></button></div><div class="form-text">Dikosongkan kembali setelah menyimpan. Penyimpanan kredensial tersedia saat backend dibuat.</div></div><div class="bg-lighter rounded p-4 mt-6"><p class="small text-body-secondary mb-2">Pratinjau pengirim pada simulasi baru</p><p class="fw-medium text-break mb-0" id="settingsSenderPreview"></p><p class="small text-body-secondary mt-3 mb-0" id="settingsIntervalPreview" role="status" aria-live="polite"></p></div><div class="d-flex flex-wrap gap-3 mt-5"><button type="button" class="btn btn-label-secondary" id="testSettingsConnection"><i class="icon-base ti tabler-plug-connected icon-sm me-2" aria-hidden="true"></i>Uji koneksi · Pratinjau</button><button type="button" class="btn btn-label-primary" id="testSettingsEmail"><i class="icon-base ti tabler-mail-forward icon-sm me-2" aria-hidden="true"></i>Email percobaan · Pratinjau</button></div>
                            @elseif ($section === 'landing')
                                <div class="bg-lighter rounded p-4 mt-5"><p class="small text-body-secondary mb-2">Contoh format tautan</p><p class="small text-break mb-0" id="settingsTrackingExample"></p></div><div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mt-6"><p class="small text-body-secondary mb-0">Tautan contoh belum mengumpulkan data akses.</p><button type="button" class="btn btn-label-primary" id="previewSettingsLanding"><i class="icon-base ti tabler-eye icon-sm me-2" aria-hidden="true"></i>Pratinjau halaman</button></div>
                            @elseif ($section === 'reports')
                                <div class="alert alert-secondary mt-6 mb-0"><h6 class="mb-2">Cara membaca hasil simulasi</h6><p class="small mb-2">Tingkat klik = penerima unik yang mengeklik pada setiap simulasi ÷ pengiriman berhasil × 100%.</p><p class="small mb-0">Klik ulang ditampilkan terpisah. Alamat email unik lintas simulasi dibedakan dari total penerima yang mengeklik.</p></div><a href="{{ route('reports.index') }}" class="btn btn-label-primary mt-5">Buka laporan<i class="icon-base ti tabler-arrow-right icon-sm ms-2" aria-hidden="true"></i></a>
                            @elseif ($section === 'audit')
                                <div class="bg-lighter rounded p-5 mt-6"><div class="d-flex align-items-start gap-3"><i class="icon-base ti tabler-database icon-26px text-primary" aria-hidden="true"></i><div><h6 class="mb-2">Cakupan data laporan</h6><p class="small text-body-secondary mb-0">Hasil pengiriman, email penerima, IP tercatat, waktu klik, browser, dan jenis perangkat.</p></div></div></div><div class="alert alert-secondary mt-5 mb-0" role="note">Pilihan ini disimpan sebagai preferensi. Penghapusan otomatis akan diterapkan saat backend dibuat.</div>
                            @endif
                        </div>@include('pages.partials.settings-footer')
                    </form></section>
                @endforeach
            </div></div>
        </div>
    </div>
    <div class="modal fade" id="settingsLandingModal" tabindex="-1" aria-labelledby="settingsLandingModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header border-bottom"><h5 class="modal-title" id="settingsLandingModalTitle">Pratinjau halaman setelah diklik</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div><div class="modal-body p-6"><div class="bg-lighter rounded p-5 p-sm-8" id="settingsLandingPreview"></div><p class="small text-body-secondary text-center mt-4 mb-0">Pratinjau tampilan · Tidak merekam aktivitas akses</p></div><div class="modal-footer border-top px-6 py-5"><button type="button" class="btn btn-label-secondary m-0" data-bs-dismiss="modal">Tutup</button></div></div></div></div>
    <div class="modal fade" id="settingsTestModal" tabindex="-1" aria-labelledby="settingsTestTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="settingsTestForm" novalidate><div class="modal-header border-bottom"><h5 class="modal-title" id="settingsTestTitle">Uji SMTP · Pratinjau</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div><div class="modal-body p-6"><div class="alert alert-secondary mb-5">Pengujian ini memeriksa kelengkapan format. Koneksi SMTP dan pengiriman email belum dijalankan.</div><dl class="mb-5"><dt class="small text-body-secondary fw-normal">Mail server</dt><dd class="text-break mt-1" id="settingsTestServer"></dd><dt class="small text-body-secondary fw-normal">Pengirim</dt><dd class="text-break mt-1 mb-0" id="settingsTestSender"></dd></dl><div id="settingsTestRecipientField"><label for="settingsTestRecipient" class="form-label">Email penerima uji</label><input type="email" class="form-control" id="settingsTestRecipient" maxlength="254" placeholder="it@perusahaan.example"><div class="invalid-feedback">Masukkan alamat email penerima yang valid.</div></div><div class="alert alert-primary mt-5 mb-0 d-none" id="settingsTestResult" role="status" aria-live="polite"></div></div><div class="modal-footer border-top flex-column flex-sm-row align-items-stretch justify-content-sm-between gap-3 px-6 py-5"><button type="button" class="btn btn-label-secondary m-0" data-bs-dismiss="modal">Tutup</button><button type="submit" class="btn btn-primary m-0" id="settingsTestSubmit">Periksa format</button></div></form></div></div></div>
    @include('pages.partials.compose-feedback')
@endsection
