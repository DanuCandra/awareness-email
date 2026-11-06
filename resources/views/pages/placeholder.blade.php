@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6">
        <div><h4 class="mb-1">{{ $pageTitle }}</h4><p class="mb-0 text-body-secondary">{{ $description }}</p></div>
        <span class="badge bg-label-secondary">Pratinjau halaman</span>
    </div>
    <div class="card">
        <div class="card-header border-bottom d-flex align-items-center gap-3">
            <div class="avatar"><span class="avatar-initial rounded bg-label-primary"><i class="icon-base ti {{ $icon }} icon-26px" aria-hidden="true"></i></span></div>
            <div><h5 class="card-title mb-1">{{ $heading }}</h5><p class="card-subtitle">Security Awareness</p></div>
        </div>
        <div class="card-body text-center py-12">
            <img src="{{ asset('assets/img/illustrations/page-misc-launching-soon.png') }}" width="180" class="img-fluid mb-6" alt="Ilustrasi persiapan workspace">
            <h5 class="mb-2">Halaman ini sedang disiapkan</h5>
            <p class="text-body-secondary mb-6">Area {{ strtolower($pageTitle) }} akan dilengkapi pada tahap berikutnya.</p>
            <div class="d-flex flex-wrap justify-content-center gap-2 mb-8">
                @foreach ($features as $feature)
                    <span class="badge bg-label-secondary">{{ $feature }}</span>
                @endforeach
            </div>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="{{ route('dashboard') }}" class="btn btn-primary"><i class="icon-base ti tabler-arrow-left icon-sm me-2" aria-hidden="true"></i>Kembali ke dashboard</a>
                <button type="button" class="btn btn-label-secondary" data-open-customizer>Atur tampilan</button>
            </div>
        </div>
    </div>
@endsection
