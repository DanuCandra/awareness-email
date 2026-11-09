<?php

namespace App\Http\Controllers;

use App\Http\Resources\SimulationDeliveryLogResource;
use App\Http\Resources\SimulationResource;
use App\Models\Simulation;
use App\SimulationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SimulationDeliveryLogController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('pages.delivery-logs', ['pageTitle' => 'Log Pengiriman']);
        }

        return response()->json(['data' => Simulation::query()->latest()->orderByDesc('id')
            ->get(['id', 'name', 'status', 'ends_at'])->map(fn (Simulation $simulation): array => [
                'id' => $simulation->id, 'name' => $simulation->name, 'status' => $simulation->effectiveStatus(),
            ])->all()])->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, Simulation $simulation, SimulationManager $manager): JsonResponse
    {
        $validated = $request->validate([
            'after' => ['nullable', 'integer', 'min:0', 'max:9007199254740991', 'prohibits:before'],
            'before' => ['nullable', 'integer', 'min:1', 'max:9007199254740991', 'prohibits:after'],
        ]);
        $record = $manager->query()->findOrFail($simulation->id);
        $summary = (new SimulationResource($record))->resolve($request);
        $query = $record->deliveryLogs();
        $incremental = isset($validated['after']);
        if ($incremental) {
            $query->where('id', '>', $validated['after'])->orderBy('id');
        } else {
            if (isset($validated['before'])) {
                $query->where('id', '<', $validated['before']);
            }
            $query->orderByDesc('id');
        }
        $events = $query->limit(101)->get();
        $hasMore = $events->count() > 100;
        $events = $events->take(100)->sortBy('id')->values();
        $waiting = $summary['status'] === 'completed' ? 0 : (int) $record->pending_count;
        $processing = (int) $record->processing_count;
        $phase = match (true) {
            $summary['status'] === 'draft' => 'draft',
            $summary['status'] === 'scheduled' && $record->starts_at?->gt(now()) => 'scheduled',
            $waiting + $processing === 0 && $summary['uncertain'] > 0 => 'completed_with_issues',
            $waiting + $processing === 0 => 'completed',
            $summary['status'] === 'completed' => 'closing',
            default => 'running',
        };

        return response()->json(['data' => [
            'simulation' => [
                'id' => $record->id, 'name' => $record->name, 'status' => $summary['status'], 'deliveryStatus' => $phase,
                'target' => $summary['target'], 'sent' => $summary['sent'], 'waiting' => $waiting, 'processing' => $processing,
                'uncertain' => $summary['uncertain'], 'skipped' => $summary['skipped'], 'lastError' => $record->last_error,
            ],
            'events' => SimulationDeliveryLogResource::collection($events)->resolve($request),
            'hasMore' => $hasMore, 'updatedAt' => now()->toISOString(),
        ]])->header('Cache-Control', 'no-store, private');
    }
}
