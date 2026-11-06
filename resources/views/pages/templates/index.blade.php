@extends('layouts.app')
@include('pages.partials.compose-assets', ['table' => false, 'editor' => true, 'wizard' => false, 'pageScript' => 'awareness-templates.js'])

@section('content')
    <div id="templateWorkspace">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-6"><div><h4 class="mb-1">Template Email</h4><p class="mb-0 text-body-secondary">Susun pesan simulasi yang dapat digunakan kembali.</p></div><div class="d-flex flex-wrap gap-3"><button type="button" class="btn btn-label-secondary" id="manageTemplateCategories"><i class="icon-base ti tabler-folders icon-sm me-2" aria-hidden="true"></i>Kelola kategori</button><button type="button" class="btn btn-primary" id="createEmailTemplate"><i class="icon-base ti tabler-plus icon-sm me-2" aria-hidden="true"></i>Buat template</button></div></div>
        <div class="alert alert-primary mb-6" role="note"><span class="fw-medium">Koleksi template email.</span> Template dan kategori tersimpan serta dapat dipilih saat membuat simulasi.</div>
        <div class="card mb-6"><div class="card-body"><div class="row g-4 align-items-end"><div class="col-md-7"><label for="templateSearch" class="form-label">Cari template</label><div class="input-group input-group-merge"><span class="input-group-text"><i class="icon-base ti tabler-search" aria-hidden="true"></i></span><input type="search" class="form-control" id="templateSearch" placeholder="Cari nama atau subjek email…"></div></div><div class="col-md-3"><label for="templateCategoryFilter" class="form-label">Kategori</label><select class="form-select" id="templateCategoryFilter"><option value="">Semua kategori</option><option>HR &amp; Payroll</option><option>Administrasi</option><option>Benefit Karyawan</option></select></div><div class="col-md-2"><p class="text-body-secondary mb-2 small" id="templateCount">3 template</p></div></div></div></div>
        <div class="alert alert-secondary d-none" id="noTemplateCategories" role="status">Belum ada kategori. Gunakan <strong>Kelola kategori</strong> untuk membuat kategori sebelum menambahkan template.</div>
        <div class="row g-6" id="templateGrid"></div>
        <div class="card d-none" id="templateEmpty"><div class="card-body text-center py-12"><div class="avatar avatar-lg mx-auto mb-5"><span class="avatar-initial rounded bg-label-secondary"><i class="icon-base ti tabler-file-search icon-32px" aria-hidden="true"></i></span></div><h5 class="mb-2">Tidak ada template yang sesuai</h5><p class="text-body-secondary mb-5">Coba kata kunci atau kategori lain.</p><button type="button" class="btn btn-label-primary" id="clearTemplateFilters">Reset pencarian</button></div></div>
    </div>

    <div class="modal fade" id="templateEditorModal" tabindex="-1" aria-labelledby="templateEditorTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form id="templateEditorForm" novalidate>
        <div class="modal-header border-bottom"><div><h5 class="modal-title mb-1" id="templateEditorTitle">Buat template email</h5><p class="small text-body-secondary mb-0">Susun pesan dan periksa tampilannya sebelum disimpan.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <div class="modal-body p-6">
            <div class="row g-5 mb-6"><div class="col-md-8"><label for="templateName" class="form-label">Nama template <span class="text-danger">*</span></label><input type="text" class="form-control" id="templateName" maxlength="100" placeholder="Contoh: Kenaikan Gaji PT AST INDONESIA 2027"><div class="invalid-feedback" data-template-error="name"></div></div><div class="col-md-4"><label for="templateCategory" class="form-label">Kategori</label><select class="form-select" id="templateCategory"><option>HR &amp; Payroll</option><option>Administrasi</option><option>Benefit Karyawan</option></select><div class="invalid-feedback" data-template-error="category"></div></div></div>
            <div class="row g-6"><div class="col-lg-7">
                <div class="mb-5"><label for="templateSubject" class="form-label">Subjek email <span class="text-danger">*</span></label><input type="text" class="form-control" id="templateSubject" maxlength="180" placeholder="Subjek email yang diterima karyawan"><div class="invalid-feedback" data-template-error="subject"></div></div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3" id="templateTokenButtons"><span class="small text-body-secondary me-1">Sisipkan:</span><button type="button" class="btn btn-sm btn-label-secondary" data-insert-token="email">Email penerima</button><button type="button" class="btn btn-sm btn-label-secondary" data-insert-token="campaign">Nama simulasi</button><button type="button" class="btn btn-sm btn-label-primary" data-insert-token="link">Tautan simulasi</button></div>
                <div class="compose-editor" id="templateMessageEditor"></div><div class="small text-danger mt-2" data-template-error="body"></div><div class="d-flex flex-wrap justify-content-between gap-2 mt-3 small text-body-secondary"><span>Sisipkan tautan simulasi ke dalam pesan.</span><span id="templateBodyCount">0 / 20.000 karakter</span></div>
            </div><div class="col-lg-5">@include('pages.partials.mail-preview', ['previewId' => 'templateEditorPreview'])</div></div>
        </div>
        <div class="modal-footer border-top flex-column flex-sm-row align-items-stretch justify-content-sm-between gap-3 px-6 py-5"><button type="button" class="btn btn-label-secondary m-0" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary m-0" id="saveEmailTemplate"><i class="icon-base ti tabler-device-floppy icon-sm me-2" aria-hidden="true"></i>Simpan template</button></div>
    </form></div></div></div>

    <div class="modal fade" id="templatePreviewModal" tabindex="-1" aria-labelledby="templatePreviewTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header border-bottom"><h5 class="modal-title text-break" id="templatePreviewTitle">Pratinjau template</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div><div class="modal-body p-6">@include('pages.partials.mail-preview', ['previewId' => 'templateReadPreview'])</div><div class="modal-footer border-top px-6 py-5"><button type="button" class="btn btn-label-secondary m-0" data-bs-dismiss="modal">Tutup</button></div></div></div></div>
    <div class="modal fade" id="templateCategoryModal" tabindex="-1" aria-labelledby="templateCategoryTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
            <div class="modal-header border-bottom"><div><h5 class="modal-title mb-1" id="templateCategoryTitle">Kelola kategori</h5><p class="small text-body-secondary mb-0">Kategori digunakan pada template dan filter koleksi email.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body p-6">
                <div class="alert alert-danger d-none" id="categoryActionError" role="alert"></div>
                <div id="categoryManagePanel">
                    <form id="templateCategoryForm" novalidate>
                        <div class="row g-3 align-items-start">
                            <div class="col-lg-7"><label for="templateCategoryName" class="form-label" id="categoryNameLabel">Nama kategori baru</label><input type="text" class="form-control" id="templateCategoryName" maxlength="60" placeholder="Contoh: Benefit dan Tunjangan" autocomplete="off" aria-describedby="templateCategoryNameError" required><div class="invalid-feedback" id="templateCategoryNameError"></div></div>
                            <div class="col-lg-5"><span class="form-label d-none d-lg-block" aria-hidden="true">&nbsp;</span><div class="d-flex gap-2"><button type="submit" class="btn btn-primary flex-grow-1" id="saveTemplateCategory">Tambah kategori</button><button type="button" class="btn btn-label-secondary d-none" id="cancelCategoryEdit">Batal</button></div></div>
                        </div>
                    </form>
                    <div class="d-flex align-items-center justify-content-between gap-3 mt-8 mb-4"><h6 class="mb-0">Daftar kategori</h6><span class="badge bg-label-primary" id="categoryRegistryCount">3 kategori</span></div>
                    <div class="table-responsive border rounded"><table class="table mb-0"><thead><tr><th>Nama kategori</th><th>Template</th><th>Aksi</th></tr></thead><tbody id="templateCategoryRows"></tbody></table></div>
                </div>
                <div id="categoryDeletePanel" hidden>
                    <h6 class="mb-3" id="categoryDeleteHeading"></h6><p class="text-body-secondary mb-5" id="categoryDeleteDescription"></p>
                    <div id="categoryReplacementField"><label for="categoryReplacement" class="form-label">Pindahkan template ke kategori</label><select class="form-select" id="categoryReplacement"></select><p class="form-text mb-0">Template email tetap tersimpan. Hanya kategorinya yang diubah.</p></div>
                    <div class="alert alert-warning mt-4 mb-0 d-none" id="categoryReplacementUnavailable">Tambahkan kategori pengganti terlebih dahulu sebelum menghapus kategori yang masih dipakai.</div>
                </div>
            </div>
            <div class="modal-footer border-top flex-column flex-sm-row align-items-stretch justify-content-sm-between gap-3 px-6 py-5"><button type="button" class="btn btn-label-secondary m-0" id="categoryModalBack">Tutup</button><button type="button" class="btn btn-danger m-0 d-none" id="confirmCategoryDelete">Hapus kategori</button></div>
        </div></div>
    </div>
    @include('pages.partials.compose-feedback')
@endsection
