@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <h3>Add new building</h3>
                <p>This is a form to create a new building for this application.</p>

                <h4><a href="/buildings">Back to the list</a></h4>

                <h5>Country</h5>
                <p>{{ $building->country }}</p>

                <h5>City</h5>
                <p>{{ $building->city }}</p>

                <h5>Address</h5>
                <p>{{ $building->address }}</p>

                <h5>No. of Apartments</h5>
                <p>{{ $building->apartments }}</p>

                <h5>Has parking</h5>
                @if($building->parking === 'on')
                    <p>Yes</p>
                @else
                    <p>No</p>
                @endif

            </div>
        </div>
    </div>
@endsection
