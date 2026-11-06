@extends('layouts.auth')

@section('content')
    <main class="authentication-wrapper authentication-cover">
        <a href="{{ route('dashboard') }}" class="app-brand auth-cover-brand" aria-label="Security Awareness — Dashboard">
            @include('layouts.partials.brand')
        </a>
        <div class="authentication-inner row m-0">
            <div class="d-none d-xl-flex col-xl-8 p-0">
                <div class="auth-cover-bg d-flex justify-content-center align-items-center">
                    <img src="{{ asset('assets/img/illustrations/auth-login-illustration-light.png') }}" alt="Ilustrasi halaman masuk" class="my-5 auth-illustration"
                        data-app-light-img="illustrations/auth-login-illustration-light.png" data-app-dark-img="illustrations/auth-login-illustration-dark.png">
                    <img src="{{ asset('assets/img/illustrations/bg-shape-image-light.png') }}" alt="" class="platform-bg"
                        data-app-light-img="illustrations/bg-shape-image-light.png" data-app-dark-img="illustrations/bg-shape-image-dark.png">
                </div>
            </div>
            <div class="d-flex col-12 col-xl-4 align-items-center authentication-bg p-sm-12 p-6">
                <div class="w-px-400 mx-auto mt-12 pt-5">
                    <span class="badge bg-label-primary mb-5">Security Awareness</span>
                    <h4 class="mb-1">Selamat datang! 👋</h4>
                    <p class="mb-6">Masuk ke workspace untuk mengelola simulasi awareness perusahaan Anda.</p>


                    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
                    @if (request()->query('password') === 'updated')<div class="alert alert-success" role="status">Password akun diperbarui. Silakan masuk kembali.</div>@endif
                    <form id="adminLoginForm" class="mb-6" action="{{ route('login.store') }}" method="post" novalidate>
                        @csrf
                        <div class="mb-6 form-control-validation">
                            <label for="preview-identifier" class="form-label">Email atau username</label>
                            <input type="text" class="form-control" id="preview-identifier" name="identifier" value="{{ old('identifier') }}" placeholder="admin atau admin@perusahaan.example" autocomplete="username" maxlength="254" aria-describedby="loginIdentifierError" required>
                            <div class="invalid-feedback" id="loginIdentifierError"></div>
                        </div>
                        <div class="mb-6 form-password-toggle form-control-validation">
                            <label for="preview-password" class="form-label">Password</label>
                            <div class="input-group input-group-merge">
                                <input type="password" id="preview-password" name="password" class="form-control" placeholder="Masukkan password" autocomplete="current-password" maxlength="128" aria-describedby="loginPasswordError" required>
                                <button type="button" class="input-group-text cursor-pointer" aria-label="Tampilkan atau sembunyikan kata sandi"><i class="icon-base ti tabler-eye-off" aria-hidden="true"></i></button>
                            </div>
                            <div class="invalid-feedback d-block" id="loginPasswordError"></div>
                        </div>
                        <div class="my-6 d-flex flex-wrap justify-content-between gap-3">
                            <span class="small text-body-secondary">Login dengan email atau username.</span>
                            <button type="button" class="btn btn-text-primary p-0" data-bs-toggle="modal" data-bs-target="#loginHelpModal">Lupa kata sandi?</button>
                        </div>
                        <button type="submit" class="btn btn-primary d-grid w-100" id="adminLoginSubmit">Masuk</button>
                    </form>


                    <p class="text-center text-body-secondary small mt-8 mb-0">Security Awareness · Workspace Tim IT</p>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="loginHelpModal" tabindex="-1" aria-labelledby="loginHelpTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="loginHelpTitle">Bantuan akses</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body"><p class="mb-0">Hubungi administrator tim IT untuk bantuan akses ke workspace Security Awareness.</p></div>
            <div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Mengerti</button></div>
        </div></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/awareness-login.js') }}"></script>
@endpush
