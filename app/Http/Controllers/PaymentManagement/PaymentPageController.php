<?php

namespace App\Http\Controllers\PaymentManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ShareManagement\Concerns\ScopesToViewer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Payment Management pages that group related lists in tabs: a tab is a Vue component with its props.
 */
class PaymentPageController extends Controller
{
    use ScopesToViewer;

    /**
     * @var array<string, array{title: string, tabs: array<int, array{key: string, title: string, component: string, props?: array<string, mixed>}>}>
     */
    public const PAGES = [
        'income' => [
            'title' => 'Income',
            'tabs' => [
                ['key' => 'group-incomes', 'title' => 'Group incomes', 'component' => 'group-incomes'],
                ['key' => 'income-categories', 'title' => 'Income categories', 'component' => 'lookup-manager', 'props' => ['title' => 'Income categories', 'endpoint' => '/income-categories']],
            ],
        ],
        'expenses' => [
            'title' => 'Expenses',
            'tabs' => [
                ['key' => 'group-expenses', 'title' => 'Group expenses', 'component' => 'group-expenses'],
                ['key' => 'group-expense-categories', 'title' => 'Expense categories', 'component' => 'lookup-manager', 'props' => ['title' => 'Expense categories', 'endpoint' => '/group-expense-categories']],
            ],
        ],
    ];

    public function show(Request $request, string $page): View
    {
        return view('backend.pages.payment-management.page', [
            'title' => self::PAGES[$page]['title'],
            'tabs' => self::PAGES[$page]['tabs'],
            'groups' => $this->groupOptions($request->user()),
        ]);
    }
}
