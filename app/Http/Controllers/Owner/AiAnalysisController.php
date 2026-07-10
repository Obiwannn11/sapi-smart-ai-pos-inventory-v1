<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Jobs\RunAiAnalysisJob;
use App\Models\AiAnalysis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAnalysisController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Owner/AiAnalysis/Index', [
            'analyses' => AiAnalysis::latest()->take(20)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:general,discount,profit_projection,custom',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'prompt' => 'nullable|string|max:1000',
        ]);

        $analysis = AiAnalysis::create([
            'user_id' => auth()->id(),
            'type' => $validated['type'],
            'status' => AiAnalysis::STATUS_PENDING,
            'params' => ['from' => $validated['from'], 'to' => $validated['to']],
            'prompt' => $validated['prompt'] ?? null,
        ]);

        RunAiAnalysisJob::dispatch($analysis->id);

        return back()->with('success', 'Analisis sedang diproses.');
    }

    public function show(AiAnalysis $aiAnalysis): Response
    {
        return Inertia::render('Owner/AiAnalysis/Index', [
            'analyses' => AiAnalysis::latest()->take(20)->get(),
            'active' => $aiAnalysis,
        ]);
    }
}
