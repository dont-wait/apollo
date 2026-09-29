@extends('layouts.app')

@section('title', 'Users · NeuralLog')

@section('content')
    <div class="flex flex-col gap-8">
        <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2 font-mono text-xs uppercase tracking-[0.18em] text-primary">
                    <span class="size-1.5 rounded-full bg-tertiary"></span>
                    <span>Data registry</span>
                </div>
                <div class="flex flex-col gap-2">
                    <h1 class="font-display text-3xl font-semibold tracking-tight text-on-surface sm:text-4xl">Users</h1>
                    <p class="max-w-2xl text-sm leading-6 text-on-surface-variant">Data loaded from the User model by HomeController.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 self-start rounded-full border border-outline-variant bg-surface px-3 py-1.5 font-mono text-xs text-on-surface-variant sm:self-auto">
                <span class="size-1.5 rounded-full bg-tertiary"></span>
                <span>LIVE DATABASE</span>
            </div>
        </div>

        <section class="overflow-hidden rounded-2xl border border-outline-variant bg-surface shadow-2xl shadow-black/10" aria-labelledby="users-table-title">
            <div class="flex flex-col gap-3 border-b border-outline-variant bg-surface-low px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 id="users-table-title" class="font-display text-lg font-semibold text-on-surface">Directory</h2>
                    <p class="mt-1 text-sm text-on-surface-variant">Registered accounts in the application.</p>
                </div>
                <span class="w-fit rounded-md border border-outline-variant/80 bg-surface-high px-2.5 py-1 font-mono text-xs text-on-surface-variant">{{ $users->total() }} RECORDS</span>
            </div>

            @if ($users->isEmpty())
                <div class="flex flex-col items-center gap-3 px-6 py-16 text-center">
                    <h2 class="font-display text-xl font-semibold text-on-surface">No users found</h2>
                    <p class="text-sm text-on-surface-variant">Create a user to see it rendered here.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-xl text-left">
                        <thead class="border-b border-outline-variant bg-surface-lowest">
                            <tr class="font-mono text-xs uppercase tracking-[0.12em] text-on-surface-variant">
                                <th class="px-5 py-4 font-medium sm:px-6">Name</th>
                                <th class="px-5 py-4 font-medium sm:px-6">Email</th>
                                <th class="px-5 py-4 font-medium sm:px-6">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/70">
                            @foreach ($users as $user)
                                <tr class="transition-colors hover:bg-surface-high/60">
                                    <td class="px-5 py-4 text-sm font-medium text-on-surface sm:px-6">{{ $user->name }}</td>
                                    <td class="px-5 py-4 text-sm text-on-surface-variant sm:px-6">{{ $user->email }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-on-surface-variant sm:px-6">{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($users->hasPages())
            <div class="overflow-x-auto">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection
