<section class="mail-preview" id="{{ $previewId }}" aria-label="Pratinjau tampilan email">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <h6 class="mb-0">Pratinjau email</h6>
        <div class="btn-group btn-group-sm" role="group" aria-label="Ukuran pratinjau">
            <button type="button" class="btn btn-primary" data-preview-device="desktop" aria-pressed="true"><i class="icon-base ti tabler-device-desktop icon-sm me-1" aria-hidden="true"></i>Desktop</button>
            <button type="button" class="btn btn-label-secondary" data-preview-device="mobile" aria-pressed="false"><i class="icon-base ti tabler-device-mobile icon-sm me-1" aria-hidden="true"></i>Mobile</button>
        </div>
    </div>
    <div class="mail-preview-device mx-auto border rounded overflow-hidden">
        <div class="p-4 border-bottom">
            <h6 class="mb-3 text-break" data-preview-subject>Subjek email</h6>
            <div class="d-flex align-items-start gap-3">
                <div class="avatar avatar-sm flex-shrink-0"><span class="avatar-initial rounded-circle bg-label-primary"><i class="icon-base ti tabler-mail icon-sm" aria-hidden="true"></i></span></div>
                <div class="overflow-hidden small"><span class="fw-medium" data-preview-sender>HRD PT AST INDONESIA</span><div class="text-body-secondary text-break" data-preview-sender-email>hrd@ast-indonesia.example</div><div class="text-body-secondary text-break mt-1">Kepada: <span data-preview-recipient>karyawan001@perusahaan.example</span></div></div>
            </div>
        </div>
        <iframe class="mail-preview-frame" title="Isi email simulasi" sandbox="" referrerpolicy="no-referrer"></iframe>
    </div>
    <p class="small text-body-secondary mt-3 mb-0">Tautan di dalam pratinjau tidak menjalankan pelacakan.</p>
</section>
