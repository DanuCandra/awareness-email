<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ $landing['title'] }}</title><link rel="stylesheet" href="{{ asset('assets/vendor/css/core.css') }}"></head>
<body class="bg-body-tertiary"><main class="container py-12"><div class="row justify-content-center"><div class="col-lg-7 col-xl-6"><article class="card"><div class="card-body p-6 p-sm-10">
<div class="text-center mb-6">@if ($identity['logo'])<img src="{{ $identity['logo'] }}" alt="Logo perusahaan" width="80" height="80" class="object-fit-contain mb-4">@endif<p class="text-body-secondary">{{ $identity['companyName'] }}</p><h3>{{ $landing['title'] }}</h3><p style="white-space:pre-line">{{ $landing['message'] }}</p></div>
@if ($landing['type'] === 'education')<div class="bg-lighter rounded p-5"><h5>Kenali sebelum mengeklik</h5><ul class="mb-0 ps-5">@foreach (explode("\n", $landing['tips']) as $tip)@if (trim($tip))<li class="mb-3">{{ $tip }}</li>@endif @endforeach</ul></div>@endif
@if ($identity['contactEmail'])<p class="text-center text-body-secondary mt-6 mb-0">Hubungi tim IT: {{ $identity['contactEmail'] }}</p>@endif
</div></article></div></div></main></body></html>
