<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        return view('admin.expense-categories', ['categories' => ExpenseCategory::withCount('expenses')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        ExpenseCategory::create($request->validate(['name' => ['required', 'string', 'max:100', 'unique:expense_categories,name']]));

        return back()->with('success', 'Catégorie ajoutée.');
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        $expenseCategory->update($request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')->ignore($expenseCategory->id)],
        ]));

        return back()->with('success', 'Catégorie mise à jour.');
    }
}
