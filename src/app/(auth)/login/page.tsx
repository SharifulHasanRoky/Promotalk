export default function LoginPage() {
  return (
    <main className="grid min-h-screen place-items-center bg-slate-100 px-4">
      <form className="w-full max-w-sm rounded-lg border bg-white p-6 shadow-sm">
        <h1 className="text-xl font-semibold">Sign in to Promotalk</h1>
        <p className="mt-1 text-sm text-slate-500">Auth is stubbed in this scaffold.</p>
        <label className="mt-5 block text-sm font-medium">Email</label>
        <input type="email" className="mt-1 w-full rounded-md border px-3 py-2 text-sm" placeholder="you@example.com" />
        <label className="mt-3 block text-sm font-medium">Password</label>
        <input type="password" className="mt-1 w-full rounded-md border px-3 py-2 text-sm" />
        <button
          type="button"
          className="mt-5 w-full rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700"
        >
          Sign in
        </button>
      </form>
    </main>
  );
}
