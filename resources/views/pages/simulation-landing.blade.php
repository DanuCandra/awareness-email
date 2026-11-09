@php
    $isEducation = $landing['type'] === 'education';
    $tips = array_values(array_filter(array_map('trim', explode("\n", $landing['tips'] ?? ''))));
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#1768d6">
    <title>{{ $landing['title'] }} | {{ $identity['companyName'] }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/awareness-landing.css') }}">
</head>
<body class="awareness-landing">
    <main class="awareness-card" aria-labelledby="landing-title">
        <header class="card-brand">
            @if ($identity['logo'])
                <img class="company-logo" src="{{ $identity['logo'] }}" alt="Logo {{ $identity['companyName'] }}" width="36" height="36">
            @else
                <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6l-8-3Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg></span>
            @endif
            <div><span class="company-name">{{ $identity['companyName'] }}</span><span class="brand-label">SECURITY AWARENESS</span></div>
        </header>

        <div class="card-visual" aria-hidden="true">
            <span class="visual-orbit"></span>
            <svg viewBox="0 0 230 150" fill="none">
                <circle cx="114" cy="75" r="65" fill="#edf5ff"/>
                <circle cx="114" cy="75" r="49" stroke="#dceafd" stroke-width="1"/>
                <g transform="rotate(-10 105 78)">
                    <rect x="33" y="41" width="135" height="85" rx="13" fill="#d8e7fb" opacity=".55"/>
                    <rect x="33" y="34" width="135" height="85" rx="13" fill="#fff" stroke="#c9dcf6" stroke-width="1.5"/>
                    <path d="m43 47 57 40a9 9 0 0 0 10 0l48-35" stroke="#85b3ed" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="m43 107 37-29m77 29-36-27" stroke="#dce9fa" stroke-width="2" stroke-linecap="round"/>
                </g>
                <g class="illustration-shield">
                    <path d="m164 57-37 14v28c0 24 37 45 37 45s37-21 37-45V71l-37-14Z" fill="#d6e5fa"/>
                    <path d="m164 51-37 14v28c0 24 37 45 37 45s37-21 37-45V65l-37-14Z" fill="#fff" stroke="#fff" stroke-width="5" stroke-linejoin="round"/>
                    <path d="m164 55-34 13v25c0 20 34 40 34 40s34-20 34-40V68l-34-13Z" fill="#1768d6"/>
                    <path d="m149 91 10 10 21-25" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
                </g>
                <path d="M184 24v10m-5-5h10M33 97v8m-4-4h8" stroke="#72a9ed" stroke-width="2" stroke-linecap="round"/>
                <circle cx="60" cy="23" r="3" fill="#b2d1f8"/>
                <circle cx="208" cy="106" r="3" fill="#b2d1f8"/>
            </svg>
        </div>

        <section class="card-message">
            <h1 id="landing-title">{{ $landing['title'] }}</h1>
            <p>{{ $landing['message'] }}</p>
        </section>

        @if ($isEducation && count($tips))
            <section class="card-guidance" aria-labelledby="guidance-title">
                <h2 id="guidance-title">Kenali sebelum mengeklik</h2>
                <ol class="education-list">
                    @foreach ($tips as $tip)
                        <li><span class="tip-number" aria-hidden="true">{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span><p>{{ $tip }}</p></li>
                    @endforeach
                </ol>
            </section>
        @else
            <div class="confirmation-note"><span aria-hidden="true">✓</span>Tautan berhasil dibuka. Anda dapat menutup halaman ini.</div>
        @endif

        @if ($identity['contactEmail'])
            <aside class="card-contact" aria-label="Kontak tim IT"><span class="contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 13v-1a8 8 0 0 1 16 0v1 M4 12H3v6h4v-6H4Zm16 0h1v6h-4v-6h3ZM20 18v1a2 2 0 0 1-2 2h-4"/></svg></span><div class="contact-copy"><strong>Butuh bantuan? Hubungi tim IT.</strong><span>{{ $identity['contactEmail'] }}</span></div><a class="contact-button" href="mailto:{{ $identity['contactEmail'] }}">Hubungi IT<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a></aside>
        @endif

        <footer class="card-footer"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6l-8-3Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg>{{ $isEducation ? 'Keamanan informasi dimulai dari kita.' : 'Terima kasih telah berpartisipasi.' }}</footer>
    </main>
</body>
</html>
