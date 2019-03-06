@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <h3>All buildings</h3>
                <p>This is a list of all buildings in this application.</p>
                <ul>
                @foreach($buildings as $building)
                        <li><strong>{{ $building->city }}, {{ $building->country }}</strong> - {{ $building->address }} - <a href="/buildings/{{ $building->id }}">Details</a></li>
                @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection
