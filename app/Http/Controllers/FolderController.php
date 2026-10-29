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

        $user = $request->user();
        $user->folders()->create(['name' => $name, 'position' => ((int) $user->folders()->max('position')) + 1]);

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
     * Move a folder one step up or down in the person's own folder order.
     */
    public function reorder(Request $request, Folder $folder): RedirectResponse
    {
        $this->own($request, $folder);

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        // Number the folders in their current own order first, so folders made before this existed have a place.
        $folders = $request->user()->folders()->get()
            ->sort(fn (Folder $a, Folder $b) => [$a->position, mb_strtolower($a->name)] <=> [$b->position, mb_strtolower($b->name)])
            ->values();
        $index = $folders->search(fn (Folder $f) => $f->is($folder));
        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if ($folders->has($swap)) {
            $moved = $folders->all();
            [$moved[$index], $moved[$swap]] = [$moved[$swap], $moved[$index]];
            $folders = collect($moved);
        }

        $folders->each(fn (Folder $f, int $position) => $f->update(['position' => $position + 1]));

        return back();
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
