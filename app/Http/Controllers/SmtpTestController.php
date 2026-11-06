<?php

namespace App\Http\Controllers;

use App\Http\Resources\WorkspaceSettingsResource;
use App\Models\WorkspaceSetting;
use App\SmtpDelivery;
use App\WorkspaceSettings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SmtpTestController extends Controller
{
    public function connection(Request $request, WorkspaceSettings $settings, SmtpDelivery $delivery): JsonResponse
    {
        $data = $request->validate(['revision' => ['required', 'uuid']]);
        $smtp = $settings->smtpForTest($data['revision']);
        try {
            $delivery->testConnection($smtp);
        } catch (TransportExceptionInterface|DecryptException) {
            $this->recordConnection($smtp, false);
            throw ValidationException::withMessages(['smtp' => 'Koneksi atau autentikasi SMTP gagal. Periksa host, port, enkripsi, username, password, dan akses jaringan server.']);
        }
        $this->recordConnection($smtp, true);

        $current = $settings->all();
        $changed = $current['smtp']['revision'] !== $smtp->revision;

        return response()->json([
            'message' => $changed ? 'Uji selesai untuk konfigurasi sebelumnya. Pengaturan SMTP berubah selama pengujian; uji kembali konfigurasi terbaru.' : 'Koneksi SMTP terenkripsi berhasil. Uji ini tidak mengirim email.',
            'configurationChanged' => $changed,
            'settings' => new WorkspaceSettingsResource($current),
        ]);
    }

    public function email(Request $request, WorkspaceSettings $settings, SmtpDelivery $delivery): JsonResponse
    {
        $data = $request->validate(['revision' => ['required', 'uuid'], 'recipient' => ['required', 'string', 'email:rfc', 'max:254']]);
        $smtp = $settings->smtpForTest($data['revision']);
        $recipient = strtolower(trim($data['recipient']));
        try {
            $delivery->sendTest($smtp, $recipient, $settings->all()['identity']['companyName']);
        } catch (TransportExceptionInterface|DecryptException) {
            throw ValidationException::withMessages(['smtp' => 'Pengiriman email percobaan belum dapat dipastikan. Periksa inbox atau spam, kredensial, izin alamat pengirim, dan koneksi jaringan.']);
        }
        $this->recordConnection($smtp, true);
        $current = $settings->all();
        $changed = $current['smtp']['revision'] !== $smtp->revision;

        return response()->json([
            'message' => 'Server SMTP menerima email percobaan untuk '.$recipient.'. Periksa kotak masuk atau spam; penerimaan di inbox bergantung pada penyedia email.'.($changed ? ' Pengaturan SMTP berubah selama pengujian; uji kembali konfigurasi terbaru.' : ''),
            'configurationChanged' => $changed,
            'settings' => new WorkspaceSettingsResource($current),
        ]);
    }

    private function recordConnection(WorkspaceSetting $smtp, bool $successful): void
    {
        WorkspaceSetting::query()->whereKey($smtp->id)->where('revision', $smtp->revision)
            ->update(['connection_checked_at' => now(), 'connection_successful' => $successful, 'updated_at' => $smtp->updated_at]);
    }
}
