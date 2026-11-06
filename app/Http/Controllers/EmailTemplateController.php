<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveEmailTemplateRequest;
use App\Http\Resources\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Models\TemplateCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('pages.templates.index', ['pageTitle' => 'Template Email']);
        }

        return response()->json(['templates' => EmailTemplateResource::collection(EmailTemplate::with('category')->orderByDesc('id')->get()), 'categories' => TemplateCategory::query()->orderBy('id')->get(['id', 'name'])]);
    }

    public function store(SaveEmailTemplateRequest $request): EmailTemplateResource
    {
        $template = DB::transaction(function () use ($request): EmailTemplate {
            $category = TemplateCategory::query()->lockForUpdate()->findOrFail($request->validated('category_id'));

            return EmailTemplate::create([...$request->safe()->only(['name', 'subject', 'delta']), 'template_category_id' => $category->id]);
        });

        return new EmailTemplateResource($template->load('category'));
    }

    public function update(SaveEmailTemplateRequest $request, EmailTemplate $emailTemplate): EmailTemplateResource
    {
        DB::transaction(function () use ($request, $emailTemplate): void {
            $category = TemplateCategory::query()->lockForUpdate()->findOrFail($request->validated('category_id'));
            $emailTemplate->update([...$request->safe()->only(['name', 'subject', 'delta']), 'template_category_id' => $category->id]);
        });

        return new EmailTemplateResource($emailTemplate->load('category'));
    }

    public function destroy(EmailTemplate $emailTemplate): JsonResponse
    {
        $emailTemplate->delete();

        return response()->json(['message' => 'Template email dihapus.']);
    }
}
