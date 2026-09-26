<form method="POST" action="{{ route('logout') }}" class="inline">
    @csrf
    <button type="submit" wire:loading.attr="disabled"
        class="flex min-h-[48px] items-center justify-center gap-2 rounded-xl border border-red-200 px-4 text-[14px] font-semibold text-red-700 hover:bg-red-50 disabled:opacity-60">
        <i data-lucide="log-out" class="h-4 w-4"></i>
        <span>Keluar</span>
    </button>
</form>