<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreModifierGroupRequest;
use App\Models\ModifierGroup;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ModifierController extends Controller
{
    public function index(): Response
    {
        $modifierGroups = ModifierGroup::with('modifiers:id,modifier_group_id,name,extra_price')
            ->withCount('products')
            ->latest()
            ->get();

        return Inertia::render('Owner/Modifiers/Index', [
            'modifierGroups' => $modifierGroups,
        ]);
    }

    public function store(StoreModifierGroupRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $group = ModifierGroup::create([
            'name' => $data['name'],
            'is_required' => $data['is_required'] ?? false,
            'is_multiple' => $data['is_multiple'] ?? false,
        ]);

        foreach ($data['modifiers'] as $modifier) {
            $group->modifiers()->create($modifier);
        }

        return back()->with('success', 'Modifier group berhasil ditambahkan.');
    }

    public function update(StoreModifierGroupRequest $request, ModifierGroup $modifierGroup): RedirectResponse
    {
        $data = $request->validated();

        $modifierGroup->update([
            'name' => $data['name'],
            'is_required' => $data['is_required'] ?? false,
            'is_multiple' => $data['is_multiple'] ?? false,
        ]);

        // Sync modifiers: update existing, create new, soft delete removed
        $existingIds = $modifierGroup->modifiers()->pluck('id')->toArray();
        $incomingIds = collect($data['modifiers'])->pluck('id')->filter()->toArray();

        // Delete modifiers yang tidak ada di incoming
        $toDelete = array_diff($existingIds, $incomingIds);
        if (!empty($toDelete)) {
            $modifierGroup->modifiers()->whereIn('id', $toDelete)->delete(); // soft delete
        }

        // Update/Create modifiers
        foreach ($data['modifiers'] as $modifierData) {
            if (isset($modifierData['id']) && in_array($modifierData['id'], $existingIds)) {
                $modifierGroup->modifiers()->where('id', $modifierData['id'])->update([
                    'name' => $modifierData['name'],
                    'extra_price' => $modifierData['extra_price'],
                ]);
            } else {
                $modifierGroup->modifiers()->create([
                    'name' => $modifierData['name'],
                    'extra_price' => $modifierData['extra_price'],
                ]);
            }
        }

        return back()->with('success', 'Modifier group berhasil diperbarui.');
    }

    public function destroy(ModifierGroup $modifierGroup): RedirectResponse
    {
        // Soft delete group + cascade soft delete modifiers
        $modifierGroup->modifiers()->delete(); // soft delete all modifiers

        // Hard delete pivot entries (product_modifier_groups)
        $modifierGroup->products()->detach();

        $modifierGroup->delete(); // soft delete group

        return back()->with('success', 'Modifier group berhasil dihapus.');
    }
}
