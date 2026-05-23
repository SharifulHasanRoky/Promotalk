import { PageHeader } from "@/components/page-header";
import type { LucideIcon } from "lucide-react";

/**
 * Placeholder used by modules that aren't implemented yet.
 * Lists the planned capabilities so the roadmap is visible in-app.
 */
export function ModuleStub({
  title,
  description,
  icon: Icon,
  features,
}: {
  title: string;
  description: string;
  icon: LucideIcon;
  features: string[];
}) {
  return (
    <>
      <PageHeader title={title} description={description} />
      <div className="p-6">
        <div className="rounded-lg border border-dashed bg-white p-10 text-center">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600">
            <Icon className="h-6 w-6" />
          </div>
          <h2 className="mt-4 text-lg font-semibold">Coming soon</h2>
          <p className="mx-auto mt-2 max-w-md text-sm text-slate-600">
            This module is scaffolded but not yet implemented. The data model, route, and sidebar
            entry are in place — the UI and business logic are next.
          </p>
        </div>

        <div className="mt-6 rounded-lg border bg-white p-6">
          <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
            Planned capabilities
          </h3>
          <ul className="mt-3 grid gap-2 sm:grid-cols-2">
            {features.map((f) => (
              <li key={f} className="flex items-start gap-2 text-sm text-slate-700">
                <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500" />
                {f}
              </li>
            ))}
          </ul>
        </div>
      </div>
    </>
  );
}
