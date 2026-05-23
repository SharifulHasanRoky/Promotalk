import { Bell, Search } from "lucide-react";

export function AppTopbar() {
  return (
    <header className="flex h-14 items-center gap-4 border-b bg-white px-4">
      <div className="relative max-w-md flex-1">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input
          placeholder="Search contacts, conversations, opportunities..."
          className="h-9 w-full rounded-md border border-slate-200 bg-slate-50 pl-9 pr-3 text-sm outline-none focus:border-brand-500 focus:bg-white"
        />
      </div>
      <button className="rounded-md p-2 text-slate-500 hover:bg-slate-100" aria-label="Notifications">
        <Bell className="h-4 w-4" />
      </button>
      <div className="flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white">
        DA
      </div>
    </header>
  );
}
