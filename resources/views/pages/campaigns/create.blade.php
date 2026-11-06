@extends('layouts.app')
@include('pages.partials.compose-assets', ['table' => true, 'editor' => true, 'wizard' => true, 'pageScript' => 'awareness-campaign-wizard.js'])

@section('content')
    <div id="campaignComposer" data-index-url="{{ route('campaigns.index') }}" data-detail-url="{{ route('campaigns.show') }}">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-6">
            <div><h4 class="mb-1" id="campaignFormTitle">Buat simulasi</h4><p class="mb-0 text-body-secondary">Lengkapi detail, penerima, dan pesan sebelum meninjau simulasi.</p></div>
            <div class="d-flex flex-wrap gap-3"><a href="{{ route('campaigns.index') }}" class="btn btn-label-secondary">Kembali</a><button type="button" class="btn btn-label-primary" id="saveCampaignDraft"><i class="icon-base ti tabler-device-floppy icon-sm me-2" aria-hidden="true"></i>Simpan draf</button></div>
        </div>
        <div class="alert alert-primary mb-6" role="note"><span class="fw-medium">Mode pratinjau.</span> Data disimpan sementara di tab ini. Menjadwalkan pratinjau tidak mengirim email.</div>
        <div class="alert alert-danger d-none" id="wizardUnavailable" role="alert"></div>
        <div class="card" id="wizardWorkspace">
            <div class="bs-stepper wizard-numbered compose-wizard" id="campaignWizard">
                <div class="bs-stepper-header border-bottom">
                    @foreach ([['Detail', 'Informasi simulasi'], ['Penerima', 'Pilih alamat email'], ['Isi email', 'Template dan pesan'], ['Pratinjau', 'Periksa dan simpan']] as $step)
                        <div class="step {{ $loop->first ? 'active' : '' }}" data-wizard-header="{{ $loop->index }}"><button type="button" class="step-trigger" data-wizard-step="{{ $loop->index }}" aria-controls="campaignStep{{ $loop->index }}"><span class="bs-stepper-circle">{{ $loop->iteration }}</span><span class="bs-stepper-label"><span class="bs-stepper-title">{{ $step[0] }}</span><span class="bs-stepper-subtitle">{{ $step[1] }}</span></span></button></div>
                        @unless ($loop->last)<div class="line"><i class="icon-base ti tabler-chevron-right" aria-hidden="true"></i></div>@endunless
                    @endforeach
                </div>
                <div class="bs-stepper-content p-6">
                    <div class="alert alert-danger d-none" id="wizardAlert" role="alert"></div>
                    <div class="content active" id="campaignStep0" data-wizard-panel="0">
                        <h5 class="mb-1">Detail simulasi</h5><p class="text-body-secondary mb-6">Tentukan identitas simulasi dan pengirim email.</p>
                        <div class="row g-5">
                            <div class="col-12"><label for="campaignName" class="form-label">Nama simulasi <span class="text-danger">*</span></label><input type="text" class="form-control" id="campaignName" maxlength="120" placeholder="Contoh: Simulasi Kenaikan Gaji PT AST INDONESIA 2027"><div class="invalid-feedback" data-field-error="name"></div></div>
                            <div class="col-12"><label for="campaignDescription" class="form-label">Deskripsi</label><textarea class="form-control" id="campaignDescription" rows="3" maxlength="500" placeholder="Tujuan atau catatan singkat simulasi"></textarea></div>
                            <div class="col-md-6"><label for="campaignSenderName" class="form-label">Nama pengirim <span class="text-danger">*</span></label><input type="text" class="form-control" id="campaignSenderName" maxlength="80"><div class="invalid-feedback" data-field-error="senderName"></div><div class="form-text">Terisi dari pengaturan. Bisa diubah khusus untuk simulasi ini.</div></div>
                            <div class="col-md-6"><label for="campaignSenderEmail" class="form-label">Email pengirim · Otomatis dari SMTP</label><input type="email" class="form-control" id="campaignSenderEmail" readonly aria-describedby="campaignSenderEmailHelp"><div class="invalid-feedback" data-field-error="senderEmail"></div><div class="form-text" id="campaignSenderEmailHelp">Mengikuti Email pengirim (From) pada <a href="{{ route('settings.index', ['section' => 'smtp']) }}">Pengaturan SMTP</a>.</div></div>
                            <div class="col-12 d-none" id="campaignSenderUpdated" role="status"><div class="alert alert-secondary mb-0">Email pengirim pada draf ini disesuaikan dengan pengaturan SMTP terbaru. Nama pengirim simulasi tetap dapat diubah.</div></div>
                            <div class="col-md-6"><label for="campaignSchedule" class="form-label">Tanggal dan jam mulai</label><input type="datetime-local" class="form-control" id="campaignSchedule" aria-describedby="campaignStartHelp"><div class="invalid-feedback" data-field-error="schedule"></div><div class="form-text" id="campaignStartHelp">Waktu mulai pengiriman · WIB (Asia/Jakarta). Boleh kosong untuk draf.</div></div>
                            <div class="col-md-6"><label for="campaignEndsAt" class="form-label">Tanggal dan jam selesai · Batas evaluasi</label><input type="datetime-local" class="form-control" id="campaignEndsAt" aria-describedby="campaignEndHelp"><div class="invalid-feedback" data-field-error="endsAt"></div><div class="form-text" id="campaignEndHelp">Wajib saat menjadwalkan simulasi. Harus setelah waktu mulai · WIB.</div></div>
                        </div>
                    </div>
                    <div class="content" id="campaignStep1" data-wizard-panel="1" hidden>
                        <h5 class="mb-1">Pilih penerima</h5><p class="text-body-secondary mb-6">Gunakan seluruh daftar contoh atau pilih email tertentu.</p>
                        <div class="row g-4">
                            <div class="col-md-6"><div class="form-check custom-option custom-option-basic"><label class="form-check-label custom-option-content" for="campaignRecipientAll"><input class="form-check-input" type="radio" id="campaignRecipientAll" name="campaignRecipientMode" value="all" checked><span class="custom-option-header"><span class="h6 mb-0">Semua email</span><span class="text-body-secondary">480 email</span></span><span class="custom-option-body"><small>Seluruh alamat email dalam daftar contoh.</small></span></label></div></div>
                            <div class="col-md-6"><div class="form-check custom-option custom-option-basic"><label class="form-check-label custom-option-content" for="campaignRecipientSelected"><input class="form-check-input" type="radio" id="campaignRecipientSelected" name="campaignRecipientMode" value="selected"><span class="custom-option-header"><span class="h6 mb-0">Pilih email tertentu</span></span><span class="custom-option-body"><small>Cari dan tandai penerima sesuai kebutuhan.</small></span></label></div></div>
                        </div>
                        <div class="small text-danger mt-3" data-field-error="recipients"></div>
                        <div class="d-flex align-items-center gap-2 mt-5"><span class="badge bg-label-primary" id="selectedRecipientTotal">480</span><span class="text-body-secondary">email penerima terpilih</span></div>
                        <div class="mt-6" id="recipientSelectionPanel" hidden>
                            <div class="row g-3 align-items-end mb-4"><div class="col-md-6"><label for="wizardRecipientSearch" class="form-label">Cari email</label><input type="search" class="form-control" id="wizardRecipientSearch" placeholder="Cari alamat email…"></div><div class="col-md-6 d-flex flex-wrap justify-content-md-end gap-2"><button type="button" class="btn btn-label-primary btn-sm" id="selectFilteredRecipients">Pilih hasil pencarian</button><button type="button" class="btn btn-label-secondary btn-sm" id="clearCampaignRecipients">Kosongkan pilihan</button></div></div>
                            <div class="table-responsive border rounded"><table class="table" id="wizardRecipientTable"><thead><tr><th><input type="checkbox" class="form-check-input" id="selectRecipientPage" aria-label="Pilih semua email pada halaman ini"></th><th>Email penerima</th><th>Sumber</th></tr></thead><tbody></tbody></table></div>
                        </div>
                    </div>
                    <div class="content" id="campaignStep2" data-wizard-panel="2" hidden>
                        <h5 class="mb-1">Susun isi email</h5><p class="text-body-secondary mb-6">Gunakan template sebagai awal, lalu sesuaikan subjek dan isi pesan.</p>
                        <div class="row g-6">
                            <div class="col-lg-7">
                                <div class="row g-3 align-items-end mb-5"><div class="col-sm-8"><label for="campaignTemplate" class="form-label">Template email</label><select class="form-select" id="campaignTemplate"></select></div><div class="col-sm-4"><button type="button" class="btn btn-label-primary w-100" id="applyCampaignTemplate">Gunakan template</button></div></div>
                                <div class="mb-5"><label for="campaignSubject" class="form-label">Subjek email <span class="text-danger">*</span></label><input type="text" class="form-control" id="campaignSubject" maxlength="180" placeholder="Subjek email simulasi"><div class="invalid-feedback" data-field-error="subject"></div></div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><span class="small text-body-secondary me-1">Sisipkan:</span><button type="button" class="btn btn-sm btn-label-secondary" data-insert-token="email">Email penerima</button><button type="button" class="btn btn-sm btn-label-secondary" data-insert-token="campaign">Nama simulasi</button><button type="button" class="btn btn-sm btn-label-primary" data-insert-token="link">Tautan simulasi</button></div>
                                <div class="compose-editor" id="campaignMessageEditor"></div>
                                <div class="small text-danger mt-2" data-field-error="body"></div>
                                <div class="d-flex flex-wrap justify-content-between gap-2 mt-3 small text-body-secondary"><span>Tautan simulasi harus tersedia di dalam pesan.</span><span id="campaignBodyCount">0 / 20.000 karakter</span></div>
                            </div>
                            <div class="col-lg-5">@include('pages.partials.mail-preview', ['previewId' => 'campaignLivePreview'])</div>
                        </div>
                    </div>
                    <div class="content" id="campaignStep3" data-wizard-panel="3" hidden>
                        <h5 class="mb-1">Tinjau simulasi</h5><p class="text-body-secondary mb-6">Periksa pengirim, penerima, jadwal, dan isi email sebelum menyimpan.</p>
                        <div class="row g-6">
                            <div class="col-lg-5"><div class="border rounded p-5"><h6 class="mb-5">Ringkasan simulasi</h6><dl class="row mb-0"><dt class="col-12 small text-body-secondary fw-normal mb-1">Nama simulasi</dt><dd class="col-12 fw-medium mb-4 text-break" id="reviewCampaignName"></dd><dt class="col-12 small text-body-secondary fw-normal mb-1">Pengirim</dt><dd class="col-12 mb-4 text-break" id="reviewCampaignSender"></dd><dt class="col-12 small text-body-secondary fw-normal mb-1">Penerima</dt><dd class="col-12 mb-4" id="reviewCampaignRecipients"></dd><dt class="col-12 small text-body-secondary fw-normal mb-1">Mulai · WIB</dt><dd class="col-12 mb-4" id="reviewCampaignSchedule"></dd><dt class="col-12 small text-body-secondary fw-normal mb-1">Selesai · Batas evaluasi · WIB</dt><dd class="col-12 mb-4" id="reviewCampaignEndsAt"></dd><dt class="col-12 small text-body-secondary fw-normal mb-1">Subjek email</dt><dd class="col-12 mb-0 text-break" id="reviewCampaignSubject"></dd></dl></div></div>
                            <div class="col-lg-7">@include('pages.partials.mail-preview', ['previewId' => 'campaignReviewPreview'])</div>
                        </div>
                    </div>
                </div>
                <div class="border-top d-flex flex-wrap flex-column flex-sm-row justify-content-sm-between gap-3 px-6 py-5">
                    <button type="button" class="btn btn-label-secondary" id="wizardPrevious">Kembali</button>
                    <button type="button" class="btn btn-primary" id="wizardNext">Lanjutkan<i class="icon-base ti tabler-arrow-right icon-sm ms-2" aria-hidden="true"></i></button>
                </div>
            </div>
        </div>
    </div>
    @include('pages.partials.compose-feedback')
@endsection
