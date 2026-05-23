"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { modules } from "@/lib/modules";
import { cn } from "@/lib/utils";

export function AppSidebar() {
  const pathname = usePathname();
  return (
    <aside className="hidden w-60 shrink-0 border-r bg-white md:block">
      <div className="flex h-14 items-center border-b px-4">
        <Link href="/dashboard" className="text-lg font-semibold tracking-tight">
          Promo<span className="text-brand-600">talk</span>
        </Link>
      </div>
      <nav className="flex flex-col gap-0.5 p-2">
        {modules.map((m) => {
          const active = pathname === m.href || pathname.startsWith(m.href + "/");
          return (
            <Link
              key={m.href}
              href={m.href}
              className={cn(
                "flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition",
                active
                  ? "bg-brand-50 text-brand-700"
                  : "text-slate-700 hover:bg-slate-100"
              )}
            >
              <m.icon className="h-4 w-4" />
              {m.label}
            </Link>
          );
        })}
      </nav>
    </aside>
  );
}
