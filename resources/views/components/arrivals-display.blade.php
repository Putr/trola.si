@props(['showDirectionNameOnMobile' => false, 'arrivals'])

<div class="mx-auto">
    @if(count($arrivals) > 0)
    <div class="flex justify-center text-gray-200 pb-2 md:pb-4 min-w-full">
        <div class="w-1/3 md:w-1/2 pr-2 md:pr-4">
            <h2 class="hidden sm:block text-2xl font-black text-right">AVTOBUSNE LINIJE</h2>
        </div>
        <div class="w-2/3 md:w-1/2 pl-2 md:pl-4">
            <h2 class="text-sm md:text-2xl font-black text-left">NASLEDNJI PRIHODI (min)</h2>
        </div>
    </div>
    @endif
    <div class="flex justify-center">
        <div class="w-full text-black">
            @if(count($arrivals) > 0)
            @foreach($arrivals as $arrival)
            <div class="flex justify-center align-middle">
                <div class="w-1/3 md:w-1/2 pr-2 md:pr-4 flex justify-end items-center">
                    <div
                        class="{{ $showDirectionNameOnMobile ? '' : 'hidden sm:block' }} text-xl font-bold text-black pr-4">
                        {{ $arrival['routeDirectionName'] }}
                    </div>
                    <div class="text-2xl bg-gray-200 py-4 w-22 font-bold text-emerald-700 flex justify-center">
                        <div
                            class="border-2 border-emerald-700 rounded-full w-14 h-14 flex items-center justify-center text-2xl font-bold">
                            {{ $arrival['routeName'] }}
                        </div>
                    </div>
                </div>

                <div class="w-2/3 md:w-1/2 pl-2 md:pl-4">
                    <div class="flex">
                        <div
                            class="text-4xl font-bold text-left w-22 h-22 min-w-[5.5rem] bg-gray-200 flex items-center justify-center">
                            @if($arrival['etaMin'])
                            {{ $arrival['etaMin'] }}'
                            @else
                            &nbsp;
                            @endif
                        </div>
                        <div
                            class="text-2xl md:text-3xl font-bold flex justify-start items-center text-gray-600 whitespace-nowrap overflow-hidden">
                            @foreach($arrival['laterEtas'] as $eta)
                            @if($loop->index < 2) <span class="pl-4">{{ $eta }}'</span>
                                @endif
                                @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
            @else
            <div class="text-center text-xl md:text-2xl text-gray-600 py-8">
                Ni napovedanih avtobusov
            </div>
            @endif
        </div>
    </div>
</div>