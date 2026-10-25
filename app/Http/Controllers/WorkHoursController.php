<?php

namespace App\Http\Controllers;

use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkHoursController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.work-hours', [
            'user' => $request->user(),
            'timezones' => DateTimeZone::listIdentifiers(),
            'days' => [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['integer', 'between:1,7'],
            'work_start' => ['required', 'date_format:H:i'],
            'work_end' => ['required', 'date_format:H:i', 'after:work_start'],
        ], [
            'work_days.required' => 'Pick at least one working day.',
            'work_end.after' => 'The end of the workday must be after the start.',
        ]);

        $days = collect($data['work_days'])->map(fn ($day) => (int) $day)->unique()->sort()->implode(',');

        $request->user()->update([
            'timezone' => $data['timezone'],
            'work_days' => $days,
            'work_start' => $data['work_start'],
            'work_end' => $data['work_end'],
        ]);

        return redirect()->route('account.work-hours')->with('status', 'Your work hours were saved.');
    }
}
