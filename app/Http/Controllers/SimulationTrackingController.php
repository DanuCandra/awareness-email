<?php

namespace App\Http\Controllers;

use App\Models\SimulationRecipient;
use App\WorkspaceSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SimulationTrackingController extends Controller
{
    public function show(Request $request, string $token, WorkspaceSettings $settings): Response
    {
        $recipient = SimulationRecipient::query()->with('simulation')->where('token', $token)->firstOrFail();
        $simulation = $recipient->simulation;
        abort_unless($simulation->status !== 'draft' && in_array($recipient->status, ['sending', 'sent', 'uncertain'], true), 404);
        if (! $request->isMethod('head')) {
            $now = now();
            $recipient->clicks()->create([
                'clicked_at' => $now,
                'period' => $now->lt($simulation->starts_at) ? 'before' : ($now->gt($simulation->ends_at) ? 'after' : 'within'),
                'ip' => filter_var($request->ip(), FILTER_VALIDATE_IP) ? $request->ip() : null,
                'user_agent' => mb_substr(mb_convert_encoding($request->userAgent() ?? '', 'UTF-8', 'UTF-8'), 0, 1000),
            ]);
        }
        $configuration = $settings->all();

        return response()->view('pages.simulation-landing', ['identity' => $configuration['identity'], 'landing' => $configuration['landing']])
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag','noindex, nofollow');
    }
}
