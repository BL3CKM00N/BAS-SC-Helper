<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Reminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FolderController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $name = $this->name($request);

        $request->user()->folders()->create(['name' => $name]);

        return back()->with('status', 'Folder "'.$name.'" was added.');
    }

    public function update(Request $request, Folder $folder): RedirectResponse
    {
        $this->own($request, $folder);

        $folder->update(['name' => $this->name($request, $folder)]);

        return back()->with('status', 'Folder renamed.');
    }

    public function destroy(Request $request, Folder $folder): RedirectResponse
    {
        $this->own($request, $folder);

        $folder->delete();

        return back()->with('status', 'Folder "'.$folder->name.'" was deleted. Its reminders are still there.');
    }

    /**
     * Move a reminder into one of the person's folders, or out of them all.
     */
    public function move(Request $request, Reminder $reminder): RedirectResponse
    {
        Gate::authorize('view', $reminder);

        $user = $request->user();
        $data = $request->validate([
            'folder_id' => ['nullable', 'integer', Rule::exists('folders', 'id')->where('user_id', $user->getKey())],
        ]);

        $user->fileReminder($reminder, $data['folder_id'] ?? null);

        return back();
    }

    private function name(Request $request, ?Folder $folder = null): string
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('folders', 'name')->where('user_id', $request->user()->getKey())->ignore($folder?->getKey()),
            ],
        ], ['name.unique' => 'You already have a folder with that name.'])['name'];
    }

    private function own(Request $request, Folder $folder): void
    {
        abort_unless($folder->user_id === $request->user()->getKey(), 404);
    }
}
