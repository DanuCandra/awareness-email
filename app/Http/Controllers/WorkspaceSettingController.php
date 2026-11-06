<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveWorkspaceSettingRequest;
use App\Http\Resources\WorkspaceSettingsResource;
use App\WorkspaceSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceSettingController extends Controller
{
    public function index(Request $request, WorkspaceSettings $settings): View|WorkspaceSettingsResource
    {
        return $request->expectsJson()
            ? new WorkspaceSettingsResource($settings->all())
            : view('pages.settings', ['pageTitle' => 'Pengaturan']);
    }

    public function update(SaveWorkspaceSettingRequest $request, WorkspaceSettings $settings, string $section): WorkspaceSettingsResource
    {
        $settings->save($section, $request->validated());

        return new WorkspaceSettingsResource($settings->all());
    }
}
