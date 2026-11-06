@push('styles')
    @if ($table)
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    @endif
    @if ($editor)
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}">
    @endif
    @if ($wizard)
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/bs-stepper/bs-stepper.css') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('assets/css/awareness-compose.css') }}">
@endpush

@push('vendor-scripts')
    @if ($table)
        <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    @endif
    @if ($editor)
        <script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
    @endif
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/awareness-campaign-core.js') }}"></script>
    <script src="{{ asset('assets/js/awareness-settings-core.js') }}"></script>
    <script src="{{ asset('assets/js/awareness-compose-ui.js') }}"></script>
    @if ($reporting ?? false)
        <script src="{{ asset('assets/js/awareness-report-core.js') }}"></script>
    @endif
    <script src="{{ asset('assets/js/'.$pageScript) }}"></script>
@endpush
