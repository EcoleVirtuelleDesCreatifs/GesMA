<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $storeId = $this->storeFilter($request);
        $period = Period::fromRequest($request, 'month');

        $query = Expense::visibleTo($user, $storeId)
            ->whereBetween('spent_at', [$period->from->toDateString(), $period->to->toDateString()])
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')));

        return view('expenses.index', [
            'expenses' => (clone $query)->with('store', 'user')->latest('spent_at')->latest('id')->paginate(40)->withQueryString(),
            'total' => (clone $query)->sum('amount'),
            'byCategory' => (clone $query)->select('category', DB::raw('SUM(amount) as total'))->groupBy('category')->orderByDesc('total')->get(),
            'byStore' => (clone $query)->select('store_id', DB::raw('SUM(amount) as total'))->groupBy('store_id')->with('store')->get(),
            'stores' => $this->selectableStores($request),
            'storeId' => $storeId,
            'period' => $period,
        ]);
    }

    public function create(Request $request)
    {
        return view('expenses.form', ['expense' => new Expense(['spent_at' => today()]), 'stores' => $this->selectableStores($request)]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['store_id'] = $this->storeForCreation($request);
        Expense::create($data + ['user_id' => $request->user()->id]);

        return redirect()->route('expenses.index')->with('success', 'Dépense enregistrée.');
    }

    public function edit(Request $request, Expense $expense)
    {
        $this->authorizeStore($request, $expense);

        return view('expenses.form', ['expense' => $expense, 'stores' => $this->selectableStores($request)]);
    }

    public function update(Request $request, Expense $expense)
    {
        $this->authorizeStore($request, $expense);
        $data = $this->validated($request);
        if ($request->user()->isDirection()) {
            $data['store_id'] = $this->storeForCreation($request);
        }
        $before = $expense->only('spent_at', 'amount', 'category', 'description', 'store_id');
        $expense->update($data);
        ActivityLog::record('depense.modification', 'Modification de la dépense #'.$expense->id, $expense, ['avant' => $before, 'apres' => $expense->only(array_keys($before))]);

        return redirect()->route('expenses.index')->with('success', 'Dépense mise à jour.');
    }

    public function destroy(Request $request, Expense $expense)
    {
        $this->authorizeStore($request, $expense);
        ActivityLog::record('depense.suppression', 'Suppression de la dépense #'.$expense->id.' ('.money($expense->amount).')', null, $expense->toArray(), $expense->store_id);
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', 'Dépense supprimée.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'spent_at' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:1'],
            'category' => ['required', Rule::in(array_keys(config('gesma.expense_categories')))],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
