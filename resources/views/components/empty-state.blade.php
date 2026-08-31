@props(['heading'])

{{--
    Jeden stan pusty na cały panel. Wcześniej każdy ekran miał własny —
    różniły się obramowaniem, odstępami i wyrównaniem, bo powstawały osobno.
    Wygląd zmienia się tutaj i nigdzie indziej.

    Zasada treści: stan pusty ma tłumaczyć, co musi się wydarzyć, żeby coś
    się tu pojawiło — a nie tylko stwierdzać, że jest pusto.
--}}
<div class="border border-zinc-200 bg-zinc-50 p-8 text-center dark:border-neutral-700 dark:bg-neutral-900">
    <flux:heading size="lg">{{ $heading }}</flux:heading>

    <flux:text class="mx-auto mt-3 max-w-md">
        {{ $slot }}
    </flux:text>
</div>
