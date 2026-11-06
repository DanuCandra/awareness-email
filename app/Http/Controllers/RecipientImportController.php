<?php

namespace App\Http\Controllers;

use App\EmailImportReader;
use App\Models\Recipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecipientImportController extends Controller
{
    public function store(Request $request, EmailImportReader $reader): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:5120', 'extensions:csv,xlsx']]);
        $file = $request->file('file');
        $rows = $reader->read($file);
        $result = $reader->validateRows($rows, Recipient::query()->pluck('email')->all());
        $token = (string) Str::uuid();
        Cache::put('email-import:'.$request->user()->id.':'.$token, ['emails' => $result['valid'], 'source' => strtolower($file->getClientOriginalExtension()) === 'csv' ? 'CSV' : 'Excel'], now()->addMinutes(15));

        return response()->json([...$result, 'token' => $token]);
    }

    public function commit(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'uuid']]);
        $key = 'email-import:'.$request->user()->id.':'.$data['token'];

        return Cache::lock($key.':lock', 60)->block(5, function () use ($key): JsonResponse {
            $preview = Cache::get($key);
            if (! $preview) {
                throw ValidationException::withMessages(['token' => 'Pratinjau impor kedaluwarsa atau tidak tersedia. Periksa kembali file Anda.']);
            }
            if (isset($preview['result'])) {
                return response()->json($preview['result']);
            }
            $added = DB::transaction(function () use ($preview): int {
                $added = 0;
                foreach (array_chunk($preview['emails'], 500) as $chunk) {
                    $now = now();
                    $added += Recipient::query()->insertOrIgnore(array_map(fn (string $email): array => ['email' => $email, 'source' => $preview['source'], 'created_at' => $now, 'updated_at' => $now], $chunk));
                }

                return $added;
            });
            $result = ['added' => $added, 'skipped' => count($preview['emails']) - $added];
            Cache::put($key, ['result' => $result], now()->addMinutes(15));

            return response()->json($result);
        });
    }
}
