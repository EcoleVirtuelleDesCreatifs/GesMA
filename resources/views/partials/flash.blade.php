@foreach (['success' => 'success', 'error' => 'error', 'info' => 'info', 'warning' => 'warning'] as $key => $class)
    @if (session($key))
        <div class="alert alert-{{ $class }}" role="status">{{ session($key) }}</div>
    @endif
@endforeach
@if ($errors->any() && ! ($hideErrorSummary ?? false))
    <div class="alert alert-error">
        <strong>Veuillez corriger les erreurs suivantes :</strong>
        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
