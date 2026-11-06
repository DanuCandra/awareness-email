<footer class="content-footer footer bg-footer-theme">
    <div class="container-xxl">
        <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column gap-2">
            <div class="text-body">© {{ date('Y') }} <span class="fw-medium">Security Awareness</span> · Tim IT</div>
            <div class="d-flex align-items-center gap-4">
                <a href="{{ route('admins.index', ['edit' => 'me']) }}" class="footer-link">Akun saya</a>
                <button type="button" class="btn btn-text-secondary p-0" data-open-customizer>
                    <i class="icon-base ti tabler-palette icon-sm me-1" aria-hidden="true"></i>Tampilan
                </button>
            </div>
        </div>
    </div>
</footer>
