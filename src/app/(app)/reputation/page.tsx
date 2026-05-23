import { ModuleStub } from "@/components/module-stub";
import { Star } from "lucide-react";

export default function Page() {
  return (
    <ModuleStub
      title="Reputation"
      description="Review requests and rating monitoring."
      icon={Star}
      features={[
        "Automated review request via SMS / email",
        "Smart routing: positive -> public review, negative -> private feedback",
        "Google and Facebook integrations",
        "Reply to reviews from inside the app",
        "Per-location rating dashboards",
        "Email digest of new reviews",
      ]}
    />
  );
}
