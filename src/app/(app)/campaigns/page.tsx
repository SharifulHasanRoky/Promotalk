import { ModuleStub } from "@/components/module-stub";
import { Megaphone } from "lucide-react";

export default function Page() {
  return (
    <ModuleStub
      title="Campaigns"
      description="Outbound SMS / email blasts and drips."
      icon={Megaphone}
      features={[
        "Audience builder using CRM filters",
        "Email designer with templates and merge fields",
        "SMS / MMS broadcasts with rate limiting",
        "Scheduling and timezone-aware sends",
        "Open / click / reply / bounce tracking",
        "A/B testing and resend-to-non-openers",
      ]}
    />
  );
}
