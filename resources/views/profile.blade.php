@extends('main')
@section('content')
<div class="container-fluid px-2 px-md-4">
    <div class="page-header min-height-300 border-radius-xl mt-4" style="background-image: url('https://images.unsplash.com/photo-1531512073830-ba890ca4eba2?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80');">
    <span class="mask  bg-gradient-primary  opacity-6"></span>
    </div>
    <div class="card card-body mx-3 mx-md-4 mt-n6">
    <div class="row gx-4 mb-2">
        <div class="col-auto my-auto">
        <div class="h-100">
            <h5 class="mb-1">
            {{Auth::user()->name}}
            </h5>
        </div>
        </div>
    </div>
    <div class="row">
        <div class="row">
        <div class="col-12 col-xl-12">
            <div class="card card-plain h-100">
            <div class="card-header pb-0 p-3">
                <div class="row">
                <div class="col-md-8 d-flex align-items-center">
                    <h6 class="mb-0">Profile Information</h6>
                </div>
                </div>
            </div>
            <div class="card-body p-3">
                <p class="text-sm">
                Hi, I’m {{Auth::user()->name}}, Decisions: If you can’t decide, the answer is no. If two equally difficult paths, choose the one more painful in the short term (pain avoidance is creating an illusion of equality).
                </p>
                <hr class="horizontal gray-light my-4">
                <ul class="list-group">
                <li class="list-group-item border-0 ps-0 pt-0 text-sm"><strong class="text-dark">Full Name:</strong> &nbsp; {{Auth::user()->name}}</li>
                <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">Mobile:</strong> &nbsp; {{Auth::user()->telephone}}</li>
                <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">Email:</strong> &nbsp; {{Auth::user()->email}}</li>
                </ul>
            </div>
            </div>
        </div>
        </div>
    </div>
    </div>
</div>
@endsection