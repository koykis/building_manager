@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <h3>Add new building</h3>
                <p>This is a form to create a new building for this application.</p>

                <form method="POST" action="/buildings">
                    {{ csrf_field() }}
                    <input type="hidden" name="manager_id" value="{{ $uid }}">

                    <div class="col-md-8">
                        <label class="col-form-label">Building Country</label>
                        <input type="text" class="form-control form-text" name="country" placeholder="Country">
                    </div>

                    <div class="col-md-8">
                        <label class="col-form-label">Building City</label>
                        <input type="text" class="form-control form-text" name="city" placeholder="City">
                    </div>

                    <div class="col-md-8">
                        <label class="col-form-label">Building Address</label>
                        <input type="text" class="form-control form-text" name="address" placeholder="Address">
                    </div>

                    <div class="col-md-8">
                        <label class="col-form-label">No. of Apartments</label>
                        <input type="number" class="form-control w-25" name="apartments" size="5">
                    </div>

                    <div class="col-md-8">
                        <label class="col-form-label">Parking</label>
                        <input type="checkbox" class="form-inline custom-checkbox" name="parking">
                    </div>

                    <hr>
                    <div class="col-md-8">
                        <input type="submit" class="btn btn-dark" value="Submit">
                    </div>

                </form>

            </div>
        </div>
    </div>
@endsection
