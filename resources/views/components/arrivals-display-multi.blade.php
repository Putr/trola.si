@props(['arrivals'])

<div class="mx-auto overflow-x-auto">
    <div class="">
        <div class="flex justify-center text-gray-200 pb-2 md:pb-4">
            <div class="w-1/3 md:w-1/2 pr-2 md:pr-4">
                <h2 class="hidden sm:block text-2xl font-black text-right">AVTOBUSNE LINIJE</h2>
            </div>
            <div class="w-2/3 md:w-1/2 pl-2 md:pl-4">
                <h2 class="text-sm md:text-2xl font-black text-left">NASLEDNJI PRIHODI (min)</h2>
            </div>
        </div>
        <div class="flex justify-center">
            <div class="w-full text-black">
                @foreach($arrivals as $arrival)
                <div class="flex justify-center align-middle">
                    <div class="w-2/3 md:w-1/2 pr-2 md:pr-4 flex justify-start md:justify-end items-center">
                        <div class="text-sm md:text-xl font-bold text-black pr-4 w-full text-right">
                            {{ $arrival['routeDirectionName'] }}
                        </div>
                        <div
                            class="text-2xl bg-gray-200 py-4 w-22 min-w-[5.5rem] font-bold text-emerald-700 flex justify-center">
                            <div
                                class="border-2 border-emerald-700 rounded-full w-14 h-14 flex items-center justify-center text-2xl font-bold">
                                {{ $arrival['routeName'] }}
                            </div>
                        </div>
                    </div>

                    <div class="block md:hidden w-1/3 pl-2 md:pl-4">
                        <div class="flex">
                            <div
                                class="text-left w-22 h-22 min-w-[5.5rem] bg-gray-200 flex items-center justify-center {{ isset($arrival['etas'][0]) && $arrival['etas'][0] <= 9 ? 'font-bold text-4xl' : 'font-normal text-2xl' }}">
                                @if(isset($arrival['etas'][0]))
                                {{ $arrival['etas'][0] }}'
                                @else
                                &nbsp;
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="hidden md:block w-1/2 pl-2 md:pl-4">
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
            </div>
        </div>
    </div>
</div>