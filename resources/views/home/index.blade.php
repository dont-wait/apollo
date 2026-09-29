@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="page-heading">
        <div>
            <h1>Users</h1>
            <p class="muted">Data loaded from the User model by HomeController.</p>
        </div>
    </div>

    <div class="card">
        @if ($users->isEmpty())
            <div class="empty-state">
                <h2>No users found</h2>
                <p class="muted">Create a user to see it rendered here.</p>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if ($users->hasPages())
        <div class="pagination">
            {{ $users->links() }}
        </div>
    @endif
@endsection
