@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <h3>Building Links</h3>
            <ul>
                <li>See <a href="/buildings">all buildings</a></li>
                <li>Create <a href="/buildings/create">a building</a></li>
            </ul>
        </div>
    </div>
</div>
@endsection
