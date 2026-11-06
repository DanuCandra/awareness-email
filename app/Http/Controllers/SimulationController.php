<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSimulationRequest;
use App\Http\Resources\SimulationResource;
use App\Models\Simulation;
use App\SimulationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SimulationController extends Controller
{
    public function index(Request $request, SimulationManager $manager): View|AnonymousResourceCollection
    {
        if (! $request->expectsJson()) {
            return view('pages.campaigns.index', ['pageTitle' => 'Simulasi']);
        }
        $query = $manager->query()->latest();
        if ($request->boolean('recipients')) {
            $query->with('recipients.clicks');
        }

        return SimulationResource::collection($query->get());
    }

    public function show(Simulation $simulation, SimulationManager $manager): SimulationResource
    {
        $record = $manager->query()->with('recipients.clicks')->findOrFail($simulation->id);
        $record->wasRecentlyCreated = $simulation->wasRecentlyCreated;

        return new SimulationResource($record);
    }

    public function store(SaveSimulationRequest $request, SimulationManager $manager): SimulationResource
    {
        $simulation = $manager->save($request->validated(), $request->user()->id);

        return $this->show($simulation, $manager);
    }

    public function update(SaveSimulationRequest $request, Simulation $simulation, SimulationManager $manager): SimulationResource
    {
        $simulation = $manager->save($request->validated(), $request->user()->id, $simulation);

        return $this->show($simulation, $manager);
    }

    public function duplicate(Request $request, Simulation $simulation, SimulationManager $manager): SimulationResource
    {
        return $this->show($manager->duplicate($simulation, $request->user()->id), $manager);
    }

    public function cancel(Simulation $simulation, SimulationManager $manager): SimulationResource
    {
        return $this->show($manager->cancel($simulation), $manager);
    }

    public function destroy(Simulation $simulation): JsonResponse
    {
        DB::transaction(function () use ($simulation): void {
            $current = Simulation::query()->lockForUpdate()->findOrFail($simulation->id);
            if ($current->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Hanya draf yang dapat dihapus.']);
            }
            $current->delete();
        });

        return response()->json(['message' => 'Draf simulasi dihapus.']);
    }
}
