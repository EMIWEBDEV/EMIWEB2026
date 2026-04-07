@extends($layout ?? 'layouts.master2')
@push('css')
    @vite('resources/js/vueapp.js')
    @inertiaHead
@endpush

@section('content')
    @inertia
@endsection

@section('script')
    <script src="https://kit.fontawesome.com/5bb4e904c2.js" crossorigin="anonymous"></script>
    <script src="{{ URL::asset('assets/js/pages/form-wizard.init.js') }}"></script>
    <script src="{{ URL::asset('/assets/js/app.min.js') }}"></script>
@endsection
