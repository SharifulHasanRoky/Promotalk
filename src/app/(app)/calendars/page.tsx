import { ModuleStub } from "@/components/module-stub";
import { Calendar } from "lucide-react";

export default function Page() {
  return (
    <ModuleStub
      title="Calendars"
      description="Booking pages and appointment scheduling."
      icon={Calendar}
      features={[
        "Public booking pages with custom slugs",
        "Round-robin and team calendars",
        "Google / Outlook 2-way sync",
        "Buffer times, padding, daily caps",
        "Reminders and confirmations via SMS / email",
        "Embeddable widget for sites and funnels",
      ]}
    />
  );
}
