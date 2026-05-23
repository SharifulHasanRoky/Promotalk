import { ModuleStub } from "@/components/module-stub";
import { Settings } from "lucide-react";

export default function Page() {
  return (
    <ModuleStub
      title="Settings"
      description="Sub-accounts, team, integrations, billing."
      icon={Settings}
      features={[
        "Agency profile and branding (white-label)",
        "Sub-account (Location) management",
        "Team members, roles, and per-location access",
        "Integrations: Twilio, Stripe, Google, Outlook, Meta, ...",
        "Custom fields and tag management",
        "Billing and usage (calls, SMS segments, emails)",
      ]}
    />
  );
}
