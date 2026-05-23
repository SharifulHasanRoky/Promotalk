import { PageHeader } from "@/components/page-header";
import { Users, MessageSquare, KanbanSquare, Calendar } from "lucide-react";

const kpis = [
  { label: "Contacts",          value: "—", icon: Users,         hint: "Total in this sub-account" },
  { label: "Open conversations",value: "—", icon: MessageSquare, hint: "Across all channels" },
  { label: "Open opportunities",value: "—", icon: KanbanSquare,  hint: "In active pipelines" },
  { label: "Upcoming bookings", value: "—", icon: Calendar,      hint: "Next 7 days" },
];

export default function DashboardPage() {
  return (
    <>
      <PageHeader
        title="Dashboard"
        description="A unified view of every module, scoped to the active sub-account."
      />
      <div className="p-6">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {kpis.map((k) => (
            <div key={k.label} className="rounded-lg border bg-white p-4">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-slate-600">{k.label}</span>
                <k.icon className="h-4 w-4 text-slate-400" />
              </div>
              <div className="mt-2 text-2xl font-semibold">{k.value}</div>
              <div className="text-xs text-slate-500">{k.hint}</div>
            </div>
          ))}
        </div>

        <div className="mt-6 grid gap-4 lg:grid-cols-2">
          <div className="rounded-lg border bg-white p-5">
            <h3 className="font-semibold">Recent activity</h3>
            <p className="mt-2 text-sm text-slate-500">
              Wire this to an Activity feed once events are emitted from each module.
            </p>
          </div>
          <div className="rounded-lg border bg-white p-5">
            <h3 className="font-semibold">Quick actions</h3>
            <p className="mt-2 text-sm text-slate-500">
              Add contact · Send broadcast · Create pipeline · Build workflow
            </p>
          </div>
        </div>
      </div>
    </>
  );
}
