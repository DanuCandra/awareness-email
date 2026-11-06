@if (! $authLayout)
    @php
        $searchItems = array_map(static fn (array $item): array => [
            'name' => $item['label'],
            'icon' => $item['icon'],
            'url' => route($item['route']),
        ], $navigationItems);
        $searchData = [
            'navigation' => ['Workspace' => $searchItems],
            'suggestions' => ['Halaman' => $searchItems],
        ];
    @endphp
    <script type="application/json" id="workspace-navigation">@json($searchData)</script>
@endif
<script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
<script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/node-waves/node-waves.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/@algolia/autocomplete-js.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/pickr/pickr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/hammer/hammer.js') }}"></script>
<script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
@stack('vendor-scripts')
<script src="{{ asset('assets/js/main.js') }}"></script>
<script src="{{ asset('assets/js/awareness-preview.js') }}"></script>
@auth
    @php($authenticatedAdmin = auth()->user()->only(['id', 'name', 'username', 'email']))
    <script type="application/json" id="authenticated-admin">@json($authenticatedAdmin)</script>
    @php($masterEndpoints = ['emails' => route('emails.index'), 'bulkEmails' => route('emails.bulk-destroy'), 'importPreview' => route('emails.import.preview'), 'importCommit' => route('emails.import.commit'), 'templates' => route('email-templates.index'), 'categories' => route('template-categories.store'), 'login' => route('login'), 'simulations' => route('campaigns.index')])
    <script type="application/json" id="master-endpoints">@json($masterEndpoints)</script>
    @php($settingsEndpoints = ['settings' => route('settings.index'), 'connection' => route('settings.smtp.connection'), 'testEmail' => route('settings.smtp.email')])
    <script type="application/json" id="settings-endpoints">@json($settingsEndpoints)</script>
    <script type="application/json" id="workspace-settings">@json($workspaceSettings)</script>
    <script src="{{ asset('assets/js/awareness-api.js') }}"></script>
@endauth
<script src="{{ asset('assets/js/layout.js') }}"></script>
@stack('scripts')
