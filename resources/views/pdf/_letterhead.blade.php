{{-- Papier à en-tête "MOBILIER GROUP'S" — Version A Classique (partagé par tous les PDF) --}}
@php($id = config('gesma.documents'))

<div class="mg-band mg-band-top"></div>
<div class="mg-band mg-band-bottom"></div>

<div class="mg-footer">
    <div class="sep"></div>
    <div class="legal">{{ $id['name'] }} {{ $id['legal_form'] }} au capital de {{ $id['capital'] }} — Siège : {{ $id['siege'] }}</div>
    <div>RCCM : {{ $id['rccm'] }} — N° CC : {{ $id['cc'] }} — Tél : {{ $id['phone1'] }} / {{ $id['phone2'] }}</div>
</div>

<table style="width:100%">
    <tr>
        <td style="width:62%; vertical-align:top">
            <img src="{{ public_path('logo/01_horizontal_couleur_2500px.png') }}" style="width:230px" alt="{{ $id['name'] }}">
        </td>
        <td class="right" style="vertical-align:top; font-size:8.5px">
            <span class="mg-navy" style="font-weight:bold">{{ $id['location'] }}</span><br>
            Tél : {{ $id['phone1'] }}<br>
            {{ $id['phone2'] }}
        </td>
    </tr>
</table>

<div class="mg-rule"></div>
<div class="mg-slogan">{{ $id['slogan'] }}</div>
