<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\SyncConflict;
use Illuminate\Http\Request;

/** Conflits signalés lors de la synchronisation (stock, statuts) */
class SyncConflictController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $storeId = $this->storeFilter($request);
        $showResolved = $request->boolean('resolved');

        $conflicts = SyncConflict::visibleTo($user, $storeId)
            ->with('user', 'resolver', 'store', 'subject')
            ->when(! $showResolved, fn ($q) => $q->open())
            ->when($showResolved, fn ($q) => $q->whereNotNull('resolved_at'))
            ->latest()->paginate(30)->withQueryString();

        return view('conflicts.index', [
            'conflicts' => $conflicts,
            'showResolved' => $showResolved,
            'stores' => $this->selectableStores($request),
            'storeId' => $storeId,
        ]);
    }

    public function resolve(Request $request, SyncConflict $conflict)
    {
        $this->authorizeStore($request, $conflict);
        $data = $request->validate(['resolution_note' => ['required', 'string', 'max:255']], [
            'resolution_note.required' => 'Décrivez la vérification effectuée.',
        ]);
        $conflict->update([
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
            'resolution_note' => $data['resolution_note'],
        ]);
        ActivityLog::record('conflit.resolution', 'Conflit #'.$conflict->id.' résolu : '.$data['resolution_note'], $conflict);

        return back()->with('success', 'Conflit marqué comme vérifié.');
    }
}
