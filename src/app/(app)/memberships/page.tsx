import { ModuleStub } from "@/components/module-stub";
import { GraduationCap } from "lucide-react";

export default function Page() {
  return (
    <ModuleStub
      title="Memberships"
      description="Courses, communities, and gated content."
      icon={GraduationCap}
      features={[
        "Course builder with categories, modules, and lessons",
        "Drip schedules and prerequisites",
        "Video hosting integration (Mux, Bunny, ...)",
        "Member portal with progress tracking",
        "Free, paid, and subscription access",
        "Certificates on completion",
      ]}
    />
  );
}
