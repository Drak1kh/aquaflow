@props([
    'name',
    'label' => 'Billing month',
    'value' => null,
    'placeholder' => 'All months',
    'required' => false,
])

@php
    $validValue = is_string($value) && preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/', $value) && substr($value, 0, 4) !== '0000';
    $selectedMonth = $validValue ? substr($value, 5, 2) : '';
    $selectedYear = $validValue ? substr($value, 0, 4) : now()->format('Y');
@endphp

<div
    {{ $attributes->class('min-w-0') }}
    x-data="{ month: @js($selectedMonth), year: @js($selectedYear) }"
    x-effect="$dispatch('month-input-changed', { value: month && year ? String(year).padStart(4, '0') + '-' + month : '' })"
>
    <fieldset class="space-y-2">
        <legend class="text-sm font-medium leading-4 text-zinc-800 dark:text-white">{{ __($label) }}@if ($required) <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">{{ __('Required') }}</span>@endif</legend>
        <div class="flex items-center gap-2">
            <flux:select x-model="month" :aria-label="__($label).' — '.__('Month')" :required="$required" :invalid="$errors->has($name)">
                <option value="" @selected($selectedMonth === '')>{{ $required ? __('Select month') : __($placeholder) }}</option>
                @foreach (range(1, 12) as $monthNumber)
                    @php $monthValue = sprintf('%02d', $monthNumber); @endphp
                    <option value="{{ $monthValue }}" @selected($selectedMonth === $monthValue)>{{ __(date('F', mktime(0, 0, 0, $monthNumber, 1))) }}</option>
                @endforeach
            </flux:select>
            <flux:input type="number" x-model="year" :value="$selectedYear" min="1" max="9999" step="1" class="max-w-28" :aria-label="__($label).' — '.__('Year')" x-bind:required="month !== ''" :invalid="$errors->has($name)" />
        </div>
        <flux:error :name="$name" />
    </fieldset>
    <input type="hidden" name="{{ $name }}" value="{{ $validValue ? $value : '' }}" x-bind:value="month && year ? String(year).padStart(4, '0') + '-' + month : ''">
</div>
