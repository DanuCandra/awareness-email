<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTemplateCategoryRequest;
use App\Models\TemplateCategory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TemplateCategoryController extends Controller
{
    public function store(SaveTemplateCategoryRequest $request): JsonResponse
    {
        try {
            $category = TemplateCategory::create($request->safe()->only(['name', 'name_key']));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Nama kategori sudah digunakan.']);
        }

        return response()->json(['data' => $category->only(['id', 'name'])], 201);
    }

    public function update(SaveTemplateCategoryRequest $request, TemplateCategory $category): JsonResponse
    {
        try {
            $category->update($request->safe()->only(['name', 'name_key']));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Nama kategori sudah digunakan.']);
        }

        return response()->json(['data' => $category->only(['id', 'name'])]);
    }

    public function destroy(Request $request, TemplateCategory $category): JsonResponse
    {
        $data = $request->validate(['replacement_id' => ['nullable', 'integer', 'exists:template_categories,id']]);
        DB::transaction(function () use ($data, $category): void {
            $categories = TemplateCategory::query()->orderBy('id')->lockForUpdate()->get();
            $current = $categories->find($category->id);
            abort_unless($current, 404);
            if ($current->templates()->exists()) {
                $replacement = $categories->find($data['replacement_id'] ?? null);
                if (! $replacement || $replacement->is($current)) {
                    throw ValidationException::withMessages(['replacement_id' => 'Pilih kategori pengganti yang berbeda untuk template yang masih dipakai.']);
                }
                $current->templates()->update(['template_category_id' => $replacement->id, 'updated_at' => now()]);
            }
            $current->delete();
        });

        return response()->json(['message' => 'Kategori dihapus. Template email tetap tersimpan.']);
    }
}
