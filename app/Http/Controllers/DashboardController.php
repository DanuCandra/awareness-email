<?php

namespace App\Http\Controllers;

use App\Http\Resources\SimulationResource;
use App\Models\Simulation;
use App\Models\SimulationClick;
use App\Models\SimulationRecipient;
use App\SimulationManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, SimulationManager $manager): JsonResponse
    {
        $validated = $request->validate(['simulation' => ['nullable', 'uuid']], [
            'simulation.uuid' => 'Pilihan Simulasi tidak valid.',
        ]);
        $simulations = Simulation::query()->latest()->orderByDesc('id')->get(['id', 'name']);
        $id = $validated['simulation'] ?? $simulations->first()?->id;
        $selected = null;

        if ($id !== null) {
            $simulation = $manager->query()->findOrFail($id);
            $summary = (new SimulationResource($simulation))->resolve($request);
            $selected = array_intersect_key($summary, array_flip([
                'id', 'name', 'status', 'scheduledAt', 'endsAt', 'target', 'sent', 'clicked', 'pending', 'uncertain', 'skipped',
            ]));
            $selected['unclicked'] = max(0, $selected['sent'] - $selected['clicked']);
            $selected['rate'] = $selected['sent'] ? round($selected['clicked'] / $selected['sent'] * 100, 1) : 0;
            $selected['progress'] = $selected['target'] ? round(($selected['sent'] + $selected['uncertain'] + $selected['skipped']) / $selected['target'] * 100, 1) : 0;

            $firstClickIds = SimulationRecipient::query()
                ->selectSub(SimulationClick::query()->select('id')
                    ->whereColumn('simulation_recipient_id', 'simulation_recipients.id')
                    ->where('period', 'within')->orderBy('clicked_at')->orderBy('id')->limit(1), 'first_click_id')
                ->where('simulation_id', $id)->where('status', 'sent');
            $firstClicks = SimulationClick::query()->whereIn('id', $firstClickIds);
            $hours = (clone $firstClicks)->orderBy('clicked_at')->get(['clicked_at'])
                ->groupBy(fn (SimulationClick $click): string => $click->clicked_at->timezone('Asia/Jakarta')->format('Y-m-d H:00'));
            $selected['activity'] = [
                'labels' => $hours->keys()->map(fn (string $hour): string => CarbonImmutable::parse($hour, 'Asia/Jakarta')->locale('id')->translatedFormat('d M H.i'))->all(),
                'counts' => $hours->map->count()->values()->all(),
            ];
            $selected['recent'] = (clone $firstClicks)->with('recipient:id,email')->orderByDesc('clicked_at')->orderByDesc('id')->limit(6)->get()
                ->map(fn (SimulationClick $click): array => [
                    'email' => $click->recipient->email, 'ip' => $click->ip ?? '', 'at' => $click->clicked_at->toISOString(),
                ])->all();
        }

        return response()->json(['data' => [
            'simulations' => $simulations->toArray(), 'selected' => $selected, 'updatedAt' => now()->toISOString(),
        ]])->header('Cache-Control', 'no-store, private');
    }
}
