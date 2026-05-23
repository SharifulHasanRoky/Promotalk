import { PageHeader } from "@/components/page-header";
import { formatDate } from "@/lib/utils";

// Static demo data so the page works before the database is provisioned.
// Once `prisma db push` + `db:seed` have been run, swap this for:
//   const contacts = await prisma.contact.findMany({ where: { subAccountId } });
const demoContacts = [
  { id: "1", firstName: "Ada",   lastName: "Lovelace", email: "ada@example.com",   phone: "+1 555 010 0101", source: "Website",     createdAt: new Date() },
  { id: "2", firstName: "Alan",  lastName: "Turing",   email: "alan@example.com",  phone: "+1 555 010 0102", source: "Referral",    createdAt: new Date() },
  { id: "3", firstName: "Grace", lastName: "Hopper",   email: "grace@example.com", phone: "+1 555 010 0103", source: "Facebook Ad", createdAt: new Date() },
];

export default function ContactsPage() {
  return (
    <>
      <PageHeader
        title="Contacts"
        description="Your CRM database. People, companies, tags, custom fields."
        actions={
          <button className="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">
            New contact
          </button>
        }
      />
      <div className="p-6">
        <div className="overflow-hidden rounded-lg border bg-white">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-3">Name</th>
                <th className="px-4 py-3">Email</th>
                <th className="px-4 py-3">Phone</th>
                <th className="px-4 py-3">Source</th>
                <th className="px-4 py-3">Created</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {demoContacts.map((c) => (
                <tr key={c.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 font-medium">
                    {c.firstName} {c.lastName}
                  </td>
                  <td className="px-4 py-3 text-slate-600">{c.email}</td>
                  <td className="px-4 py-3 text-slate-600">{c.phone}</td>
                  <td className="px-4 py-3 text-slate-600">{c.source}</td>
                  <td className="px-4 py-3 text-slate-600">{formatDate(c.createdAt)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <p className="mt-3 text-xs text-slate-500">
          Showing demo data. Run <code className="rounded bg-slate-100 px-1">npm run db:push &amp;&amp; npm run db:seed</code> and switch this page to read from Prisma to see live records.
        </p>
      </div>
    </>
  );
}
