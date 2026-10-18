@php($logoPng = public_path('logo/desktop/logo.png'))
@if(is_file($logoPng))<img src="data:image/png;base64,{{ base64_encode(file_get_contents($logoPng)) }}" class="logo" alt="Mobilier Addict">@endif
