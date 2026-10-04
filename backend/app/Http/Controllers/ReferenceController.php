<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\AuditEvent;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReferenceController
{
    public function index()
    {
        return ['apartments' => Apartment::orderBy('id')->get(), 'categories' => ExpenseCategory::orderBy('id')->get()];
    }

    public function saveApartment(Request $r, ?Apartment $apartment = null)
    {
        $v = $r->validate(['label' => ['required', 'string', 'max:20', Rule::unique('apartments')->ignore($apartment?->id)], 'alias' => ['required', 'string', 'max:20', Rule::unique('apartments')->ignore($apartment?->id)], 'floor' => 'required|integer|min:0|max:100', 'area' => 'nullable|numeric|min:1|max:999999', 'car_lift' => 'required|boolean', 'active' => 'required|boolean']);
        $a = $apartment ?? new Apartment;
        $before = $a->toArray();
        $a->fill($v)->save();
        $this->audit($r, $a, $before);

        return $a;
    }

    public function saveCategory(Request $r, ?ExpenseCategory $category = null)
    {
        $v = $r->validate(['code' => ['required', 'regex:/^[a-z_]+$/', 'max:80', Rule::unique('expense_categories')->ignore($category?->id)], 'name_el' => 'required|string|max:150', 'name_en' => 'required|string|max:150', 'active' => 'required|boolean']);
        $a = $category ?? new ExpenseCategory;
        $before = $a->toArray();
        $a->fill($v)->save();
        $this->audit($r, $a, $before);

        return $a;
    }

    public function users()
    {
        return User::orderBy('id')->get();
    }

    public function saveUser(Request $r, ?User $user = null)
    {
        $v = $r->validate(['name' => 'required|string|max:100', 'email' => ['required', 'email', Rule::unique('users')->ignore($user?->id)], 'password' => [$user ? 'nullable' : 'required', 'string', 'min:12'], 'apartment_id' => 'required|exists:apartments,id', 'active' => 'required|boolean', 'locale' => 'required|in:el,en']);
        abort_if($user?->role === 'admin', 422, 'Use the administrator command to manage admin credentials.');
        $a = $user ?? new User;
        if (empty($v['password'])) {
            unset($v['password']);
        }$before = $a->toArray();
        $a->fill([...$v, 'role' => 'resident'])->save();
        $this->audit($r, $a, $before);

        return $a;
    }

    private function audit($r, $a, $before)
    {
        AuditEvent::create(['user_id' => $r->user()->id, 'action' => 'reference.saved', 'entity' => get_class($a), 'entity_id' => $a->id, 'changes' => ['before' => $before, 'after' => $a->toArray()]]);
    }
}
