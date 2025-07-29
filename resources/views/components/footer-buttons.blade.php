
<div class="text-center py-4 px-4 flex flex-row gap-4 justify-center items-center">
    <a href="https://play.google.com/store/apps/details?id=si.andree.trolasi&hl=sl" target="_blank"
       class="flex-1 max-w-xs inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white border border-green-600 px-6 py-3 rounded-lg text-sm font-medium shadow-md hover:shadow-lg transition-colors duration-150">

       <div class="flex-shrink-0">
           @include('components.icon-android')
       </div>

       Naloži aplikacijo
    </a>

    <a href="mailto:info@ip21.si?subject=Napaka {{ request()->path() }} "
       class="flex-1 max-w-xs inline-flex items-center justify-center gap-2 bg-white hover:bg-gray-50 text-green-600 border border-green-600 px-6 py-3 rounded-lg text-sm font-medium transition-colors duration-150">

       <div class="flex-shrink-0">
           @include('components.icon-bug')
       </div>

       Sporoči napako
    </a>
</div>

