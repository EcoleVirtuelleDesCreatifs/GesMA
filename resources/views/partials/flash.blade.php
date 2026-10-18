@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        @if($errors->count() === 1)
            {{ $errors->first() }}
        @else
            Merci de corriger les points suivants :
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
