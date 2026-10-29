{{-- A small sort dropdown. The choice is saved in a cookie by the controller, so only the changed list is sent. --}}
<form method="GET" action="{{ route('reminders.index') }}" class="flex items-center gap-2">
    <input type="hidden" name="scope" value="{{ $scope }}">
    <label for="sort-{{ $field }}" class="text-xs text-[#b8bab8]">Sort</label>
    <select id="sort-{{ $field }}" name="{{ $field }}" data-auto-submit
        class="rounded-lg border border-white/15 bg-[#1d1d1a] px-2 py-1.5 text-xs text-[#b8bab8] outline-none focus:border-[#1da15e]">
        @foreach ($options as $value => $label)
            <option value="{{ $value }}" @selected($sorting->chosen[$field] === $value)>{{ $label }}</option>
        @endforeach
    </select>
</form>
