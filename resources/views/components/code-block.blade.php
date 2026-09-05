@props([
    'codeAttributes' => null,
    'language',
    'wrap' => true,
])

@php($codeAttributes ??= new \Illuminate\View\ComponentAttributeBag)

<pre {{ $attributes->class(['ndb-code ndb-scrollbar ndb:m-0 ndb:min-w-0 ndb:overflow-x-auto ndb:rounded-lg ndb:bg-zinc-100 ndb:p-4 ndb:font-mono ndb:text-sm ndb:leading-6 ndb:text-zinc-700 ndb:dark:bg-zinc-950 ndb:dark:text-zinc-200', 'ndb:whitespace-pre-wrap ndb:break-words' => $wrap, 'ndb:whitespace-pre' => ! $wrap]) }}>@isset($value)<code data-ndb-language="{{ $language }}" {{ $value->attributes }}>{{ $value }}</code>@else<code data-ndb-language="{{ $language }}" {{ $codeAttributes }}>{{ $slot }}</code>@endisset</pre>
