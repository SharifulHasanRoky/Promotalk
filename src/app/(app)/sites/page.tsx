import { ModuleStub } from "@/components/module-stub";
import { Globe } from "lucide-react";

export default function Page() {
  return (
    <ModuleStub
      title="Sites & Funnels"
      description="Hosted landing pages and funnels."
      icon={Globe}
      features={[
        "Block-based page editor",
        "Reusable templates per niche",
        "Custom domains with auto-SSL",
        "Funnel steps with redirects and upsells",
        "Built-in forms, calendars, and checkout blocks",
        "SEO controls and OG image generation",
      ]}
    />
  );
}
