import Link from "next/link";
import { modules } from "@/lib/modules";

export default function LandingPage() {
  return (
    <main className="min-h-screen">
      <section className="bg-gradient-to-br from-brand-600 to-brand-900 text-white">
        <div className="mx-auto max-w-6xl px-6 py-24">
          <p className="text-sm uppercase tracking-widest text-brand-100/80">Promotalk</p>
          <h1 className="mt-3 text-5xl font-bold leading-tight md:text-6xl">
            The all-in-one platform <br /> your business has been duct-taping together.
          </h1>
          <p className="mt-6 max-w-2xl text-lg text-brand-50/90">
            CRM, conversations, calendars, pipelines, automations, funnels, payments, and
            reputation — for agencies and the sub-accounts they serve.
          </p>
          <div className="mt-8 flex gap-3">
            <Link
              href="/dashboard"
              className="rounded-md bg-white px-5 py-2.5 font-medium text-brand-700 hover:bg-brand-50"
            >
              Open the app
            </Link>
            <Link
              href="/login"
              className="rounded-md border border-white/40 px-5 py-2.5 font-medium hover:bg-white/10"
            >
              Sign in
            </Link>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-6 py-20">
        <h2 className="text-2xl font-semibold">Everything in one place</h2>
        <p className="mt-2 text-slate-600">
          Each module ships as its own area of the app and shares the same multi-tenant data model.
        </p>
        <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {modules
            .filter((m) => m.href !== "/dashboard" && m.href !== "/settings")
            .map((m) => (
              <Link
                key={m.href}
                href={m.href}
                className="group rounded-lg border bg-white p-5 transition hover:border-brand-500 hover:shadow-sm"
              >
                <div className="flex items-center gap-3">
                  <m.icon className="h-5 w-5 text-brand-600" />
                  <h3 className="font-semibold group-hover:text-brand-700">{m.label}</h3>
                </div>
                <p className="mt-2 text-sm text-slate-600">{m.description}</p>
              </Link>
            ))}
        </div>
      </section>
    </main>
  );
}
