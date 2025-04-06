@extends('layout')

@section('title', 'Navodila za uporabo | Trola.si')

@section('description', 'Navodila za uporabo spletne strani Trola.si. Kako najti in preveriti prihode avtobusov v
Ljubljani.')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">Navodila za uporabo</h1>

    <div class="prose max-w-none">
        <p>Aplikacija prikazuje naslednje prihode avtobusov za izbrano postajo in opravlja enako nalogo kot
            prikaznovalniki na postajah - le da deluje za vse postaje (tudi tiste, ki jih nimajo).</p>

        <h2 class="text-xl font-bold mt-8 mb-4">Pomen ikon</h2>
        <p>Avtobusne postaje so označene po smeri poti v relaciji do centra mesta.</p>

        <p class="mt-4"><strong>Za "center" se šteje križišče Gosposvetske in Slovenske ceste ("Ajdovščina").</strong>
        </p>

        <div class="flex flex-col gap-4 mt-4">
            <div class="flex items-center gap-4">
                <img src="/imgs/bullseye-to_small.png" alt="Proti centru" class="w-8">
                <span>Proti centru</span>
            </div>
            <div class="flex items-center gap-4">
                <img src="/imgs/bullseye-from_small.png" alt="Iz centra" class="w-8">
                <span>Iz centra</span>
            </div>
            <div class="flex items-center gap-4">
                <img src="/imgs/all.svg" alt="V obe smeri" class="w-8">
                <span>V obe smeri oz. smer ni določena</span>
            </div>
        </div>

        <h2 class="text-xl font-bold mt-8 mb-4">Iskanje</h2>
        <ul class="list-disc list-inside space-y-2">
            <li>Iščete lahko po imenu postaje ali njeni številki (oba podatka najdete na napisu na postaji nad košom za
                smeti).</li>
            <li>Z filtri pod poljem za vpis imena lahko omejite rezultate po smeri.</li>
            <li>Predizbrano je iskanje za postaje v vse smeri.</li>
        </ul>
        <p class="mt-4">Če iščete po imenu ni potrebno napisati točnega naslova, v kolikor je med rezultati več kot ena
            postaja vam
            boste med njimi lahko izbirali.</p>

        <h2 class="text-xl font-bold mt-8 mb-4">Prikaz podatkov</h2>
        <p>Ko imate izbrano postajo je pod njenim imenom prikazana tudi smer. S klikom na drugo smer boste pridobili
            podatke za njeno nasprotno postajo (ta je lahko včasih poimenovana drugače). Če izberete možnost "vse smeri"
            bodo prikazni rezultati za obe postaji.</p>

        <h2 class="text-xl font-bold mt-8 mb-4">Branje podatkov o prihodih</h2>
        <p>Vmesnik je optimiziran za hitro prepoznavo avtobusov, ki prihajajo v naslednjih 10 minutah (desni
            siv
            stolpec).</p>
        <p class="mt-4">Avtobusi so razporejeni po vrstnem redu njihove številke poti. Prikazani so le avtobusi, ki
            imajo vsaj en
            prihod v naslednji uri.</p>
        <p class="mt-4">V primeru izbire prikaza podatkov v obe smeri na mobilni napravi bo prikazan tudi ime poti in
            med prihodi le
            naslednji prihod. Prihodi v naslednjih 10 minutah bodo odebeljeni in večji.</p>

        <h2 class="text-xl font-bold mt-8 mb-4">Vprašanja, predlogi in kritike</h2>
        <p>Pišite nam na <a href="mailto:info@ip21.si" class="text-blue-600 hover:text-blue-800">info@ip21.si</a></p>
    </div>
</div>
@endsection