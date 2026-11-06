<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveRecipientRequest;
use App\Http\Resources\RecipientResource;
use App\Models\Recipient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecipientController extends Controller
{
    public function index(Request $request): View|AnonymousResourceCollection
    {
        return $request->expectsJson() ? RecipientResource::collection(Recipient::query()->orderBy('id')->get()) : view('pages.emails', ['pageTitle' => 'Daftar Email']);
    }

    public function store(SaveRecipientRequest $request): RecipientResource
    {
        try {
            $recipient = Recipient::create(['email' => $request->validated('email'), 'source' => 'Manual']);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Email ini sudah ada di daftar.']);
        }

        return new RecipientResource($recipient);
    }

    public function update(SaveRecipientRequest $request, Recipient $recipient): RecipientResource
    {
        try {
            $recipient->update(['email' => $request->validated('email')]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Email ini sudah ada di daftar.']);
        }

        return new RecipientResource($recipient);
    }

    public function destroy(Recipient $recipient): JsonResponse
    {
        $recipient->delete();

        return response()->json(['message' => 'Email dihapus.']);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:10000'], 'ids.*' => ['required', 'integer', 'distinct']]);
        $deleted = Recipient::query()->whereIn('id', $data['ids'])->delete();

        return response()->json(['deleted' => $deleted]);
    }
}
