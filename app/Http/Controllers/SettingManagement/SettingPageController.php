<?php

namespace App\Http\Controllers\SettingManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ShareManagement\Concerns\ScopesToViewer;
use App\Models\UserManagement\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The Setting Management pages. Each page is a set of tabs; a tab is a Vue component with its props.
 */
class SettingPageController extends Controller
{
    use ScopesToViewer;

    /**
     * @var array<string, array{title: string, tabs: array<int, array{key: string, title: string, component: string, props?: array<string, mixed>}>}>
     */
    public const PAGES = [
        'general' => [
            'title' => 'General settings',
            'tabs' => [
                ['key' => 'group-settings', 'title' => 'Group settings', 'component' => 'group-settings-panel'],
                ['key' => 'membership-types', 'title' => 'Membership types', 'component' => 'lookup-manager', 'props' => ['title' => 'Membership types', 'endpoint' => '/membership-types']],
            ],
        ],
        'finance' => [
            'title' => 'Finance settings',
            'tabs' => [
                ['key' => 'fund-types', 'title' => 'Fund types', 'component' => 'lookup-manager', 'props' => ['title' => 'Fund types', 'endpoint' => '/fund-types', 'hasCode' => true]],
                ['key' => 'contribution-frequencies', 'title' => 'Contribution frequencies', 'component' => 'lookup-manager', 'props' => ['title' => 'Contribution frequencies', 'endpoint' => '/contribution-frequencies']],
                ['key' => 'loan-interest-methods', 'title' => 'Loan interest methods', 'component' => 'lookup-manager', 'props' => ['title' => 'Loan interest methods', 'endpoint' => '/loan-interest-methods', 'hasCode' => true, 'lockCode' => true]],
                ['key' => 'repayment-frequencies', 'title' => 'Repayment frequencies', 'component' => 'lookup-manager', 'props' => ['title' => 'Repayment frequencies', 'endpoint' => '/repayment-frequencies', 'hasInterval' => true]],
                ['key' => 'penalty-types', 'title' => 'Penalty types', 'component' => 'lookup-manager', 'props' => ['title' => 'Penalty types', 'endpoint' => '/penalty-types']],
            ],
        ],
        'fines' => [
            'title' => 'Fine types',
            'tabs' => [
                ['key' => 'fine-types', 'title' => 'Fine types', 'component' => 'fine-types'],
            ],
        ],
    ];

    public function show(Request $request, string $page): View
    {
        $groups = collect($this->groupOptions($request->user()))->map(fn (array $option) => [
            ...$option,
            'can_manage' => Gate::allows('manageSettings', Group::query()->findOrFail($option['value'])),
        ])->all();

        return view('backend.pages.setting-management.page', [
            'title' => self::PAGES[$page]['title'],
            'tabs' => self::PAGES[$page]['tabs'],
            'groups' => $groups,
        ]);
    }
}
