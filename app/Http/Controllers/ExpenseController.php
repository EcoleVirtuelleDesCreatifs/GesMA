<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\AuditLogger;
use App\Services\StatsService;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request, StatsService $stats)
    {
        $this->authorizeAny('expenses.view', 'expenses.manage');
        $user = $this->user();
        $period = Period::fromRequest($request, 'month');
        $storeIds = $stats->storeIds($user, $this->selectedStoreId($request));
        // Sans le droit de consultation, un membre d'équipe ne voit que ses propres saisies.
        $ownOnly = ! $user->hasPermission('expenses.view');

        $query = $stats->expenses($storeIds, $period)
            ->when($ownOnly, fn ($q) => $q->where('expenses.user_id', $user->id))
            ->when($request->input('category_id'), fn ($q, $c) => $q->where('expense_category_id', $c));

        return view('expenses.index', [
            'period' => $period,
            'expenses' => (clone $query)->with(['category', 'store', 'user'])->latest('spent_at')->latest('id')->paginate(30)->withQueryString(),
            'total' => (int) (clone $query)->sum('amount'),
            'byCategory' => $ownOnly ? collect() : $stats->expensesByCategory($storeIds, $period),
            'byStore' => $ownOnly || ! $user->isDirection() ? collect() : $stats->byStore($storeIds, $period),
            'revenue' => $ownOnly ? null : (int) $stats->orders($storeIds, $period)->valid()->sum('total'),
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'stores' => $user->accessibleStores(),
            'storeId' => $this->selectedStoreId($request),
        ]);
    }

    public function create()
    {
        return view('expenses.form', [
            'expense' => new Expense(['spent_at' => today()]),
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'stores' => $this->user()->accessibleStores(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['store_id'] = $this->storeIdForCreation($request);
        Expense::create($data + ['user_id' => $this->user()->id]);

        return redirect()->route('expenses.index')->with('success', 'Dépense de '.money($data['amount']).' enregistrée.');
    }

    public function edit(Expense $expense)
    {
        $this->authorizeExpense($expense);

        return view('expenses.form', [
            'expense' => $expense,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'stores' => $this->user()->accessibleStores(),
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        $this->authorizeExpense($expense);
        $data = $this->validated($request);
        $data['store_id'] = $this->user()->isDirection() ? $this->storeIdForCreation($request) : $expense->store_id;
        if ((int) $data['amount'] !== $expense->amount) {
            AuditLogger::log('expense.update', sprintf('Dépense modifiée (%s) : %s → %s', $expense->description, money($expense->amount), money($data['amount'])), $expense, ['before' => $expense->amount, 'after' => (int) $data['amount']]);
        }
        $expense->update($data);

        return redirect()->route('expenses.index')->with('success', 'Dépense mise à jour.');
    }

    public function destroy(Expense $expense)
    {
        $this->authorizeExpense($expense);
        AuditLogger::log('expense.delete', sprintf('Dépense supprimée : %s (%s)', $expense->description, money($expense->amount)), $expense, $expense->only(['amount', 'spent_at', 'description']));
        $expense->delete();

        return back()->with('success', 'Dépense supprimée.');
    }

    private function authorizeExpense(Expense $expense): void
    {
        $this->authorizeStore($expense);
        $user = $this->user();
        abort_unless($user->hasPermission('expenses.view') || $expense->user_id === $user->id, 403);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'spent_at' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'integer', 'min:1'],
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'description' => ['required', 'string', 'max:255'],
            'payment_method' => ['nullable', Rule::in(array_keys(config('gesma.payment_methods')))],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ]);
    }
}
