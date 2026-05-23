import { ModuleStub } from "@/components/module-stub";
import { BarChart3 } from "lucide-react";

export default function Page() {
  return (
    <ModuleStub
      title="Reporting"
      description="Cross-module analytics and attribution."
      icon={BarChart3}
      features={[
        "Source / campaign attribution for contacts",
        "Pipeline conversion and velocity reports",
        "Appointment outcomes and show rates",
        "Conversation response-time SLAs",
        "Agency-level rollups across sub-accounts",
        "Scheduled email / PDF reports",
      ]}
    />
  );
}
